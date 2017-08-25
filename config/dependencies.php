<?php

return [
    'PDO' => [
        'shared'    => true,
        'construct' => ['mysql:host=127.0.0.1;dbname=new', 'root', ''],
        'call' => [
            ['setAttribute', [\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION]]
        ]
    ],
    'Cache' => [
        'shared'     => true,
        'instanceOf' => '\Symfony\Component\Cache\Simple\PdoCache',
        'construct'  => [['instance' => 'PDO'], $this->get('siteCode')]
    ],
    'NullCache' => [
        'shared'     => true,
        'instanceOf' => '\Symfony\Component\Cache\Simple\NullCache',
    ],
    'StreamHandler' => [
        'instanceOf' => '\Monolog\Handler\StreamHandler',
        'construct'  => [$this->get('logPath') . '/' . 'nano.log', $this->get('debug') ? \Monolog\Logger::DEBUG : \Monolog\Logger::WARNING]
    ],
    'Logger' => [
        'instanceOf' => '\Monolog\Logger',
        'construct'  => [$this->get('siteCode')],
        'shared'     => true,
        'call'       => [
            ['pushHandler', [['instance' => 'StreamHandler']]]
        ]
    ],
    'Request' => [
        'instanceOf' => '\GuzzleHttp\Psr7\ServerRequest',
        'static'     => 'fromGlobals',
        'shared'     => true,
    ],
    'Response' => [
        'instanceOf' => '\GuzzleHttp\Psr7\Response',
        'shared'     => true,
    ],
    'Route' => [
        'instanceOf' => '\Nanoframework\Component\Route',
        'shared' => true,
        'construct' => [$this->get('route'), $this->get('cachePath'), $this->get('debug')]
    ]
];