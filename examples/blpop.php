<?php

require __DIR__ . "/../vendor/autoload.php";

Amp\Loop::run(function () {
    $pushClient = new Amp\Redis\Client("tcp://localhost:6379");
    $pushClient->blpop("foobar-list")->onResolve(function ($error, $value) {
        if ($error) {
            print "Error: " . $error->getMessage() . PHP_EOL;
        } else {
            print "Value: " . var_export($value, true) . PHP_EOL;
        }

        Amp\Loop::stop();
    });

    $client = new Amp\Redis\Client("tcp://localhost:6379");

    print "Pushing value…" . PHP_EOL;
    yield $client->lpush("foobar-list", "42");
    print "Value pushed." . PHP_EOL;
});
