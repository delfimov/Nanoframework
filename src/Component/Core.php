<?php

namespace Nanoframework\Component;

use \Psr\Container\ContainerInterface;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Nanoframework\Component\Route;

class Core
{
    /**
     * @var $controller \Nanoframework\Component\Route
     */
    protected $route;
    protected $request;
    protected $response;
    protected $config;
    protected $di;

    /**
     * @var $controller \Nanoframework\Controller\Controller
     */
    protected $controller;

    public function __construct(
        Route $route,
        ServerRequestInterface $request,
        ResponseInterface $response,
        ContainerInterface $config,
        ContainerInterface $di
    ) {
        $this->route = $route;
        $this->request = $request;
        $this->response  = $response;
        $this->config = $config;
        $this->di = $di;
    }

    /**
     * @return ServerRequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param ServerRequestInterface $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param ResponseInterface $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    public function execute()
    {
        $call = $this->route->get(
            $this->request->getUri()->getPath(),
            $this->request->getMethod()
        );
        $controllerName = $this->config->get('siteCode') . '\\Controller\\' . $call['controller'] . 'Controller';
        $this->controller = new $controllerName(
            $this->getRequest(),
            $this->getResponse(),
            $this->config,
            $this->di
        );
        $this->controller->beforeAction($call['action']);
        $this->controller->action($call['action'], $call['variables']);
        $this->controller->afterAction($call['action']);
        $this->setResponse($this->controller->getResponse());
    }

    public function getCacheTTL()
    {
        return isset($this->controller) ? $this->controller->getCacheTTL() : 0;
    }


    public function setStatusIfNoneMatch($value)
    {
        if ($this->getRequest()->hasHeader('HTTP_IF_NONE_MATCH')
            && ($this->getRequest()->getHeader('HTTP_IF_NONE_MATCH') == $value
                || 'W/' . $this->getRequest()->getHeader('HTTP_IF_NONE_MATCH') == $value)
        ) {
            $this->setResponse(
                $this->getResponse()->withStatus(304)
            );
        }
    }

    public function respond()
    {
        $body = (string) $this->getResponse()->getBody();
        $etag = md5($body);
        $this->setResponse($this->getResponse()->withHeader('ETag', $etag));
        $this->setStatusIfNoneMatch($etag);
        header(sprintf(
            'HTTP/%s %s %s',
            $this->getResponse()->getProtocolVersion(),
            $this->getResponse()->getStatusCode(),
            $this->getResponse()->getReasonPhrase()
        ));
        foreach ($this->getResponse()->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
        echo $body;
    }
}
