<?php

namespace Nanoframework\Component;

use \Psr\Container\ContainerInterface;

class Config implements ContainerInterface
{
    /**
     * @var array
     */
    protected $container = [];

    /**
     * Config constructor.
     *
     * @param string $sitePath project root path (ex. /var/www/yorsite.com)
     * @param array  $options  options to override in config file
     */
    public function __construct($sitePath, array $options = [])
    {
        $varPath = $sitePath . '/var';
        $configCachePath = $varPath . '/cache/config.php';
        $configPath = $sitePath . '/config';
        $configCache = @include($configCachePath);
        if (empty($configCache)) {
            $this->container['varPath'] = $varPath;
            $this->container['cachePath'] = $varPath . '/cache';
            $this->container['logPath'] = $varPath . '/logs';
            $this->container['configPath'] = $configPath;
            $this->container['configCachePath'] = $configCachePath;
            $this->container = array_merge($this->container, $this->getConfig($configPath . '/config.php'), $options);
            if (!empty($this->container['load'])) {
                foreach ($this->container['load'] as $key => $file) {
                    $this->container[$key] = $this->getConfig($configPath . '/' . $file);
                }
            }
            $this->saveCache($this->container['configCachePath'], $this->container);
        } else {
            $this->container = $configCache;
        }
    }

    protected function getConfig($path)
    {
        return include $path;
    }


    /**
     * Сохраняеет настройки сайта в виде закешированного файла
     *
     * @param $sitePath string путь к кэшу конфига
     * @param $configCache array контейнер со всеми настройками сайта
     */
    protected function saveCache($sitePath, array $configCache)
    {
        // сохраняем кэш только если мы работаем в среде с выключенным debug
        if (empty($this->get('debug'))) {
            file_put_contents($sitePath, '<?php return ' . var_export($configCache, true) . ';');
        }
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return mixed Entry or null if not defined.
     */
    public function get($id)
    {
        if (isset($this->container[$id])) {
            return $this->container[$id];
        } else {
            return null;
        }
    }

    public function has($id)
    {
        return isset($this->container[$id]);
    }
}
