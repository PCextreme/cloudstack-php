<?php

namespace PCextreme\Cloudstack\Test;

use PCextreme\Cloudstack\AbstractClient;
use PCextreme\Cloudstack\RequestFactory;
use PCextreme\Cloudstack\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\ClientInterface;

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

        $mockProvider = new MockClient(compact('timeout'));

        $config = $mockProvider->getHttpClient()->getConfig();

        $this->assertContains('timeout', $config);
        $this->assertEquals($timeout, $config['timeout']);
    }

    public function testConstructorSetsHttpAdapter()
    {
        $mockAdapter = m::mock(ClientInterface::class);

        $mockProvider = new MockClient([], ['httpClient' => $mockAdapter]);
        $this->assertSame($mockAdapter, $mockProvider->getHttpClient());
    }

    public function testConstructorSetsRequestFactory()
    {
        $mockAdapter = m::mock(RequestFactory::class);

        $mockProvider = new MockClient([], ['requestFactory' => $mockAdapter]);
        $this->assertSame($mockAdapter, $mockProvider->getRequestFactory());
    }

    public function testCanSetAProxy()
    {
        $proxy = '192.168.0.1:8888';

        $mockProvider = new MockClient(['proxy' => $proxy]);

        $config = $mockProvider->getHttpClient()->getConfig();

        $this->assertContains('proxy', $config);
        $this->assertEquals($proxy, $config['proxy']);
    }

    public function testCannotDisableVerifyIfNoProxy()
    {
        $mockProvider = new MockClient(['verify' => false]);

        $config = $mockProvider->getHttpClient()->getConfig();

        $this->assertContains('verify', $config);
        $this->assertTrue($config['verify']);
    }

    public function testCanDisableVerificationIfThereIsAProxy()
    {
        $mockProvider = new MockClient(['proxy' => '192.168.0.1:8888', 'verify' => false]);

        $config = $mockProvider->getHttpClient()->getConfig();

        $this->assertContains('verify', $config);
        $this->assertFalse($config['verify']);
    }
}
