<?php

return [
    'index'  =>  [
        'rule'      => '/user/{id:\d+}',
        'controller'=> 'Test',
        'action'    => 'Test'
    ],

    '_error404' =>  [
        'title'     => 'Ошибка 404',
        'controller'=> 'Error',
        'action'    => 'NotFound',
    ],

    '_errordb' =>  [
        'title'     => 'Cтраница временно недоступна',
        'controller'=> 'Error',
        'action'    => 'ErrorDB',
    ],

    '_errordeny' => [
        'title'     => 'Доступ к странице запрещен',
        'controller'=> 'Error',
        'action'    => 'ErrorAccessDenied',
    ],
];