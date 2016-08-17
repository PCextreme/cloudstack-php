# PHP Cloudstack Client

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
