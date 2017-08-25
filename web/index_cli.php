<?php
$sitePath = __DIR__ . '/../';
include $sitePath . '/vendor/autoload.php';
if (php_sapi_name() == 'cli') {
    $request = getopt('r:h:');
    if (!empty($request['h'])) {
        $_SERVER['HTTP_HOST'] = $request['h'];
    }
    $request = $request['r'];
}

$sitePath = realpath(__DIR__ . '/..');

include $sitePath . '/vendor/autoload.php';

$config = new Nanoframework\Component\Config($sitePath);

$di = new Nanoframework\Component\DI($config->get('dependencies'));
/*
$request = $di->get('Request');

$response = $di->get('Response');

$core = new Nanoframework\Component\Core($di->get('Route'), $request, $response, $config, $di);
$core = new Nanoframework\Component\HTTPCache($core, $di->get('Cache'));

$core->execute();

$response = $core->getResponse();
$core->respond();*/