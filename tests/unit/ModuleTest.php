<?php

use Jasny\Codeception\Module;
use Jasny\Codeception\Connector;
use Jasny\Router;
use Codeception\Lib\ModuleContainer;
use Codeception\TestInterface;

/**
 * @covers Jasny\Codeception\Module
 */
class ModuleTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function testInitialize()
    {
        $config = ['router' => 'tests/_data/myrouter.php'];
        
        $container = $this->createMock(ModuleContainer::class);
        $router = $this->createMock(Router::class);

        $module = $this->getMockBuilder(Module::class)
            ->setMethods(['loadRouter'])
            ->setConstructorArgs([$container, $config])
            ->getMock();
        
        $module->expects($this->once())->method('loadRouter')
            ->with(codecept_data_dir('myrouter.php'))
            ->willReturn($router);
        
        $module->_initialize();
        
        $this->assertSame($router, $module->router);
    }
    
    public function testBefore()
    {
        $config = ['router' => 'tests/_data/myrouter.php', 'global_environment' => true];
        
        $container = $this->createMock(ModuleContainer::class);
        $router = $this->createMock(Router::class);
        $test = $this->createMock(TestInterface::class);
        
        $module = new Module($container, $config);
        $module->router = $router;
        
        $module->_before($test);
        
        $this->assertInstanceOf(Connector::class, $module->client);
        $this->assertTrue($module->client->useGlobalEnvironment());
    }
}
