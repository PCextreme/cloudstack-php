<?php

namespace PCextreme\Cloudstack;

use InvalidArgumentException;
use PCextreme\Cloudstack\Exception\ClientException;
use PCextreme\Cloudstack\Util\UrlHelpersTrait;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class Client extends AbstractClient
{
    use UrlHelpersTrait;

    /**
     * @var array
     */
    protected $apiList;

    /**
     * @var string
     */
    private $urlApi;

    /**
     * @var string
     */
    private $urlClient;

    /**
     * @var string
     */
    private $urlConsole;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string
     */
    protected $secretKey;

    /**
     * @var string
     */
    protected $ssoKey;

    /**
     * @var string
     */
    private $responseError = 'errortext';

    /**
     * @var string
     */
    private $responseCode = 'errorcode';

    /**
     * @var boolean
     */
    private $ssoEnabled = false;

    /**
     * Constructs a new Cloudstack client instance.
     *
     * @param  array $options
     *     An array of options to set on this client. Options include
     *     'apiList', 'urlApi', 'urlClient', 'urlConsole', 'apiKey',
     *     'secretKey', 'responseError' and 'responseCode'.
     * @param  array $collaborators
     *     An array of collaborators that may be used to override
     *     this provider's default behavior. Collaborators include
     *     `requestFactory` and `httpClient`.
     */
    public function __construct(array $options = [], array $collaborators = [])
    {
        $this->assertRequiredOptions($options);

        $possible   = $this->getConfigurableOptions();
        $configured = array_intersect_key($options, array_flip($possible));

        foreach ($configured as $key => $value) {
            $this->$key = $value;
        }

        // Remove all options that are only used locally
        $options = array_diff_key($options, $configured);

        parent::__construct($options, $collaborators);
    }

    /**
     * Returns all options that can be configured.
     *
     * @return array
     */
    protected function getConfigurableOptions()
    {
        return array_merge($this->getRequiredOptions(), [
            'apiList',
            'urlClient',
            'urlConsole',
            'ssoKey',
            'responseError',
            'responseCode',
        ]);
    }

    /**
     * Returns all options that are required.
     *
     * @return array
     */
    protected function getRequiredOptions()
    {
        return [
            'urlApi',
            'apiKey',
            'secretKey',
        ];
    }

    /**
     * Verifies that all required options have been passed.
     *
     * @param  array $options
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertRequiredOptions(array $options)
    {
        $missing = array_diff_key(array_flip($this->getRequiredOptions()), $options);

        if (!empty($missing)) {
            throw new InvalidArgumentException(
                'Required options not defined: ' . implode(', ', array_keys($missing))
            );
        }
    }

    /**
     * Execute command.
     *
     * @param  string $command
     * @param  array  $options
     * @return mixed
     */
    public function command(string $command, array $options = [])
    {
        $this->assertRequiredCommandOptions($command, $options);

        $method  = $this->getCommandMethod($command);
        $url     = $this->getCommandUrl($command, $options);
        $request = $this->getRequest($method, $url, $options);

        return $this->getResponse($request);
    }

    /**
     * Verifies that all required options have been passed.
     *
     * @param string $command
     * @param  array  $options
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private function assertRequiredCommandOptions(string $command, array $options = [])
    {
        $apiList = $this->getApiList();

        if (!array_key_exists($command, $apiList)) {
            throw new RuntimeException(
                "Call to unsupported API command [{$command}], this call is not present in the API list."
            );
        }

        foreach ($apiList[$command]['params'] as $key => $value) {
            if (!array_key_exists($key, $options) && (bool) $value['required']) {
                throw new InvalidArgumentException(
                    "Missing argument [{$key}] for command [{$command}] must be of type [{$value['type']}]."
                );
            }
        }
    }

    /**
     * Returns command method based on the command.
     *
     * @param  string $command
     * @return string
     */
    public function getCommandMethod(string $command)
    {
        if (in_array($command, ['login', 'deployVirtualMachine'])) {
            return self::METHOD_POST;
        }

        return self::METHOD_GET;
    }

    /**
     * Builds the command URL's query string.
     *
     * @param  array $params
     * @return string
     */
    public function getCommandQuery(array $params)
    {
        return $this->signCommandParameters($params);
    }

    /**
     * Builds the authorization URL.
     *
     * @param  string $command
     * @param  array  $options
     * @return string
     */
    public function getCommandUrl(string $command, array $options = [])
    {
        $base   = $this->urlApi;
        $params = $this->getCommandParameters($command, $options);
        $query  = $this->getCommandQuery($params);

        return $this->appendQuery($base, $query);
    }

    /**
     * Returns command parameters based on provided options.
     *
     * @param  string $command
     * @param  array  $options
     * @return array
     */
    protected function getCommandParameters(string $command, array $options)
    {
        return array_merge($options, [
            'command'  => $command,
            'response' => 'json',
            'apikey'   => $this->apiKey,
        ]);
    }

    /**
     * Signs the command parameters.
     *
     * @param  array $params
     * @return string
     */
    protected function signCommandParameters(array $params = [])
    {
        if ($this->isSsoEnabled() && is_null($this->ssoKey)) {
            throw new InvalidArgumentException(
                'Required options not defined: ssoKey'
            );
        }

        ksort($params);

        $query = $this->buildQueryString($params);

        $key = $this->isSsoEnabled() ? $this->ssoKey : $this->secretKey;
        $signature = rawurlencode(base64_encode(hash_hmac(
            'SHA1',
            strtolower($query),
            $key,
            true
        )));

        // Reset SSO signing for the next request.
        $this->ssoEnabled = false;

        // To prevent the signature from being escaped we simply append
        // the signature to the previously build query.
        return $query . '&signature=' . $signature;
    }

    /**
     * Get Cloudstack Client API list.
     *
     * Tries to load the API list from the cache directory when
     * the 'apiList' on the class is empty.
     *
     * @return array
     * @throws RuntimeException
     */
    public function getApiList()
    {
        if (is_null($this->apiList)) {
            $path = __DIR__ . '/../cache/api_list.php';

            if (!file_exists($path)) {
                throw new RuntimeException(
                    "Cloudstack Client API list not found. This file needs to be generated before using the client."
                );
            }

            $this->apiList = require $path;
        }

        return $this->apiList;
    }

    /**
     * Set Cloudstack Client API list.
     *
     * @param  array $apiList
     * @return void
     */
    public function setApiList(array $apiList)
    {
        $this->apiList = $apiList;
    }

    /**
     * Appends a query string to a URL.
     *
     * @param  string $url
     * @param  string $query
     * @return string
     */
    protected function appendQuery(string $url, string $query)
    {
        $query = trim($query, '?&');

        if ($query) {
            return $url . '?' . $query;
        }

        return $url;
    }

    /**
     * Build a query string from an array.
     *
     * @param  array $params
     * @return string
     */
    protected function buildQueryString(array $params)
    {
        // We need to modify the nested array keys to get them accepted by Cloudstack.
        // For example 'details[0][key]' should resolve to 'details[0].key'.
        array_walk($params, function (&$value, $key) {
            if (is_array($value)) {
                $parsedParams = [];

                foreach ($value as $index => $entry) {
                    $parsedParams[] = [
                        $key . '[' . $index . ']' . '.key' => $entry['key'],
                        $key . '[' . $index . ']' . '.value' => $entry['value'],
                    ];
                }

                $value = $parsedParams;
            }
        });

        // Next we flatten the params array and prepare the query params. We need
        // to encode the values, but we can't encode the keys. This would otherwise
        // compromise the signature. Therefore we can't use http_build_query().
        $queryParams = $this->flattenParams($params);
        array_walk($queryParams, function (&$value, $key) {
            $value = $key . '=' . rawurlencode($value);
        });

        return implode('&', $queryParams);
    }

    /**
     * Flatten query params.
     *
     * @param  array $params
     * @return array
     */
    protected static function flattenParams(array $params)
    {
        $result = [];

        foreach ($params as $key => $value) {
            if (!is_array($value)) {
                $result[$key] = $value;
            } else {
                $result = array_merge($result, static::flattenParams($value));
            }
        }

        return $result;
    }

    /**
     * Checks a provider response for errors.
     *
     * @param  ResponseInterface $response
     * @param  array|string      $data
     * @return void
     * @throws ClientException
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        // Cloudstack returns multidimensional responses, keyed with the
        // command name. To handle errors in a generic way we need to 'reset'
        // the data array. To prevent strings from breaking this we ensure we
        // have an array to begin with.
        $data = is_array($data) ? $data : [$data];

        if (isset(reset($data)[$this->responseError])) {
            $error = reset($data)[$this->responseError];
            $code  = $this->responseCode ? reset($data)[$this->responseCode] : 0;

            throw new ClientException($error, $code, $data);
        }
    }

    /**
     * Enable SSO key signing for the next request.
     *
     * @param  boolean $enable
     * @return self
     */
    public function enableSso(bool $enable = true)
    {
        $this->ssoEnabled = $enable;

        return $this;
    }
    /**
     * Determine if SSO signing is enabled.
     *
     * @return boolean
     */
    public function isSsoEnabled()
    {
        return $this->ssoEnabled;
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        array_unshift($parameters, $method);

        return call_user_func_array(array($this, 'command'), $parameters);
    }
}
