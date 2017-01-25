<?php

use Jasny\Codeception\Module;
use Jasny\Codeception\Connector;
use Jasny\RouterInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Interop\Container\ContainerInterface;
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
     * Create a module as partial mock
     * 
     * @param array $config
     */
    protected function createModule($config = [])
    {
        $moduleContainer = $this->createMock(ModuleContainer::class);
        
        $config += ['container' => ''];
        
        $this->module = $this->getMockBuilder(Module::class)
            ->setMethods(['loadContainer', 'obStart', 'obGetLevel', 'obClean', 'sessionStatus', 'sessionAbort'])
            ->setConstructorArgs([$moduleContainer, $config])
            ->getMock();
    }

    
    public function _before()
    {
        $this->createModule();
    }
    
    
    public function testInitialize()
    {
        $this->createModule(['container' => 'tests/_data/container.php']);
        
        $container = $this->createMock(ContainerInterface::class);
        
        $this->module->expects($this->once())->method('loadContainer')
            ->with(codecept_data_dir('container.php'))
            ->willReturn($container);
        
        $this->module->_initialize();
        
        $this->assertSame($container, $this->module->container);
    }
    
    public function testInitializeWithRequestResponse()
    {
        $this->createModule(['container' => 'tests/_data/container.php']);
        
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->any())->method('has')->willReturn(true);
        $container->expects($this->exactly(2))->method('get')->willReturnMap([
            [ServerRequestInterface::class, $request],
            [ResponseInterface::class, $response]
        ]);
        
        $this->module->expects($this->once())->method('loadContainer')
            ->with(codecept_data_dir('container.php'))
            ->willReturn($container);
        
        $this->module->_initialize();
        
        $this->assertSame($container, $this->module->container);
        $this->assertSame($request, $this->module->baseRequest);
        $this->assertSame($response, $this->module->baseResponse);
    }
    
    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage Failed to get a container
     */
    public function testInitializeWithInvalidContainer()
    {
        $this->createModule(['container' => 'tests/_data/container.php']);
        
        $this->module->expects($this->once())->method('loadContainer')
            ->with(codecept_data_dir('container.php'))
            ->willReturn(true);
        
        $this->module->_initialize();
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
        $this->module->method('obGetLevel')->willReturnOnConsecutiveCalls(0, 1);

        $this->module->baseResponse = $response;

        $this->module->_beforeSuite();
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Failed to start output buffering
     */
    public function testBeforeSuiteFailObStart()
    {
        $this->module->expects($this->once())->method('obStart');
        $this->module->expects($this->exactly(2))->method('obGetLevel')->willReturn(0);

        $this->module->baseResponse = $this->createResponseMockWithStream('php://output');

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
        $this->module->method('obGetLevel')->willReturn(1);
        $this->module->expects(clone $invoke)->method('obClean');

        $this->module->baseResponse = $response;

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
        $router = $this->createMock(RouterInterface::class);
        
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->with(RouterInterface::class)->willReturn($router);
        
        $this->module->container = $container;
        $this->module->baseRequest = $request;
        $this->module->baseResponse = $response;

        $this->module->_before($test);
        
        $this->assertInstanceOf(Connector::class, $this->module->client);
        
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
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $connector = $this->createMock(Connector::class);
        $connector->expects($this->once())->method('reset');
        
        $connector->expects($this->once())->method('getBaseRequest')->willReturn($request);
        $connector->expects($this->once())->method('getBaseResponse')->willReturn($response);
        
        $this->module->client = $connector;
        $this->module->baseRequest = $this->createMock(ServerRequestInterface::class);
        $this->module->baseResponse = $this->createMock(ResponseInterface::class);
        
        $this->module->_after($test);
        
        $this->assertSame($request, $this->module->baseRequest);
        $this->assertSame($response, $this->module->baseResponse);
    }
}
