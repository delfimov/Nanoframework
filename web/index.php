<?php

$sitePath = realpath(__DIR__ . '/..');

include $sitePath . '/vendor/autoload.php';

$config = new Nanoframework\Component\Config($sitePath);

$di = new Nanoframework\Component\DI($config->get('dependencies'));

/**
 * @var $logger \Psr\Log\LoggerInterface
 */
/*$logger = $di->get('Logger');
$logger->info('Start');*/
/**
 * @var $response Psr\Http\Message\RequestInterface;
 */
$request = $di->get('Request');
/**
 * @var $response Psr\Http\Message\ResponseInterface;
 */
$response = $di->get('Response');

$core = new Nanoframework\Component\Core($di->get('Route'), $request, $response, $config, $di);
$core = new Nanoframework\Component\HTTPCache($core, $di->get('Cache'));

$core->execute();

$response = $core->getResponse();
$core->respond();

/*echo '<hr>';

print_r($response->getHeaders());*/
