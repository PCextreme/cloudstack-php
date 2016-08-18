<?php

namespace PCextreme\Cloudstack\Test;

use PCextreme\Cloudstack\AbstractClient;
use PCextreme\Cloudstack\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;

class MockClient extends AbstractClient
{
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (isset(reset($data)['errortext'])) {
            throw new ClientException(reset($data)['errortext'], reset($data)['errorcode'], $data);
        }
    }
}
