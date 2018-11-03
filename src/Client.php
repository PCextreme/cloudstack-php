<?php

declare(strict_types=1);

namespace PCextreme\Cloudstack;

use PCextreme\Cloudstack\Exception\ClientException;
use InvalidArgumentException;
use PCextreme\Cloudstack\Util\UrlHelpersTrait;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Cache\Simple\FilesystemCache;

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
     * @var FilesystemCache
     */
    private $cache;

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
    protected function getConfigurableOptions() : array
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
    protected function getRequiredOptions() : array
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
    private function assertRequiredOptions(array $options) : void
    {
        $missing = array_diff_key(array_flip($this->getRequiredOptions()), $options);

        if (!empty($missing)) {
            throw new InvalidArgumentException(
                'Required options not defined: '.implode(', ', array_keys($missing))
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
    private function assertRequiredCommandOptions(string $command, array $options = []) : void
    {
        if (! $this->isCommandValid($command)) {
            throw new RuntimeException(
                "Call to unsupported API command [{$command}], this call is not present in the API list."
            );
        }

        $requiredParameters = $this->getRequiredCommandParameters($command);
        $providedParameters = array_keys($options);

        $missing = array_diff($requiredParameters, $providedParameters);

        if (! empty($missing)) {
            $missing = implode(', ', $missing);

            throw new InvalidArgumentException(
                "Missing arguments [{$missing}] for command [{$command}]."
            );
        }
    }

    /**
     * Check if command is supported
     * @param  string $command
     * @return boolean
     */
    protected function isCommandValid(string $command)
    {
        return array_key_exists($command, $this->getApiList());
    }

    /**
     * Get required parameter names
     * @param  string $command
     * @return array
     */
    protected function getRequiredCommandParameters(string $command)
    {
        $commands = $this->getApiList();
        $parameters = $commands[$command]['params'];

        $required = array_filter($parameters, function ($rules) {
            return (bool) $rules['required'];
        });

        return array_keys($required);
    }

    /**
     * Returns command method based on the command.
     *
     * @param  string $command
     * @return string
     */
    public function getCommandMethod(string $command) : string
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
    public function getCommandQuery(array $params) : string
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
    public function getCommandUrl(string $command, array $options = []) : string
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
    protected function getCommandParameters(string $command, array $options) : array
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
    protected function signCommandParameters(array $params = []) : string
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
        return $query.'&signature='.$signature;
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
    public function getApiList() : array
    {
        if (is_null($this->apiList)) {
            if (! $this->cache()->has('api.list')) {
                throw new RuntimeException(
                    "Cloudstack Client API list not found. This file needs to be generated before using the client."
                );
            }

            $this->apiList = $this->cache()->get('api.list');
        }

        return $this->apiList;
    }

    /**
     * Set Cloudstack Client API list.
     *
     * @param  array $apiList
     * @return void
     */
    public function setApiList(array $apiList) : void
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
    protected function appendQuery(string $url, string $query) : string
    {
        $query = trim($query, '?&');

        if ($query) {
            return $url.'?'.$query;
        }

        return $url;
    }

    /**
     * Build a query string from an array.
     *
     * @param  array $params
     * @return string
     */
    protected function buildQueryString(array $params) : string
    {
        // We need to modify the nested array keys to get them accepted by Cloudstack.
        // For example 'details[0][key]' should resolve to 'details[0].key'.
        array_walk($params, function (&$value, $key) {
            if (is_array($value)) {
                $parsedParams = [];

                foreach ($value as $index => $entry) {
                    $parsedParams[] = [
                        $key.'['.$index.']'.'.key' => $entry['key'],
                        $key.'['.$index.']'.'.value' => $entry['value'],
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
            $value = $key.'='.rawurlencode($value);
        });

        return implode('&', $queryParams);
    }

    /**
     * Flatten query params.
     *
     * @param  array $params
     * @return array
     */
    protected static function flattenParams(array $params) : array
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
    protected function checkResponse(ResponseInterface $response, $data) : void
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
    public function enableSso(bool $enable = true) : self
    {
        $this->ssoEnabled = $enable;

        return $this;
    }
    /**
     * Determine if SSO signing is enabled.
     *
     * @return boolean
     */
    public function isSsoEnabled() : bool
    {
        return $this->ssoEnabled;
    }

    /**
     * Get cache driver instance
     * @return FilesystemCache
     */
    private function cache() : FilesystemCache
    {
        if (! isset($this->cache)) {
            $this->cache = new FilesystemCache('', 0, __DIR__.'/../cache');
        }

        return $this->cache;
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  mixed $method
     * @param  array $parameters
     * @return mixed
     */
    public function __call($method, array $parameters)
    {
        array_unshift($parameters, $method);

        return call_user_func_array(array($this, 'command'), $parameters);
    }
}
