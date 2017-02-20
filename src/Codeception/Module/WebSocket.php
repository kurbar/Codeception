<?php

namespace Codeception\Module;

use Codeception\Exception\ModuleConfig as ModuleConfigException;
use Codeception\Lib\WebSocket\Client;
use Codeception\Module;
use Codeception\TestCase;
use React\EventLoop\Factory as LoopFactory;


class WebSocket extends Module
{

	/**
	 * @var LoopFactory
	 */
	protected $loop;

	private $host;

	private $port;

	private $path;

	public $response;

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

		$loop = LoopFactory::create();
		$client = new Client($loop, $this->host, $this->port, $this->path);

		$this->debug("Creating socket connection to host '{$this->host}' port {$this->port}");
		$self = $this;

		$client->setOnWelcomeCallback(function (Client $conn, $data) use ($self, &$response, $action, $params, $loop) {
			$self->debug('Connected. Sending ' . $action);

			$conn->call($action, $params, function ($data) use ($self, &$response, $loop) {
				$self->response = $data;
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

	public function seeCallResultContains($key)
	{
		\PHPUnit_Framework_Assert::assertArrayHasKey($key, $this->response);
	}

}
