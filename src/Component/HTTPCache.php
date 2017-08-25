<?php

namespace Nanoframework\Component;

use Nanoframework\Component\Core;
use Psr\SimpleCache\CacheInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class HTTPCache
{
    protected $cache;
    protected $cacheKey;

    protected $core;

    /**
     * @var ServerRequestInterface
     */
    protected $request;
    /**
     * @var ResponseInterface
     */
    protected $response;

    public function __construct(Core $core, CacheInterface $cache)
    {
        $this->core = $core;
        $this->cache = $cache;
    }

    /**
     * @return ServerRequestInterface
     */
    public function getRequest()
    {
        return $this->core->getRequest();
    }

    /**
     * @param ServerRequestInterface $request
     */
    public function setRequest($request)
    {
        $this->core->setRequest($request);
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->core->getResponse();
    }

    /**
     * @param ResponseInterface $response
     */
    public function setResponse($response)
    {
        $this->core->setResponse($response);
    }

    protected function getCacheKey()
    {
        if (empty($this->cacheKey)) {
            $this->cacheKey = md5(
                'cache_'
                . $this->getRequest()->getMethod()
                . $this->getRequest()->getUri()->getHost()
                . $this->getRequest()->getUri()->getPath()
                . $this->getRequest()->getUri()->getQuery()
            );
        }
        return $this->cacheKey;
    }

    public function execute()
    {
        $cacheKey = $this->getCacheKey();
        $cacheValue = $this->cache->get($cacheKey, false);
        if ($cacheValue === false) {
            $cacheValue = [];
            $this->core->execute();
            $ttl = $this->core->getCacheTTL();
            if ($ttl > 0) {
                $cacheValue['out'] = (string) $this->getResponse()->getBody();
                $cacheValue['headers'] = $this->getResponse()->getHeaders();
                $this->cache->set($cacheKey, $cacheValue, $ttl);
            }
        } else {
            foreach ($cacheValue['headers'] as $headerName => $headerValues) {
                if ($headerName == 'ETag') {
                    $this->setStatusIfNoneMatch(reset($headerValues));
                }
                foreach ($headerValues as $value) {
                    $this->setResponse(
                        $this->getResponse()->withAddedHeader($headerName, $value)
                    );
                }
            }
            $this->getResponse()->getBody()->write($cacheValue['out']);
        }
    }

    public function setStatusIfNoneMatch($value)
    {
        $this->core->setStatusIfNoneMatch($value);
    }

    public function respond()
    {
        $this->core->respond();
    }
}
