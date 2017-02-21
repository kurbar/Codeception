<?php

namespace Codeception\Module;

use Codeception\Exception\ModuleConfig as ModuleConfigException;
use Codeception\Lib\WebSocket\Client;
use Codeception\Module;
use Codeception\TestCase;
use React\EventLoop\Factory as LoopFactory;


class WebSocket extends Module
{

	const CALL_RESULT = 2;

	/**
	 * @var LoopFactory
	 */
	protected $loop;

	private $host;

	private $port;

	private $path;

	public $response;

	public $responseType;

	public function _before(TestCase $test)
	{
		if (!isset($this->config['host']) || !isset($this->config['port'])) {
			throw new ModuleConfigException(__CLASS__, 'WebSocket server host and port need to be configured');
		}

		$this->host = $this->config['host'];
		$this->port = $this->config['port'];

		$this->path = isset($this->config['path']) ? $this->config['path'] : '/';

		$this->debugSection('Host', $this->host);
		$this->debugSection('Port', $this->port);
		$this->debugSection('Path', $this->path);
	}

	public function sendWsRequest($action, array $params = array())
	{
		$this->response = null;
		$this->responseType = null;

		$loop = LoopFactory::create();
		$client = new Client($loop, $this->host, $this->port, $this->path);

		$this->debug("Creating socket connection to host '{$this->host}' port {$this->port}");
		$self = $this;

		$client->setOnWelcomeCallback(function (Client $conn, $data) use ($self, &$response, $action, $params, $loop) {
			$self->debug('Connected. Sending ' . $action);

			$conn->call($action, array($params), function ($data, $type) use ($self, &$response, $loop) {
				$self->response = $data;
				$self->responseType = $type;
				$loop->stop();
			});
		});

		$loop->addPeriodicTimer(20, function() use ($self, $loop) {
			$self->debug('20 seconds timeout');
			$loop->stop();
			$self->fail('Timeout');
		});

		$loop->run();

		$this->debugSection('Response', var_export($this->response, true));
	}

	public function seeResponseIsCallResult()
	{
		\PHPUnit_Framework_Assert::assertTrue($this->responseType === self::CALL_RESULT);
	}

	public function seeCallResultHasStructure(array $schema)
	{
		\PHPUnit_Framework_Assert::assertTrue($this->validateArrayStructure($schema, $this->response));
	}

	public function seeCallResultContainsEntry($key)
	{
		\PHPUnit_Framework_Assert::assertArrayHasKey($key, $this->response);
	}

	public function seeCallResultEntryHasValue($entry, $value)
	{
		$parent = $this->response;

		if (strpos($entry, '::') !== false) { // Multi-dimensional look-up
			$parts = explode('::', $entry);
			$key = array_pop($parts); // element key

			foreach ($parts as $sKey) {
				if (array_key_exists($sKey, $parent)) {
					$parent = $parent[$sKey];
				} else {
					break;
				}
			}
		} else {
			$key = $entry;
		}

		\PHPUnit_Framework_Assert::assertTrue($parent[$key] === $value);
	}

	private function validateArrayStructure(array $schema, array $array)
	{
		$this->debugSection('Validate Array Structure Schema', var_export($schema, true));
		$result = array();

		foreach ($schema as $key => $value) {
			if (array_key_exists($key, $array)) {
				if (is_array($value)) {
					$diff = $this->validateArrayStructure($value, $array[$key]);
					if ($diff === false) {
						$result[$key] = $diff;
					}
				}
			} else {
				$result[$key] = $value;
			}
		}

		return count($result) === 0;
	}

}
