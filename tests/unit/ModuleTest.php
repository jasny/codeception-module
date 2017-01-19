<?php

use Jasny\Codeception\Module;
use Jasny\Codeception\Connector;
use Jasny\Router;
use Mouf\Picotainer\Picotainer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit_Framework_MockObject_Matcher_InvokedCount as InvokedCount;
use Codeception\Lib\ModuleContainer;
use Codeception\TestInterface;
use Jasny\TestHelper;

/**
 * @covers Jasny\Codeception\Module
 */
class ModuleTest extends \Codeception\Test\Unit
{
    use TestHelper;
    
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var Module|MockObject
     */
    protected $module;

    /**
     * @var Picotainer|MockObject
     */
    protected $container;

    
    /**
     * Create a module as partial mock
     * 
     * @param array $config
     */
    protected function createModule($config = [])
    {
        $moduleContainer = $this->createMock(ModuleContainer::class);
        
        $config += ['container' => ''];
        
        $this->module = $this->getMockBuilder(Module::class)
            ->setMethods(['loadContainer', 'obStart', 'obGetLevel', 'obEndClean', 'sessionStatus', 'sessionAbort'])
            ->setConstructorArgs([$moduleContainer, $config])
            ->getMock();
        
        $this->container = empty($config['container']) ? $this->createMock(Picotainer::class) : null;
        $this->setPrivateProperty($this->module, 'container', $this->container);
    }

    
    public function _before()
    {
        $this->createModule();
    }
    
    
    public function testGetContainer()
    {
        $this->assertSame($this->container, $this->module->getContainer());
    }
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Container isn't initialized
     */
    public function testGetContainerUninitialized()
    {
        $this->createModule(['container' => 'tests/_data/container.php']);
        
        $this->module->getContainer();
    }
    
    
    public function testInitialize()
    {
        $this->createModule(['container' => 'tests/_data/container.php']);
        
        $container = $this->createMock(Picotainer::class);
        
        $this->module->expects($this->once())->method('loadContainer')
            ->with(codecept_data_dir('container.php'))
            ->willReturn($container);
        
        $this->module->_initialize();
        
        $this->assertSame($container, $this->module->getContainer());
    }
    
    
    /**
     * @param string $uri
     * @return ResponseInterface|MockObject
     */
    protected function createResponseMockWithStream($uri)
    {
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        
        $response->method('getBody')->willReturn($stream);
        $stream->method('getMetadata')->with('uri')->willReturn($uri);
        
        return $response;
    }
    
    public function responseProvider()
    {
        return [
            [null, $this->never()],
            [$this->createResponseMockWithStream('php://temp'), $this->never()],
            [$this->createResponseMockWithStream('php://output'), $this->once()]
        ];
    }
    
    /**
     * @dataProvider responseProvider
     * 
     * @param ResponseInterface|MockObject $response
     * @param InvokedCount                 $invoke
     */
    public function testBeforeSuite($response, $invoke)
    {
        $this->module->expects(clone $invoke)->method('obStart');
        $this->module->expects(clone $invoke)->method('obGetLevel')->willReturn(1);

        $this->container->method('has')->with(ResponseInterface::class)->willReturn(isset($response));
        $this->container->method('get')->with(ResponseInterface::class)->willReturn($response);

        $this->module->_beforeSuite();
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Failed to start output buffering
     */
    public function testBeforeSuiteFailObStart()
    {
        $this->module->expects($this->once())->method('obStart');
        $this->module->expects($this->once())->method('obGetLevel')->willReturn(0);

        $this->container->method('has')->with(ResponseInterface::class)->willReturn(true);
        $this->container->method('get')->with(ResponseInterface::class)
            ->willReturn($this->createResponseMockWithStream('php://output'));

        $this->module->_beforeSuite();
    }
    
    /**
     * @dataProvider responseProvider
     * 
     * @param ResponseInterface|MockObject $response
     * @param InvokedCount                 $invoke
     */
    public function testAfterSuite($response, $invoke)
    {
        $this->module->expects(clone $invoke)->method('obGetLevel')->willReturn(1);
        $this->module->expects(clone $invoke)->method('obEndClean');

        $this->container->method('has')->with(ResponseInterface::class)->willReturn(isset($response));
        $this->container->method('get')->with(ResponseInterface::class)->willReturn($response);

        $this->module->_afterSuite();
    }

    
    public function requestResponseProvider()
    {
        return [
            [null, null],
            [$this->createMock(ServerRequestInterface::class), null],
            [null, $this->createMock(ResponseInterface::class)],
            [$this->createMock(ServerRequestInterface::class), $this->createMock(ResponseInterface::class)]
        ];
    }
    
    /**
     * @dataProvider requestResponseProvider
     * 
     * @param ServerRequestInterface|MockObject $request
     * @param ResponseInterface|MockObject      $response
     */
    public function testBefore($request, $response)
    {
        $test = $this->createMock(TestInterface::class);
        $router = $this->createMock(Router::class);
        
        $this->container->method('has')->willReturnMap([
            [Router::class, true],
            [ServerRequestInterface::class, isset($request)],
            [ResponseInterface::class, isset($response)]
        ]);
        
        $this->container->method('get')->willReturnMap([
            [Router::class, $router],
            [ServerRequestInterface::class, $request],
            [ResponseInterface::class, $response]
        ]);

        $this->module->_before($test);
        
        $this->assertInstanceOf(Connector::class, $this->module->client);
        $this->assertSame($router, $this->module->client->getRouter());
        
        if (isset($request)) {
            $this->assertSame($request, $this->module->client->getBaseRequest());
        }
        
        if (isset($response)) {
            $this->assertSame($response, $this->module->client->getBaseResponse());
        }
    }
    
    public function sessionStatusProvider()
    {
        return [
            [PHP_SESSION_NONE, $this->never()],
            [PHP_SESSION_ACTIVE, $this->once()]
        ];
    }
    
    /**
     * @dataProvider sessionStatusProvider
     * 
     * @param int          $status
     * @param InvokedCount $invoke
     */
    public function testAfterForSessionAbort($status, $invoke)
    {
        $test = $this->createMock(TestInterface::class);
        
        $this->module->expects($this->once())->method('sessionStatus')->willReturn($status);
        $this->module->expects($invoke)->method('sessionAbort');
        
        $this->module->_after($test);
    }
    
    public function testAfterForClientReset()
    {
        $test = $this->createMock(TestInterface::class);
        
        $connector = $this->createMock(Connector::class);
        $connector->expects($this->once())->method('reset');
        
        $this->setPrivateProperty($this->module, 'client', $connector);
        
        $this->module->_after($test);
    }
}
