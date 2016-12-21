<?php

namespace PCextreme\Cloudstack\Test;

use PCextreme\Cloudstack\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;

use Mockery as m;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AbstractClient
     */
    protected $client;

    protected function setUp()
    {
        $this->client = new Client([
            'urlApi' => 'https://example.com/client/api',
            'apiKey' => 'mock_api_key',
            'secretKey' => 'mock_secret_key',
        ]);
    }

    public function testRequiredOptions()
    {
        $required = [
            'urlApi' => 'https://example.com/client/api',
            'apiKey' => 'mock_api_key',
            'secretKey' => 'mock_secret_key',
        ];

        // Test each of the required options by removing a single value
        // and attempting to create a new client.
        foreach ($required as $key => $value) {
            $options = $required;
            unset($options[$key]);

            try {
                $client = new Client($options);
            } catch (\Exception $e) {
                $this->assertInstanceOf('\InvalidArgumentException', $e);
            }
        }

        $client = new Client($required + []);
    }

    public function testConfigurableOptions()
    {
        $options = [
            'apiList' => '/../cache/api_list.php',
            'responseError' => 'errortext',
            'responseCode' => 'errorcode',
        ];

        $client = new Client($options + [
            'urlApi' => 'https://example.com/client/api',
            'apiKey' => 'mock_api_key',
            'secretKey' => 'mock_secret_key',
        ]);

        foreach ($options as $key => $expected) {
            $this->assertAttributeEquals($expected, $key, $client);
        }
    }


    public function commandMethodProvider()
    {
        return [
            ['mockCommand', 'GET'],
            ['deployVirtualMachine', 'POST'],
            ['login', 'POST'],
        ];
    }

    /**
     * @dataProvider commandMethodProvider
     */
    public function testCommandMethod($command, $expected)
    {
        $this->assertEquals(
            $this->client->getCommandMethod($command),
            $expected
        );
    }

    public function testCheckResponse()
    {
        $response = m::mock(ResponseInterface::class);

        $reflection = new \ReflectionClass(get_class($this->client));
        $checkResponse = $reflection->getMethod('checkResponse');
        $checkResponse->setAccessible(true);

        $this->assertNull($checkResponse->invokeArgs($this->client, [$response, []]));
    }

    /**
     * @expectedException PCextreme\Cloudstack\Exception\ClientException
     */
    public function testCheckResponseThrowsException()
    {
        $response = m::mock(ResponseInterface::class);

        $reflection = new \ReflectionClass(get_class($this->client));
        $checkResponse = $reflection->getMethod('checkResponse');
        $checkResponse->setAccessible(true);
        $checkResponse->invokeArgs($this->client, [$response, ['clientResponse' => [
            'cserrorcode' => 1234,
            'errorcode' => 1234,
            'errortext' => 'foobar',
            'uuidList' => [],
        ]]]);
    }
}
