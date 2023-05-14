# Simple cache
This repository contains the [PHP FIG PSR-16] Simple cache implementation.

## Install
Via Composer
Package is available on [Packagist], you can install it using [Composer].
``` bash
$ composer require vulpes/cache
```

## Default usage
```php
<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$predisCache = new Cache\PredisCache(
    parameters: [
        'tcp://127.0.0.1:5380?timeout=0.100',
        'tcp://127.0.0.1:5381?timeout=0.100',
        'tcp://127.0.0.1:5382?timeout=0.100',
    ],
    options: [
        'replication' => 'sentinel',
        'service' => 'master',
    ],
    defaultTtl: new DateInterval('P1D'),
    prefix: 'cache-prefix:'
);

$predisCache = new Cache\PredisCache(
    parameters: new Predis\Client('redis://default:redispw@localhost:32768'),
    defaultTtl: null,
    prefix: 'cache-prefix:'
);

$nullCache = new Cache\NullCache(
    returnOnSet: false,
    returnOnDelete: false,
    returnOnClear: false,
    returnOnHas: false
);

$sessionHandler = new Cache\SessionHandler(
    cache: $predisCache,
    ttl: intval(ini_get('session.gc_maxlifetime')),
    prefix: 'session:'
);
$sessionHandler->register();

if (php_sapi_name() === 'cli') {
    session_id('cli-session-id');
}

session_start();

if (!$predisCache->has('id') && !array_key_exists('id', $_SESSION)) {
    $predisCache->set('id', 13);
    print 'step 1: set cache value: 13' . PHP_EOL;
}
else if (!array_key_exists('id', $_SESSION)) {
    $_SESSION['id'] = $predisCache->get('id');
    print 'step 2: set session value: ' . $predisCache->get('id') . PHP_EOL;
}
else if ($predisCache->has('id')) {
    $predisCache->delete('id');
    print 'step 3: delete cache value' . PHP_EOL;
}
else {
    print 'step 4: delete session value: ' . $_SESSION['id'] . PHP_EOL;
    session_destroy();
}
```
[PHP FIG PSR-16]: https://www.php-fig.org/psr/psr-16/
[Packagist]: http://packagist.org/packages/vulpes/cache
[Composer]: http://getcomposer.org
