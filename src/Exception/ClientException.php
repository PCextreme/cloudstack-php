<?php

declare(strict_types=1);

namespace PCextreme\Cloudstack\Exception;

use Exception;

class ClientException extends Exception
{
    /**
     * @var mixed
     */
    protected $response;

    /**
     * @param  string       $message
     * @param  integer      $code
     * @param  array|string $response
     * @return void
     */
    public function __construct(string $message, int $code, $response)
    {
        $this->response = $response;

        parent::__construct($message, $code);
    }

    /**
     * Returns the exception's response body.
     * @TODO: Refactor this method, so that it returns a single type.
     *
     * @return array|string
     */
    public function getResponseBody()
    {
        return $this->response;
    }
}
