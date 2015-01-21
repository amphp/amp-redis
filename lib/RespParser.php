<?php

namespace Amp\Redis;

class RespParser {
	const CRLF = "\r\n";

	private $responseCallback;
	private $buffer = "";
	private $currentResponse = null;
	private $arrayStack;
	private $currentSize;
	private $arraySizes;

	public function __construct (callable $responseCallback) {
		$this->responseCallback = $responseCallback;
	}

	public function reset() {
		$this->buffer = "";
		$this->currentResponse = $this->arrayStack = $this->currentSize = $this->arraySizes = null;
	}

	public function append ($str) {
		$this->buffer .= $str;

		while ($this->tryParse()) ;
	}

	private function tryParse () {
		if (strlen($this->buffer) === 0) {
			return false;
		}

		$type = $this->buffer[0];
		$pos = strpos($this->buffer, self::CRLF);

		if ($pos === false) {
			return false;
		}

		switch ($type) {
			case Resp::TYPE_SIMPLE_STRING:
			case Resp::TYPE_INTEGER:
			case Resp::TYPE_ARRAY:
			case Resp::TYPE_ERROR:
				$payload = substr($this->buffer, 1, $pos - 1);
				$remove = $pos + 2;
				break;

			case Resp::TYPE_BULK_STRING:
				$length = (int) substr($this->buffer, 1, $pos);

				if($length === -1) {
					$payload = null;
					$remove = $pos + 2;
				} else {
					if (strlen($this->buffer) < $pos + $length + 4) {
						return false;
					}

					$payload = substr($this->buffer, $pos + 2, $length);
					$remove = $pos + $length + 4;
				}

				break;

			default:
				throw new ParserException (
					sprintf("unknown resp data type: %s", $type)
				);
		}

		$this->buffer = substr($this->buffer, $remove);

		switch ($type) {
			case Resp::TYPE_INTEGER:
			case Resp::TYPE_ARRAY:
				$payload = intval($payload);
				break;

			case Resp::TYPE_ERROR:
				$payload = new RedisException($payload);
				break;

			default:
				break;
		}

		$this->onRespParsed($type, $payload);

		return true;
	}

	private function onRespParsed ($type, $payload) {
		if ($this->currentResponse !== null) { // extend array response
			if ($type === Resp::TYPE_ARRAY) {
				if ($payload >= 0) {
					$this->arraySizes[] = $this->currentSize;
					$this->arrayStack[] = &$this->currentResponse;
					$this->currentSize = $payload + 1;
					$this->currentResponse[] = [];
					$this->currentResponse = &$this->currentResponse[sizeof($this->currentResponse) - 1];
				} else {
					$this->currentResponse[] = null;
				}
			} else {
				$this->currentResponse[] = $payload;
			}

			while (--$this->currentSize === 0) {
				if (sizeof($this->arrayStack) === 0) {
					call_user_func($this->responseCallback, $this->currentResponse);
					$this->currentResponse = null;
					return;
				}

				// index doesn't start at 0 :(
				end($this->arrayStack);
				$key = key($this->arrayStack);
				$this->currentResponse = &$this->arrayStack[$key];
				$this->currentSize = array_pop($this->arraySizes);
				unset($this->arrayStack[$key]);
			}
		} else if ($type === Resp::TYPE_ARRAY) { // start new array response
			if ($payload > 0) {
				$this->currentSize = $payload;
				$this->arrayStack = $this->arraySizes = $this->currentResponse = [];
			} else if ($payload === 0) {
				call_user_func($this->responseCallback, []);
			} else {
				call_user_func($this->responseCallback, null);
			}
		} else { // single data type response
			call_user_func($this->responseCallback, $payload);
		}
	}
}
