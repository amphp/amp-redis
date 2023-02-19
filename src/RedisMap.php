<?php declare(strict_types=1);
/** @noinspection DuplicatedCode */

namespace Amp\Redis;

final class RedisMap
{
    public function __construct(
        private readonly QueryExecutor $queryExecutor,
        private readonly string $key,
    ) {
    }

    /**
     * @link https://redis.io/commands/hdel
     */
    public function remove(string $field, string ...$fields): int
    {
        return $this->queryExecutor->execute('hdel', $this->key, $field, ...$fields);
    }

    /**
     * @link https://redis.io/commands/hexists
     */
    public function hasKey(string $field): bool
    {
        return (bool) $this->queryExecutor->execute('hexists', $this->key, $field);
    }

    /**
     * @link https://redis.io/commands/hincrby
     */
    public function increment(string $field, int $increment = 1): int
    {
        return $this->queryExecutor->execute('hincrby', $this->key, $field, $increment);
    }

    /**
     * @link https://redis.io/commands/hincrbyfloat
     */
    public function incrementByFloat(string $field, float $increment): float
    {
        return (float) $this->queryExecutor->execute('hincrbyfloat', $this->key, $field, $increment);
    }

    /**
     * @link https://redis.io/commands/hkeys
     */
    public function getKeys(): array
    {
        return $this->queryExecutor->execute('hkeys', $this->key);
    }

    /**
     * @link https://redis.io/commands/hlen
     */
    public function getSize(): int
    {
        return $this->queryExecutor->execute('hlen', $this->key);
    }

    /**
     * @link https://redis.io/commands/hvals
     * @link https://redis.io/commands/hgetall
     */
    public function getAll(): array
    {
        return Internal\toMap($this->queryExecutor->execute('hgetall', $this->key));
    }

    /**
     * @link https://redis.io/commands/hmset
     *
     * @param array<string, int|float|string> $data
     */
    public function setValues(array $data): void
    {
        $array = [$this->key];

        foreach ($data as $dataKey => $value) {
            $array[] = $dataKey;
            $array[] = $value;
        }

        $this->queryExecutor->execute('hmset', ...$array);
    }

    /**
     * @link https://redis.io/commands/hget
     */
    public function getValue(string $field): string
    {
        return $this->queryExecutor->execute('hget', $this->key, $field);
    }

    /**
     * @link https://redis.io/commands/hmget
     */
    public function getValues(string $field, string ...$fields): array
    {
        return $this->queryExecutor->execute('hmget', $this->key, $field, ...$fields);
    }

    /**
     * @link https://redis.io/commands/hset
     */
    public function setValue(string $field, string $value): bool
    {
        return (bool) $this->queryExecutor->execute('hset', $this->key, $field, $value);
    }

    /**

     * @link https://redis.io/commands/hsetnx
     */
    public function setValueWithoutOverwrite(string $field, string $value): bool
    {
        return (bool) $this->queryExecutor->execute('hsetnx', $this->key, $field, $value);
    }

    /**

     * @link https://redis.io/commands/hstrlen
     */
    public function getLength(string $field): int
    {
        return $this->queryExecutor->execute('hstrlen', $this->key, $field);
    }

    /**
     * @return \Traversable<array{string, string}>
     *
     * @link https://redis.io/commands/hscan
     */
    public function scan(?string $pattern = null, ?int $count = null): \Traversable
    {
        $cursor = 0;

        do {
            $query = [$this->key, $cursor];

            if ($pattern !== null) {
                $query[] = 'MATCH';
                $query[] = $pattern;
            }

            if ($count !== null) {
                $query[] = 'COUNT';
                $query[] = $count;
            }

            /** @var list<string> $keys */
            [$cursor, $keys] = $this->queryExecutor->execute('HSCAN', ...$query);

            $count = \count($keys);
            \assert($count % 2 === 0);

            for ($i = 0; $i < $count; $i += 2) {
                yield [$keys[$i], $keys[$i + 1]];
            }
        } while ($cursor !== '0');
    }
}
