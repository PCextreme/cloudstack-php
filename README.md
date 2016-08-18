![github-readme_header](https://cloud.githubusercontent.com/assets/2406615/17754363/6e205280-64d4-11e6-946d-e7e7aedb2e30.png)

# PHP Cloudstack Client

This package makes it simple to integrate Cloudstack in your PHP applications.

## Requirements

The following versions of PHP are supported.

* PHP 5.5
* PHP 5.6
* PHP 7.0

## Installation

You can use `composer require` to add the client to your `composer.json` file.

```
$ composer require pcextreme/cloudstack
```

Or modify your `composer.json` and add the client to your `require` block followed by running `composer update`.

```
"require": {
    "pcextreme/cloudstack": "~0.1"
}
```

## Usage

There are various ways to interact with the Cloudstack API using this package. The easiest and preferred way is to call the Cloudstack 'commands' directly as a method on the client.

Internally the client resolves the command using the `__call` magic method. The command will be automatically resolved and the provided options are verified. Also when everything goes as expected the API response is automatically parsed.

The client uses an API list mapping stored in the cache folder do determine if an API command exists and all required parameters are provided. This list is generated using Cloudstack's `listApis` command.

```php
<?php

include(__DIR__.'/vendor/autoload.php');

use PCextreme\Cloudstack\Client;

$client = new Client([
    'urlApi'    => 'https://api.auroracompute.eu/ams',
    'apiKey'    => 'YOUR-API-KEY',
    'secretKey' => 'YOUR-SECRET-KEY',
]);

var_dump($client->listAccounts(['name' => 'admin', 'listall' => 'true']));
```

### Directly calling the command method

Its also possible to bypass the `__call` magic method and call the `command` method directly.

```php
$client->command('listAccounts', ['name' => 'admin', 'listall' => 'true']);
```

### Manually accessing the API

If for some reason the `cache/api_list.php` is removed, outdated or gets corrupted you can access the API directly by building a request manually. This bypasses all previously mentioned checks but still parses the response.

```php
$command = 'listAccounts';
$options = ['name' => 'admin', 'listall' => 'true'];

$method  = $client->getCommandMethod($command);
$url     = $client->getCommandUrl($command, $options);
$request = $client->getRequest($method, $url, $options);

$accounts = $client->getResponse($request);
```

## Updating the API list

You can update or regenerate the provided API list using the `bin/cloudstack` CLI.

```
$ php bin/cloudstack api:list
```

You can also get a list of available CLI commands.

```
$ php bin/cloudstack
```

## Credits

- [Kevin Dierkx](https://github.com/kevindierkx)
- [All Contributors](https://github.com/pcextreme/cloudstack-php/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
