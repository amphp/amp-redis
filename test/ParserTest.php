<?php

namespace Amp\Redis;

use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase {
    /**
     * @test
     */
    function bulkString() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("$3\r\nfoo\r\n");

        $this->assertEquals("foo", $result);
    }

    /**
     * @test
     */
    function integer() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append(":42\r\n");

        $this->assertEquals(42, $result);
    }

    /**
     * @test
     */
    function simpleString() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("+foo\r\n");

        $this->assertEquals("foo", $result);
    }

    /**
     * @test
     */
    function error() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("-ERR something went wrong :(\r\n");

        $this->assertInstanceOf("Amp\\Redis\\QueryException", $result);
    }

    /**
     * @test
     */
    function stringNull() {
        $result = false;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("$-1\r\n");

        $this->assertSame(null, $result);
    }

    /**
     * @test
     */
    function pipeline() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("+foo\r\n+bar\r\n");

        $this->assertEquals("bar", $result);
    }

    /**
     * @test
     */
    function latency() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("$3\r");
        $this->assertEquals(null, $result);
        $parser->append("\nfoo\r");
        $this->assertEquals(null, $result);
        $parser->append("\n");
        $this->assertEquals("foo", $result);
    }

    /**
     * @test
     */
    function arrayNull() {
        $result = false;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("*-1\r\n");

        $this->assertSame(null, $result);
    }

    /**
     * @test
     */
    function arrayEmpty() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("*0\r\n");

        $this->assertEquals([], $result);
    }

    /**
     * @test
     */
    function arraySingle() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("*1\r\n+foo\r\n");

        $this->assertEquals(["foo"], $result);
    }

    /**
     * @test
     */
    function arrayMultiple() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("*3\r\n+foo\r\n:42\r\n$11\r\nHello World\r\n");

        $this->assertEquals(["foo", 42, "Hello World"], $result);
    }

    /**
     * @test
     */
    function arrayComplex() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("*3\r\n*1\r\n+foo\r\n:42\r\n*2\r\n+bar\r\n$3\r\nbaz\r\n");

        $this->assertEquals([["foo"], 42, ["bar", "baz"]], $result);
    }

    /**
     * @test
     */
    function arrayInnerEmpty() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("*1\r\n*-1\r\n");

        $this->assertEquals([null], $result);
    }

    /**
     * @test
     * @see https://github.com/amphp/redis/commit/a495189735412c8962b219b6633685ddca84040c
     */
    function arrayPipeline() {
        $result = null;
        $parser = new RespParser(function ($resp) use (&$result) {
            $result = $resp;
        });
        $parser->append("*1\r\n+foo\r\n*1\r\n+bar\r\n");

        $this->assertEquals(["bar"], $result);
    }

    /**
     * @test
     * @expectedException \Amp\Redis\ParserException
     */
    function unknownType() {
        $parser = new RespParser(function ($resp) {
        });
        $parser->append("3$\r\nfoo\r\n");
    }
}
