<?php

namespace Amp\Redis;

use Amp\DeferredFuture;
use Amp\Future;
use Amp\Redis\Connection\RedisConnector;
use Amp\Redis\Connection\RespSocket;
use Revolt\EventLoop;

final class RemoteExecutor implements QueryExecutor
{
    /** @var \SplQueue<array{DeferredFuture, string[]}> */
    private readonly \SplQueue $queue;

    private int $database;

    private bool $running = false;

    private ?RespSocket $socket = null;

    public function __construct(
        private readonly RedisConfig $config,
        private readonly ?RedisConnector $connector = null,
    ) {
        $this->database = $config->getDatabase();
        $this->queue = new \SplQueue();
    }

    public function __destruct()
    {
        $this->running = false;
        $this->socket?->close();
    }

    /**
     * @param array<array-key, int|float|string> $query
     */
    public function execute(array $query, ?\Closure $responseTransform = null): mixed
    {
        if (!$this->running) {
            $this->run();
        }

        $query = \array_map(strval(...), $query);

        $command = \strtolower($query[0] ?? '');

        try {
            $response = $this->enqueue(...$query)->await();
        } finally {
            if ($command === 'quit') {
                $this->socket?->close();
            }
        }

        if ($command === 'select') {
            $this->database = (int) $query[1];
        }

        return $responseTransform ? $responseTransform($response) : $response;
    }

    private function enqueue(string ...$args): Future
    {
        $deferred = new DeferredFuture();
        $this->queue->push([$deferred, $args]);

        $this->socket?->reference();

        try {
            $this->socket?->write(...$args);
        } catch (RedisException) {
            $this->socket = null;
        }

        return $deferred->getFuture();
    }

    private function run(): void
    {
        $config = $this->config;
        $connector = $this->connector ?? Connection\redisConnector();
        $queue = $this->queue;
        $running = &$this->running;
        $socket = &$this->socket;
        $database = &$this->database;
        EventLoop::queue(static function () use (&$socket, &$running, &$database, $queue, $config, $connector): void {
            try {
                while ($running) {
                    $socket = $connector->connect($config->withDatabase($database));
                    $socket->unreference();

                    try {
                        foreach ($queue as [$deferred, $args]) {
                            $socket->reference();
                            $socket->write(...$args);
                        }

                        while ($response = $socket->read()) {
                            /** @var DeferredFuture $deferred */
                            [$deferred] = $queue->shift();
                            if ($queue->isEmpty()) {
                                $socket->unreference();
                            }

                            try {
                                $deferred->complete($response->unwrap());
                            } catch (\Throwable $exception) {
                                $deferred->error($exception);
                            }
                        }
                    } catch (RedisException) {
                        // Attempt to reconnect after failure.
                    } finally {
                        $socket = null;
                    }
                }
            } catch (\Throwable $exception) {
                $exception = new SocketException($exception->getMessage(), 0, $exception);

                while (!$queue->isEmpty()) {
                    /** @var DeferredFuture $deferred */
                    [$deferred] = $queue->shift();
                    $deferred->error($exception);
                }

                $running = false;
            }
        });

        $this->running = true;
    }
}
