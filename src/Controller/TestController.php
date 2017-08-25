<?php

namespace Nanoframework\Controller;

use Nanoframework\Controller\Controller;

class TestController extends Controller
{
    public function actionTest($var)
    {
        $this->addHeader('Cache-Control', 'max-age', 30);
        $this->addHeader('Cache-Control', 'public');
        return var_export($var, true) . '<br>Action Test';
    }
}
