<?php

namespace PCextreme\Cloudstack\Test\Console;

use PCextreme\Cloudstack\Console\Application;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $name = 'PCextreme Cloudstack client';

    /**
     * @var string
     */
    protected $logo = "   ___  ___          _
  / _ \/ __\_____  _| |_ _ __ ___ _ __ ___   ___
 / /_)/ /  / _ \ \/ / __| '__/ _ \ '_ ` _ \ / _ \
/ ___/ /__|  __/>  <| |_| | |  __/ | | | | |  __/
\/   \____/\___/_/\_\\\__|_|  \___|_| |_| |_|\___|

";

    public function testConstructorSetsName()
    {
        $application = new Application;
        $this->assertEquals(
            $this->name,
            $application->getName()
        );
    }

    public function testConstructorDoesNotSetVersion()
    {
        $application = new Application;
        $this->assertEquals(
            'UNKNOWN',
            $application->getVersion()
        );
    }

    public function testGetHelpStartsWithLogo()
    {
        $application = new Application;
        $this->assertStringStartsWith(
            $this->logo,
            $application->getHelp()
        );
    }

    public function testApiListCommandIsAddedByDefault()
    {
        $application = new Application;
        $this->assertArrayHasKey(
            'api:list',
            $application->all()
        );
    }
}
