<?php

namespace PCextreme\Cloudstack\Test;

use PCextreme\Cloudstack\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;

use Mockery as m;

class ClientTest extends TestCase
{
    const CLIENT_CONFIG = [
        'urlApi' => 'https://example.com/client/api',
        'apiKey' => 'mock_api_key',
        'secretKey' => 'mock_secret_key',
    ];

    /**
     * @var AbstractClient
     */
    protected $client;

    protected function setUp()
    {
        $this->client = new Client(self::CLIENT_CONFIG);
    }

    public function testRequiredOptions()
    {
        $required = self::CLIENT_CONFIG;

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

    // TODO: testGetRequiredOptions

    // TODO: testCommand

    /**
     * @expectedException \RuntimeException
     */
    public function testAssertRequiredCommandOptionsChecksForInvalidCommands()
    {
        $client = m::mock(Client::class);
        $client->shouldAllowMockingProtectedMethods();
        $client->shouldReceive('isCommandValid')->with('mockCommand')->andReturn(false);

        $method = $this->getMethod(Client::class, 'assertRequiredCommandOptions');
        $response = $method->invokeArgs($client, ['mockCommand']);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAssertRequiredCommandOptionsChecksForUnprovidedOptions()
    {
        $mockCommand = 'mockCommand';
        $mockCommandOptions = [
            'valueA' => 1,
            'valueB' => 2
        ];
        $mockCommandRequiredOptions = [
            'valueA', 'valueB', 'valueC'
        ];

        $client = m::mock(Client::class);
        $client->shouldAllowMockingProtectedMethods();
        $client->shouldReceive('isCommandValid')->with($mockCommand)->andReturn(true);
        $client->shouldReceive('getRequiredCommandParameters')->with($mockCommand)->andReturn($mockCommandRequiredOptions);

        $method = $this->getMethod(Client::class, 'assertRequiredCommandOptions');
        $response = $method->invokeArgs($client, [$mockCommand, $mockCommandOptions]);
    }

    public function testAssertRequiredCommandOptionsCompletesWithCorrectOptions()
    {
        $mockCommand = 'mockCommand';
        $mockCommandOptions = [
            'valueA' => 1,
            'valueB' => 2
        ];
        $mockCommandRequiredOptions = [
            'valueA', 'valueB',
        ];

        $client = m::mock(Client::class);
        $client->shouldAllowMockingProtectedMethods();
        $client->shouldReceive('isCommandValid')->with($mockCommand)->andReturn(true);
        $client->shouldReceive('getRequiredCommandParameters')->with($mockCommand)->andReturn($mockCommandRequiredOptions);

        $method = $this->getMethod(Client::class, 'assertRequiredCommandOptions');
        $response = $method->invokeArgs($client, [$mockCommand, $mockCommandOptions]);
    }

    public function testAssertRequiredCommandOptionsCompletesWithAdditionalOptions()
    {
        $mockCommand = 'mockCommand';
        $mockCommandOptions = [
            'valueA' => 1,
            'valueB' => 2
        ];
        $mockCommandRequiredOptions = [
            'valueA'
        ];

        $client = m::mock(Client::class);
        $client->shouldAllowMockingProtectedMethods();
        $client->shouldReceive('isCommandValid')->with($mockCommand)->andReturn(true);
        $client->shouldReceive('getRequiredCommandParameters')->with($mockCommand)->andReturn($mockCommandRequiredOptions);

        $method = $this->getMethod(Client::class, 'assertRequiredCommandOptions');
        $response = $method->invokeArgs($client, [$mockCommand, $mockCommandOptions]);
    }

    public function testIsCommandValid()
    {
        $commands = array_flip(['command_a', 'command_b']);

        $client = m::mock(Client::class);
        $client->shouldReceive('getApiList')->andReturn($commands);

        $method = $this->getMethod(Client::class, 'isCommandValid');
        $validResponse = $method->invokeArgs($client, ['command_a']);

        $method = $this->getMethod(Client::class, 'isCommandValid');
        $invalidResponse = $method->invokeArgs($client, ['command_c']);

        $this->assertEquals($validResponse, true, 'Command: [command_a] should be valid but is not');
        $this->assertEquals($invalidResponse, false, 'Command: [command_c] should not be valid but is');
    }

    public function testRequiredCOmmandParameters()
    {
        $commands = [
            'mock_command' => [
                'params' => [
                    'param_a' => ['required' => true],
                    'param_b' => ['required' => false],
                    'param_c' => ['required' => true],
                    'param_d' => ['required' => false],
                ]
            ]
        ];

        $client = m::mock(Client::class);
        $client->shouldReceive('getApiList')->andReturn($commands);

        $method = $this->getMethod(Client::class, 'getRequiredCommandParameters');
        $response = $method->invokeArgs($client, ['mock_command']);

        $this->assertEquals($response, ['param_a', 'param_c']);
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

    public function testGetCommandQuery()
    {
        $params = [1,2,3];
        $query = '1&2&3';

        $client = m::mock(Client::class)->makePartial();
        $client->shouldAllowMockingProtectedMethods();
        $client->shouldReceive('signCommandParameters')->with($params)->andReturn($query);

        $this->assertEquals($query, $client->getCommandQuery($params));
    }

    // TODO: testGetCommandUrl

    // TODO: testGetCommandParameters

    // TODO: testSignCommandParameters

    // TODO: testGetApiList

    // TODO: testSetApiList

    /**
     * @dataProvider appendQueryProvider
     */
    public function testAppendQuery($url, $query, $result)
    {
        $client = m::mock(Client::class);
        $method = $this->getMethod(Client::class, 'appendQuery');
        $this->assertEquals($result, $method->invokeArgs($client, [$url, $query]));
    }

    public function appendQueryProvider()
    {
        return [
            [
                'url' => 'https://example.com',
                'query' => 'a=1&b=2',
                'result' => 'https://example.com?a=1&b=2'
            ],
            [
                'url' => 'https://example.com',
                'query' => '?a=1&b=2&',
                'result' => 'https://example.com?a=1&b=2'
            ],
            [
                'url' => 'https://example.com',
                'query' => '?&',
                'result' => 'https://example.com'
            ],
        ];
    }

    // TODO: testBuildQueryString

    /**
     * @dataProvider flattenParamsProvider
     */
    public function testFlattenParams($params, $flatParams)
    {
        $client = m::mock(Client::class);
        $method = $this->getMethod(Client::class, 'flattenParams');
        $this->assertEquals($flatParams, $method->invokeArgs($client, [$params]));
    }

    public function flattenParamsProvider()
    {
        return [
            [
                'params' => [ 'a' => 1, 'b' => 2, 'c' => 3, ],
                'flatParams' => [ 'a' => 1, 'b' => 2, 'c' => 3, ],
            ],
            [
                'params' => [ 'a' => 1, 'd' => [ 'b' => 2, 'c' => 3 ]
                ],
                'flatParams' => [ 'a' => 1, 'b' => 2, 'c' => 3, ],
            ],
            [
                'params' => [ 'a' => 1, 'd' => [ 'b' => 2, 'e' => [ 'c' => 3 ] ] ],
                'flatParams' => [ 'a' => 1, 'b' => 2, 'c' => 3, ],
            ]
        ];
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

    // TODO: testEnableSso

    // TODO: testIsSsoEnabled
}
