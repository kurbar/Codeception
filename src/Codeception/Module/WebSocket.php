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

	/**
	 * @var Client
	 */
	protected $client;

	private $response;

	public function _before(TestCase $test)
	{
		if (!$this->config['host'] || !$this->config['port']) {
			throw new ModuleConfigException(__CLASS__, 'WebSocket server host and port need to be configured');
		}

		$host = $this->config['host'];
		$port = $this->config['port'];

		$path = $this->config['path'] ?: '/';

		$this->loop = LoopFactory::create();
		$this->client = new Client($this->loop, $host, $port, $path);
	}

	public function sendWsRequest($action, array $params = array())
	{
		$this->response = null;
		$loop = $this->loop;

		$this->client->setOnWelcomeCallback(function (Client $conn) use (&$response, $loop, $action, $params) {
			$conn->call($action, array($params), function ($data) use (&$response, $loop, $conn) {
				$response = $data;
				$loop->stop();
			});
		});

		$this->debugSection('Response', $response);
	}

}
