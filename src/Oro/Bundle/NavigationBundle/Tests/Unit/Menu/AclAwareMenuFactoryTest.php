<?php

namespace Oro\Bundle\NavigationBundle\Tests\Unit\Menu;

use Oro\Bundle\NavigationBundle\Menu\AclAwareMenuFactory;
use Symfony\Component\Routing\RouterInterface;
use Oro\Bundle\UserBundle\Acl\Manager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Doctrine\Common\Cache\CacheProvider;

class AclAwareMenuFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Manager
     */
    protected $aclManager;

    /**
     * @var AclAwareMenuFactory
     */
    protected $factory;

    /**
     * @var CacheProvider
     */
    protected $cache;

    protected function setUp()
    {
        $this->router = $this->getMockBuilder('Symfony\Component\Routing\RouterInterface')
            ->getMock();
        $this->aclManager = $this->getMockBuilder('Oro\Bundle\UserBundle\Acl\Manager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->factory = new AclAwareMenuFactory($this->router, $this->aclManager);
    }

    /**
     * @dataProvider optionsWithResourceIdDataProvider
     * @param array   $options
     * @param boolean $isAllowed
     */
    public function testBuildOptionsWithResourceId($options, $isAllowed)
    {
        $this->aclManager->expects($this->once())
            ->method('isResourceGranted')
            ->with($options['aclResourceId'])
            ->will($this->returnValue($isAllowed));

        $item = $this->factory->createItem('test', $options);
        $this->assertInstanceOf('Knp\Menu\MenuItem', $item);
        $this->assertEquals($isAllowed, $item->getExtra('isAllowed'));
    }

    /**
     * @return array
     */
    public function optionsWithResourceIdDataProvider()
    {
        return array(
            'allowed' => array(
                array('aclResourceId' => 'test'), true
            ),
            'not allowed' => array(
                array('aclResourceId' => 'test'), false
            ),
            'allowed with uri' => array(
                array('aclResourceId' => 'test', 'uri' => '#'), true
            ),
            'not allowed with uri' => array(
                array('aclResourceId' => 'test', 'uri' => '#'), false
            ),
            'allowed with route' => array(
                array('aclResourceId' => 'test', 'route' => 'test'), true
            ),
            'not allowed with route' => array(
                array('aclResourceId' => 'test', 'route' => 'test'), false
            ),
            'allowed with route and uri' => array(
                array('aclResourceId' => 'test', 'uri' => '#', 'route' => 'test'), true
            ),
            'not allowed with route and uri' => array(
                array('aclResourceId' => 'test', 'uri' => '#', 'route' => 'test'), false
            ),
        );
    }

    public function testBuildOptionsWithRouteNotFound()
    {
        $options = array('route' => 'no-route');

        $routeCollection = $this->getMockBuilder('Symfony\Component\Routing\RouteCollection')
            ->getMock();

        $routeCollection->expects($this->once())
            ->method('get')
            ->with('no-route')
            ->will($this->returnValue(null));

        $this->router->expects($this->once())
            ->method('getRouteCollection')
            ->will($this->returnValue($routeCollection));

        $this->aclManager->expects($this->never())
            ->method('isClassMethodGranted');

        $item = $this->factory->createItem('test', $options);
        $this->assertInstanceOf('Knp\Menu\MenuItem', $item);
        $this->assertEquals(AclAwareMenuFactory::DEFAULT_ACL_POLICY, $item->getExtra('isAllowed'));
    }

    public function testBuildOptionsWithUnknownUri()
    {
        $options = array('uri' => '#');

        $this->router->expects($this->once())
            ->method('match')
            ->will($this->throwException(new ResourceNotFoundException('Route not found')));

        $this->aclManager->expects($this->never())
            ->method('isClassMethodGranted');

        $item = $this->factory->createItem('test', $options);
        $this->assertInstanceOf('Knp\Menu\MenuItem', $item);
        $this->assertEquals(AclAwareMenuFactory::DEFAULT_ACL_POLICY, $item->getExtra('isAllowed'));
    }

    /**
     * @dataProvider optionsWithRouteDataProvider
     * @param array   $options
     * @param boolean $isAllowed
     */
    public function testBuildOptionsWithRoute($options, $isAllowed)
    {
        $this->assertRouteByRouteNameCalls($isAllowed, $options['route'], 'controller', 'action', 1);

        $item = $this->factory->createItem('test', $options);
        $this->assertInstanceOf('Knp\Menu\MenuItem', $item);
        $this->assertEquals($isAllowed, $item->getExtra('isAllowed'));
    }

    /**
     * Assert ACL and route calls are present when route option is present.
     *
     * @param boolean $isAllowed
     * @param string  $routeName
     * @param string  $class
     * @param string  $method
     * @param int     $callsCount
     */
    protected function assertRouteByRouteNameCalls($isAllowed, $routeName, $class, $method, $callsCount)
    {
        $route = $this->getMockBuilder('Symfony\Component\Routing\Route')
            ->disableOriginalConstructor()
            ->getMock();

        if ($callsCount > 0) {
            $route->expects($this->exactly($callsCount))
                ->method('getDefault')
                ->with('_controller')
                ->will($this->returnValue($class . '::' . $method));
        } else {
            $route->expects($this->never())
                ->method('getDefault');
        }

        $routeCollection = $this->getMockBuilder('Symfony\Component\Routing\RouteCollection')
            ->getMock();

        if ($callsCount > 0) {
            $routeCollection->expects($this->exactly($callsCount))
                ->method('get')
                ->with($routeName)
                ->will($this->returnValue($route));
        } else {
            $routeCollection->expects($this->never())
                ->method('get');
        }

        $this->router->expects($this->exactly($callsCount))
            ->method('getRouteCollection')
            ->will($this->returnValue($routeCollection));

        $this->aclManager->expects($this->once())
            ->method('isClassMethodGranted')
            ->with('controller', 'action')
            ->will($this->returnValue($isAllowed));
    }

    /**
     * @return array
     */
    public function optionsWithRouteDataProvider()
    {
        return array(
            'allowed with route' => array(
                array('route' => 'test'), true
            ),
            'not allowed with route' => array(
                array('route' => 'test'), false
            ),
            'allowed with route and uri' => array(
                array('uri' => '#', 'route' => 'test'), true
            ),
            'not allowed with route and uri' => array(
                array('uri' => '#', 'route' => 'test'), false
            ),
        );
    }

    /**
     * @dataProvider optionsWithUriDataProvider
     * @param array   $options
     * @param boolean $isAllowed
     */
    public function testBuildOptionsWithUri($options, $isAllowed)
    {
        $class = 'controller';
        $method = 'action';

        $this->router->expects($this->once())
            ->method('match')
            ->will($this->returnValue(array('_controller' => $class . '::' . $method)));

        $this->aclManager->expects($this->once())
            ->method('isClassMethodGranted')
            ->with($class, $method)
            ->will($this->returnValue($isAllowed));

        $item = $this->factory->createItem('test', $options);
        $this->assertInstanceOf('Knp\Menu\MenuItem', $item);
        $this->assertEquals($isAllowed, $item->getExtra('isAllowed'));
    }

    /**
     * @return array
     */
    public function optionsWithUriDataProvider()
    {
        return array(
            'allowed with route and uri' => array(
                array('uri' => '/test'), true
            ),
            'not allowed with route and uri' => array(
                array('uri' => '/test'), false
            ),
        );
    }

    public function testAclCacheByResourceId()
    {
        $options = array('aclResourceId' => 'resource_id');
        $this->aclManager->expects($this->once())
            ->method('isResourceGranted')
            ->with($options['aclResourceId'])
            ->will($this->returnValue(true));

        for ($i = 0; $i < 2; $i++) {
            $item = $this->factory->createItem('test', $options);
            $this->assertTrue($item->getExtra('isAllowed'));
            $this->assertInstanceOf('Knp\Menu\MenuItem', $item);
        }

        $this->assertAttributeCount(1, 'aclCache', $this->factory);
        $this->assertAttributeEquals(array($options['aclResourceId'] => true), 'aclCache', $this->factory);
    }

    public function testAclCacheByKey()
    {
        $options = array('route' => 'route_name');

        $this->assertRouteByRouteNameCalls(true, 'route_name', 'controller', 'action', 2);

        for ($i = 0; $i < 2; $i++) {
            $item = $this->factory->createItem('test', $options);
            $this->assertTrue($item->getExtra('isAllowed'));
            $this->assertInstanceOf('Knp\Menu\MenuItem', $item);
        }

        $this->assertAttributeCount(1, 'aclCache', $this->factory);
        $this->assertAttributeEquals(array('controller::action' => true), 'aclCache', $this->factory);
    }

    /**
     * @dataProvider hasInCacheDataProvider
     * @param boolean $hasInCache
     */
    public function testUriCaching($hasInCache)
    {
        $cacheKey = md5('uri_acl:#');

        $cache = $this->getMockBuilder('Doctrine\Common\Cache\ArrayCache')
            ->getMock();

        $cache->expects($this->once())
            ->method('contains')
            ->with($cacheKey)
            ->will($this->returnValue($hasInCache));

        if ($hasInCache) {
            $cache->expects($this->once())
                ->method('fetch')
                ->with($cacheKey)
                ->will($this->returnValue('controller::action'));
        } else {
            $cache->expects($this->once())
                ->method('save')
                ->with($cacheKey, 'controller::action');
        }

        $this->factory->setCache($cache);

        $options = array('uri' => '#');

        if ($hasInCache) {
            $this->router->expects($this->never())
                ->method('match');
        } else {
            $this->router->expects($this->once())
                ->method('match')
                ->will($this->returnValue(array('_controller' => 'controller::action')));
        }

        $this->aclManager->expects($this->once())
            ->method('isClassMethodGranted')
            ->with('controller', 'action')
            ->will($this->returnValue(true));

        $item = $this->factory->createItem('test', $options);
        $this->assertTrue($item->getExtra('isAllowed'));
        $this->assertInstanceOf('Knp\Menu\MenuItem', $item);

    }

    /**
     * @dataProvider hasInCacheDataProvider
     * @param boolean $hasInCache
     */
    public function testRouteCaching($hasInCache)
    {
        $params = array('id' => 20);
        $uriKey = md5('route_uri:route_name' . serialize($params));
        $aclKey = md5('route_acl:route_name');

        $cache = $this->getMockBuilder('Doctrine\Common\Cache\ArrayCache')
            ->getMock();

        $cache->expects($this->exactly(2))
            ->method('contains')
            ->will(
                $this->returnValueMap(
                    array(
                        array($uriKey, $hasInCache),
                        array($aclKey, $hasInCache),
                    )
                )
            );

        if ($hasInCache) {
            $cache->expects($this->exactly(2))
                ->method('fetch')
                ->will(
                    $this->returnValueMap(
                        array(
                            array($uriKey, '/'),
                            array($aclKey, 'controller::action'),
                        )
                    )
                );
        } else {
            $cache->expects($this->exactly(2))
                ->method('save')
                ->with(
                    $this->logicalOr(
                        $this->equalTo($aclKey),
                        $this->equalTo('controller::action'),
                        $this->equalTo($uriKey),
                        $this->equalTo('/')
                    )
                );
        }

        $this->factory->setCache($cache);

        $options = array('route' => 'route_name', 'routeParameters' => $params);

        $this->assertRouteByRouteNameCalls(true, 'route_name', 'controller', 'action', (int) !$hasInCache);

        $item = $this->factory->createItem('test', $options);
        $this->assertTrue($item->getExtra('isAllowed'));
        $this->assertInstanceOf('Knp\Menu\MenuItem', $item);

    }

    /**
     * @return array
     */
    public function hasInCacheDataProvider()
    {
        return array(
            'in cache' => array(true),
            'not in cache' => array(false)
        );
    }
}