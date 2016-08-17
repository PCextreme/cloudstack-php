<?php

namespace PCextreme\Cloudstack\Exception;

use Exception;

class ClientException extends Exception
{
    /**
     * @var mixed
     */
    protected $response;

    /**
     * @param  string        $message
     * @param  int           $code
     * @param  array|string  $response
     * @return void
     */
    public function __construct($message, $code, $response)
    {
        $this->response = $response;

        parent::__construct($message, $code);
    }

    /**
     * Returns the exception's response body.
     *
     * @return array|string
     */
    public function getResponseBody()
    {
        return $this->response;
    }
}
