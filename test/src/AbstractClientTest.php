<?php

namespace PCextreme\Cloudstack\Test;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use PCextreme\Cloudstack\AbstractClient;
use PCextreme\Cloudstack\Exception\ClientException;
use PCextreme\Cloudstack\RequestFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

use Mockery as m;

class AbstractClientTest extends \PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
        parent::tearDown();
    }

    public function testConstructorSetsClientOptions()
    {
        $timeout = rand(100, 900);

        $client = new MockClient(compact('timeout'));

        $config = $client->getHttpClient()->getConfig();

        $this->assertContains('timeout', $config);
        $this->assertEquals($timeout, $config['timeout']);
    }

    public function testConstructorSetsHttpAdapter()
    {
        $mockAdapter = m::mock(ClientInterface::class);

        $client = new MockClient([], ['httpClient' => $mockAdapter]);
        $this->assertSame($mockAdapter, $client->getHttpClient());
    }

    public function testConstructorSetsRequestFactory()
    {
        $mockAdapter = m::mock(RequestFactory::class);

        $client = new MockClient([], ['requestFactory' => $mockAdapter]);
        $this->assertSame($mockAdapter, $client->getRequestFactory());
    }

    public function testCanSetAProxy()
    {
        $proxy = '192.168.0.1:8888';

        $client = new MockClient(['proxy' => $proxy]);

        $config = $client->getHttpClient()->getConfig();

        $this->assertContains('proxy', $config);
        $this->assertEquals($proxy, $config['proxy']);
    }

    public function testCannotDisableVerifyIfNoProxy()
    {
        $client = new MockClient(['verify' => false]);

        $config = $client->getHttpClient()->getConfig();

        $this->assertContains('verify', $config);
        $this->assertTrue($config['verify']);
    }

    public function testCanDisableVerificationIfThereIsAProxy()
    {
        $client = new MockClient(['proxy' => '192.168.0.1:8888', 'verify' => false]);

        $config = $client->getHttpClient()->getConfig();

        $this->assertContains('verify', $config);
        $this->assertFalse($config['verify']);
    }

    public function testGetRequest()
    {
        $mockAdapter = m::mock(RequestFactory::class);
        $mockAdapter->shouldReceive('getRequestWithOptions')->andReturn([]);

        $client = new MockClient([], ['requestFactory' => $mockAdapter]);

        $method = $this->getMethod(MockClient::class, 'getRequest');
        $request = $method->invokeArgs($client, ['GET', 'mock-url', []]);

        $this->assertSame([], $request);
    }

    public function testSendRequestSuccess()
    {
        $mockRequest = m::mock(RequestInterface::class);
        $mockResponse = m::mock(ResponseInterface::class);

        $mockAdapter = m::mock(HttpClient::class);
        $mockAdapter->shouldReceive('send')->andReturn($mockResponse);

        $client = new MockClient([], ['httpClient' => $mockAdapter]);

        $method = $this->getMethod(MockClient::class, 'sendRequest');
        $response = $method->invokeArgs($client, [$mockRequest]);

        $this->assertSame($mockResponse, $response);
    }

    public function testSendRequestException()
    {
        $mockRequest = m::mock(RequestInterface::class);
        $mockResponse = m::mock(ResponseInterface::class);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(500);
        $mockException = m::mock(BadResponseException::class, ['msg', $mockRequest, $mockResponse]);
        $mockException->shouldReceive('getResponse')->andReturn($mockResponse);

        $mockAdapter = m::mock(HttpClient::class);
        $mockAdapter->shouldReceive('send')->andThrow($mockException);

        $client = new MockClient([], ['httpClient' => $mockAdapter]);

        $method = $this->getMethod(MockClient::class, 'sendRequest');
        $response = $method->invokeArgs($client, [$mockRequest]);

        $this->assertSame($mockResponse, $response);
    }

    public function testGetResponse()
    {
        $mockRequest = m::mock(RequestInterface::class);
        $mockResponse = m::mock(ResponseInterface::class);

        $client = m::mock(MockClient::class.'[sendRequest,parseResponse,checkResponse]');
        $client->shouldAllowMockingProtectedMethods();
        $client->shouldReceive('sendRequest')->with($mockRequest)->andReturn($mockResponse);
        $client->shouldReceive('parseResponse')->with($mockResponse)->andReturn('Response');
        $client->shouldReceive('checkResponse')->with($mockResponse, 'Response');

        $parsedResponse = $client->getResponse($mockRequest);
        $this->assertSame('Response', $parsedResponse);
    }

    private function getMethod($class, $name)
    {
        $class = new \ReflectionClass($class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    public function parseResponseProvider()
    {
        return [
            [
                'body'    => '{"a": 1}',
                'type'    => 'application/json',
                'parsed'  => ['a' => 1]
            ],
            [
                'body'    => 'string',
                'type'    => 'unknown',
                'parsed'  => 'string'
            ],
            [
                'body'    => 'a=1&b=2',
                'type'    => 'application/x-www-form-urlencoded',
                'parsed'  => ['a' => 1, 'b' => 2]
            ],
        ];
    }

    /**
     * @dataProvider parseResponseProvider
     */
    public function testParseResponse($body, $type, $parsed)
    {
        $method = $this->getMethod(AbstractClient::class, 'parseResponse');

        $stream = m::mock(StreamInterface::class);
        $stream->shouldReceive('__toString')->times(1)->andReturn($body);

        $response = m::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->andReturn($stream);
        $response->shouldReceive('getHeader')->with('content-type')->andReturn($type);

        $this->assertEquals($parsed, $method->invoke(new MockClient, $response));
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testParseResponseJsonFailure()
    {
        $this->testParseResponse('{a: 1}', 'application/json', null);
    }
}
