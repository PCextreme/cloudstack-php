<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use PCextreme\Cloudstack\Client;
use GuzzleHttp\Client as HttpClient;
use PCextreme\Cloudstack\RequestFactory;
use GuzzleHttp\Psr7\Response;
use Mockery as m;


/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    const CLIENT_CONFIG = [
        'urlApi' => 'https://example.com/client/api',
        'apiKey' => 'mock_api_key',
        'secretKey' => 'mock_secret_key',
    ];

    private $params;

    private $client;

    private $method;

    private $cloudstackResponseCode;

    private $cloudstackResponseBody;

    private $result;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
    }

    /**
     * @Given I have no parameters
     */
    public function iHaveNoParameters()
    {
        $this->params = [];
    }

    /**
     * @Given There is a client instance
     */
    public function thereIsAClientInstance()
    {
        $client = m::mock(Client::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Configure client instance, since we don't want to execute the constructor (it's a mock..)
        $this->setProtectedProperty($client, 'urlApi', self::CLIENT_CONFIG['urlApi']);
        $this->setProtectedProperty($client, 'apiKey', self::CLIENT_CONFIG['apiKey']);
        $this->setProtectedProperty($client, 'secretKey', self::CLIENT_CONFIG['secretKey']);
        $this->setProtectedProperty($client, 'requestFactory', new RequestFactory());
        $this->setProtectedProperty($client, 'httpClient', new HttpClient(self::CLIENT_CONFIG));

        $this->client = $client;
    }

    /**
     * @Given The expected HTTP method is :method
     */
    public function theExpectedHTTPMethodIs($method)
    {
        $this->expectedMethod = $method;
    }

    /**
     * @Given The cloudstack response code is :code
     */
    public function theCloudstackResponseCodeIs($code)
    {
        $this->cloudstackResponseCode = $code;
    }

    /**
     * @Given The cloudstack response body is:
     */
    public function theCloudstackResponseBodyIs(PyStringNode $string)
    {
        $this->cloudstackResponseBody = $string;
    }

    /**
     * @When I execute the :command command
     */
    public function iExecuteTheCommand($command)
    {
        $response = new Response($this->cloudstackResponseCode, [], $this->cloudstackResponseBody);

        $this->client->shouldReceive('sendRequest')->withArgs(function ($request) {
            return $request->getMethod() === $this->expectedMethod;
        })->andReturn($response);

        $this->result = $this->client->command($command, $this->params);
    }

    /**
     * @Then The client should return:
     */
    public function theClientShouldReturn(PyStringNode $string)
    {
        $expectedResult = json_decode((string) $string, true);

        if ($expectedResult !== $this->result) {
            throw new \UnexpectedValueException('Failed asserting that '.json_encode($expectedResult).' equals '.json_encode($this->result));
        }
    }

    private function setProtectedProperty($instance, $name, $value)
    {
        $ref = new \ReflectionProperty(get_class($instance), $name);
        $ref->setAccessible(true);
        $ref->setValue($instance, $value);
    }
}
