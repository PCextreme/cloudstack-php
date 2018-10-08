<?php

namespace PCextreme\Cloudstack\Console;

use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    /**
     * @var string
     */
    private static $logo = "   ___  ___          _
  / _ \/ __\_____  _| |_ _ __ ___ _ __ ___   ___
 / /_)/ /  / _ \ \/ / __| '__/ _ \ '_ ` _ \ / _ \
/ ___/ /__|  __/>  <| |_| | |  __/ | | | | |  __/
\/   \____/\___/_/\_\\\__|_|  \___|_| |_| |_|\___|

";

    /**
     * Create a new instance of the application.
     */
    public function __construct()
    {
        parent::__construct('PCextreme Cloudstack client');
    }

    /**
     * Gets the help message.
     *
     * @return string A help message
     */
    public function getHelp()
    {
        return self::$logo . parent::getHelp();
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return array
     */
    protected function getDefaultCommands()
    {
        $commands = array_merge(parent::getDefaultCommands(), [
            new ApiListCommand(),
        ]);

        return $commands;
    }
}
