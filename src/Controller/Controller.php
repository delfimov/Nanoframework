<?php

namespace Nanoframework\Controller;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Container\ContainerInterface;

class Controller
{

    protected $config;
    protected $di;
    protected $headers = [];

    protected $cacheTTL = 0;

    /**
     * @var ResponseInterface
     */
    protected $response;

    public function __construct(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ContainerInterface $config,
        ContainerInterface $di
    ) {
        $this->config = $config;
        $this->di = $di;
        $this->response = $response;
    }

    public function beforeAction($action)
    {
        return true;
    }

    public function action($action, $variables)
    {
        $actionName = 'action' . $action;
        $this->getResponse()->getBody()->write(
            call_user_func_array(
                [
                    $this,
                    $actionName,
                ],
                $variables
            )
        );
    }

    public function afterAction($action)
    {
        return true;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setHeader($name, $value, $subvalue = null)
    {
        $value = $this->processHeader($name, $value, $subvalue);
        $this->response = $this->response->withHeader($name, $value);
    }

    public function addHeader($name, $value, $subvalue = null)
    {
        $value = $this->processHeader($name, $value, $subvalue);
        $this->response = $this->response->withAddedHeader($name, $value);
    }

    protected function processHeader($name, $value, $subvalue = null)
    {
        switch (strtolower($name)) {
            case 'cache-control':
                $checkValue = strtolower($value);
                if (!empty($subvalue) && ($checkValue == 'max-age' || $checkValue == 's-maxage')) {
                    $this->setCacheTTL($subvalue);
                }
                break;
            case 'expires':
                $ttl = strtotime($value) - time();
                $this->setCacheTTL($ttl);
                break;
            default:
                break;
        }

        return empty($subvalue) ? $value : $value . '=' . $subvalue;
    }

    public function setStatusCode($code, $message)
    {
        $this->response = $this->response->withStatus($code, $message);
    }

    public function setCacheTTL($time)
    {
        $this->cacheTTL = (int) $time;
    }

    public function getCacheTTL()
    {
        return $this->cacheTTL;
    }
}
