<?php

namespace Nanoframework\Component;

use FastRoute;

/**
 * Class Route
 * @package Nanoframework\Component
 */
class Route
{

    /**
     * @var FastRoute\Dispatcher
     */
    protected $dispatcher;

    /**
     *
     */
    const CACHE_FILE_NAME = 'route.cache';

    /**
     * Route constructor.
     * @param array $routes
     * @param null $cachePath
     * @param bool $cacheDisabled
     */
    public function __construct(array $routes, $cachePath = null, $cacheDisabled = false)
    {
        if (empty($cachePath)) {
            $dispatcher = 'FastRoute\simpleDispatcher';
            $options = null;
        } else {
            $dispatcher = 'FastRoute\cachedDispatcher';
            $options = [
                'cacheDisabled' => $cacheDisabled,
                'cacheFile' => $cachePath . '/' . self::CACHE_FILE_NAME,
            ];
        }
        $this->dispatcher = $dispatcher(
            function (FastRoute\RouteCollector $r) use ($routes) {
                foreach ($routes as $route) {
                    if (!empty($route['rule'])) {
                        $r->addRoute(
                            isset($route['method']) ? $route['method'] : 'GET',
                            $route['rule'],
                            [
                                'controller' => $route['controller'],
                                'action' => empty($route['action']) ? 'Index' : $route['action'],
                            ]
                        );
                    }
                }
            },
            $options
        );
    }

    /**
     * @param $method
     * @param $URIPath
     * @return array
     */
    public function get($URIPath, $method = 'GET')
    {
        $vars = [];
        $routeInfo = $this->dispatcher->dispatch($method, $URIPath);
        switch ($routeInfo[0]) {
            case FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                // ... call $handler with $vars
                $controller = $handler['controller'];
                $action = $handler['action'];
                break;
            case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $vars = $routeInfo[1];
                // ... 405 Method Not Allowed
                $controller = 'Error';
                $action = 'MethodNotAllowed';
                break;
            default:
            case FastRoute\Dispatcher::NOT_FOUND:
                // ... 404 Not Found
                $controller = 'Error';
                $action = 'NotFound';
                break;
        }
        return [
            'controller' => $controller,
            'action'     => $action,
            'variables'  => $vars,
        ];
    }
}
