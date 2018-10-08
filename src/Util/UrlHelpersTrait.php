<?php namespace PCextreme\Cloudstack\Util;

use InvalidArgumentException;
use PCextreme\Cloudstack\Exception\ClientException;

trait UrlHelpersTrait
{
    /**
     * Generate Client URL for specified username.
     *
     * @param  string $username
     * @param  string $domainId
     * @return string
     * @throws InvalidArgumentException
     */
    public function clientUrl(string $username, string $domainId)
    {
        if (is_null($this->urlClient)) {
            throw new InvalidArgumentException(
                'Required options not defined: urlClient'
            );
        }

        // Prepare session.
        // Using the SSO (Single Sign On) key we can generate a sessionkey used for the console url.
        $command = 'login';
        $params = [
            'command'   => $command,
            'username'  => $username,
            'domainid'  => $domainId,
            'timestamp' => round(microtime(true) * 1000),
            'response'  => 'json',
        ];

        $method  = $this->getCommandMethod($command);
        $query   = $this->enableSso()->getCommandQuery($params);

        return $this->urlClient . '?loginUrl=' . urlencode($query);
    }

    /**
     * Generate Console URL for specified username owning the virtual machine.
     *
     * @param  string $username
     * @param  string $domainId
     * @param  string $virtualMachineId
     * @return string
     * @throws InvalidArgumentException
     */
    public function consoleUrl(string $username, string $domainId, string $virtualMachineId)
    {
        if (is_null($this->urlConsole)) {
            throw new InvalidArgumentException(
                'Required options not defined: urlConsole'
            );
        }

        // Prepare session.
        // Using the SSO (Single Sign On) key we can generate a sessionkey used for the console url.
        $command = 'login';
        $params = [
            'command'   => $command,
            'username'  => $username,
            'domainid'  => $domainId,
            'timestamp' => round(microtime(true) * 1000),
            'response'  => 'json',
        ];

        $base    = $this->urlApi;
        $method  = $this->getCommandMethod($command);
        $query   = $this->enableSso()->getCommandQuery($params);
        $url     = $this->appendQuery($base, $query);
        $request = $this->getRequest($method, $url);

        $login = $this->getResponse($request);

        // Prepare a signed request for the Console url.
        // Effectively this will be the console url, it won't be requested at the Cloudstack API.
        $params = [
            'cmd'        => 'access',
            'vm'         => $virtualMachineId,
            'userid'     => $login['loginresponse']['userid'],
            'sessionkey' => $login['loginresponse']['sessionkey'],
            'timestamp'  => round(microtime(true) * 1000),
            'apikey'     => $this->apiKey,
        ];

        $base = $this->urlConsole;
        $query  = $this->getCommandQuery($params);

        return $this->appendQuery($base, $query);
    }
}
