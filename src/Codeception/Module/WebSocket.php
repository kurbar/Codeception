<?php

namespace Codeception\Module;

use Codeception\Exception\ModuleConfigException;
use Codeception\Lib\WebSocket\Client;
use Codeception\Module;
use Codeception\TestInterface;
use React\EventLoop\Factory as LoopFactory;

class WebSocket extends Module
{

    const CALL_RESULT = 3;

    private $host;

    private $port;

    private $path;

    private $protocol;

    public $response;

    public $responseType;

    public function _before(TestInterface $test)
    {
        if (!isset($this->config['host']) || !isset($this->config['port'])) {
            throw new ModuleConfigException(__CLASS__, 'WebSocket server host and port need to be configured');
        }

        $this->host = $this->config['host'];
        $this->port = $this->config['port'];

        $this->path = isset($this->config['path']) ? $this->config['path'] : '/';

        $this->protocol = isset($this->config['protocol']) ? $this->config['protocol'] : 'wamp';

        $this->debugSection('Host', $this->host);
        $this->debugSection('Port', $this->port);
        $this->debugSection('Path', $this->path);
    }

    public function sendWsRequest($action, array $params = [])
    {
        $this->response = null;
        $this->responseType = null;

        $loop = LoopFactory::create();
        $client = new Client($loop, $this->host, $this->port, $this->path, $this->protocol);

        $this->debug(sprintf('Creating socket connection to host %s port %s', $this->host, $this->port));

        $client->setOnWelcomeCallback(function (Client $conn, $data) use ($action, $params, $loop) {
            $this->debug('Connected. Sending ' . $action);

            $conn->call($action, [$params], function ($data, $type) use ($loop) {
                $this->response = $data;
                $this->responseType = $type;
                $loop->stop();
            });
        });

        $loop->addPeriodicTimer(20, function() use ($loop) {
            $loop->stop();
            $this->fail('Connection timeout');
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

        if (strpos($entry, '::') !== false) {
            $parts = explode('::', $entry);
            $key = array_pop($parts);

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
        $this->debugSection('Validate array structure schema', var_export($schema, true));
        $result = [];

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
