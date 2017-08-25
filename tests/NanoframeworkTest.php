<?php
use PHPUnit\Framework\TestCase;
use Nanoframework\Component\Core;
use Nanoframework\Component\DI;
use Nanoframework\Component\HTTPCache;

/**
 * @covers Nanoframework\Component\Core
 */

class NanoframeworkTest extends TestCase
{

    private $URIStore = [
        ['index', '/'],
        ['index', '?b=a&a=b', [], ['b' => 'a', 'a' => 'b']],
        ['super/error404', 'тест'],
        ['super/error404', 'dasdasdsa'],
        ['super/error404', '../../../'],
        ['second', '/second'],
        ['second', '/second/param/pam/pam', ['param', 'pam', 'pam']],
    ];

    /**
     * @param Core $core
     * @param DI $di
     * @dataProvider coreProvider
     */
    public function testCore(Core $core, DI $di)
    {
        $this->assertEquals(true, $core instanceof Core);
    }

    /**
     * @dataProvider coreProvider
     */
    public function testResponse(Core $core, DI $di)
    {
        $core->execute();
        $response = $core->getResponse();
        $this->assertEquals(true, !empty($response));
    }

    /**
     * @param Core $core
     * @param DI $di
     * @dataProvider coreProvider
     */
    public function testHTTPCache(Core $core, DI $di)
    {
        $core = new HTTPCache($core, $di->get('Cache'));
        $this->assertEquals(true, $core instanceof HTTPCache);
    }

    public function coreProvider()
    {
        $sitePath = realpath(__DIR__ . '/..');
        $config = $this->getConfig($sitePath);
        $di = $this->getDI($config);
        $request = $di->get('Request');
        $response = $di->get('Response');
        $core = new Nanoframework\Component\Core($di->get('Route'), $request, $response, $config, $di);
        return [
            [$core, $di]
        ];
    }

    /**
     * @param \Nanoframework\Component\Config $config
     * @return \Nanoframework\Component\DI
     */
    public function getDI($config)
    {
        return new Nanoframework\Component\DI($config->get('dependencies'));
    }

    /**
     * @param string $sitePath
     * @return \Nanoframework\Component\Config
     */
    public function getConfig($sitePath)
    {
        return new Nanoframework\Component\Config($sitePath);
    }
}
