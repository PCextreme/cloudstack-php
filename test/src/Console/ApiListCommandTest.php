<?php

namespace PCextreme\Cloudstack\Test\Console;

use PCextreme\Cloudstack\Console\ApiListCommand;
use Mockery as m;

class ApiListCommandTest extends \PHPUnit_Framework_TestCase
{
    public function parseCommandProvider()
    {
        return [
            [
                'command' => [
                    'name'          => 'commandname',
                    'description'   => 'foo foo',
                    'isasync'       => true,
                    'related'       => 'foo,bar',
                    'required'      => false,
                    'params'        => [
                        [
                            'name'        => 'param1',
                            'description' => 'sdljlsdflkdsf',
                            'required'    => true,
                            'type'        => 'string'
                        ]
                    ]
                ],
                'parsedCommand' => [
                    'commandname' => [
                        'description'   => 'foo foo',
                        'isasync'       => true,
                        'params'        => [
                            'param1' => [
                                'description' => 'sdljlsdflkdsf',
                                'required'    => true,
                                'type'        => 'string'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * @dataProvider parseCommandProvider
     */
    public function testParseCommand($command, $parsedCommand)
    {
        $class = new ApiListCommand();

        $method = $this->getMethod(ApiListCommand::class, 'parseCommand');
        $response = $method->invokeArgs($class, [$command]);

        $this->assertEquals($response, $parsedCommand);
    }


    private function getMethod($class, $name)
    {
        $class = new \ReflectionClass($class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}
