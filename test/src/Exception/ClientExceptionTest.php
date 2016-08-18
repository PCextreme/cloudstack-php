<?php

namespace PCextreme\Cloudstack\Test\Exception;

use PCextreme\Cloudstack\Exception\ClientException;

class ClientExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array
     */
    protected $result;

    /**
     * @var ClientException
     */
    protected $exception;

    protected function setUp()
    {
        $this->result = [
            'errortext' => 'message',
            'errorcode' => 404,
        ];

        $this->exception = new ClientException(
            $this->result['errortext'],
            $this->result['errorcode'],
            $this->result
        );
    }

    public function testGetResponseBody()
    {
        $this->assertEquals(
            $this->result,
            $this->exception->getResponseBody()
        );
    }

    public function testGetMessage()
    {
        $this->assertEquals(
            $this->result['errortext'],
            $this->exception->getMessage()
        );
    }

    public function testGetCode()
    {
        $this->assertEquals(
            $this->result['errorcode'],
            $this->exception->getCode()
        );
    }
}
