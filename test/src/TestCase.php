<?php

namespace PCextreme\Cloudstack\Test;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function getMethod($class, $name)
    {
        $class = new \ReflectionClass($class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}
