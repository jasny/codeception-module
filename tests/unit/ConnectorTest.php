<?php

namespace Jasny\Codeception;

use Jasny\Codeception\Connector;
use Jasny\Codeception\RequestConvertor;
use Jasny\Codeception\ResponseConvertor;
use Jasny\Router;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;
use Jasny\HttpMessage\OutputBufferStream;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\BrowserKit\Request as BrowserKitRequest;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;
use org\bovigo\vfs\vfsStream;

/**
 * @covers Jasny\Codeception\Connector
 */
class ConnectorTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory 
     */
    protected $root;
    
    
    protected function _before()
    {
        $this->root = vfsStream::setup('root', null, [
            'one.txt' => 'File Uno'
        ]);
    }
    
    
    public function testSetRouter()
    {
        $connector = new Connector();
        $router = $this->createMock(Router::class);
        
        $connector->setRouter($router);
        
        $this->assertSame($router, $connector->getRouter());
    }
    
    public function testGetBaseRequest()
    {
        $connector = new Connector();
        $request = $connector->getBaseRequest();
        
        $this->assertInstanceOf(ServerRequest::class, $request);
    }
    
    public function testSetBaseRequest()
    {
        $connector = new Connector();
        $request = $this->createMock(ServerRequest::class);
        
        $connector->setBaseRequest($request);
        
        $this->assertSame($request, $connector->getBaseRequest());
    }
    
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Unable to set base request: ServerRequest is stale
     */
    public function testSetBaseRequestWithStale()
    {
        $connector = new Connector();
        
        $request = $this->createMock(ServerRequest::class);
        $request->method('isStale')->willReturn(true);
        
        $connector->setBaseRequest($request);
        
        $this->assertSame($request, $connector->getBaseRequest());
    }
    
    public function testGetBaseResponse()
    {
        $connector = new Connector();
        $request = $connector->getBaseResponse();
        
        $this->assertInstanceOf(Response::class, $request);
    }
    
    public function testSetBaseResponse()
    {
        $connector = new Connector();
        $response = $this->createMock(Response::class);
        
        $connector->setBaseResponse($response);
        
        $this->assertSame($response, $connector->getBaseResponse());
    }
    
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Unable to set base response: Response is stale
     */
    public function testSetBaseResponseWithStale()
    {
        $connector = new Connector();
        
        $response = $this->createMock(Response::class);
        $response->method('isStale')->willReturn(true);
        
        $connector->setBaseResponse($response);
    }
    
    
    public function testSetRequestConvertor()
    {
        $connector = new Connector();
        $convertor = $this->createMock(RequestConvertor::class);
        
        $connector->setRequestConvertor($convertor);
        
        $this->assertSame($convertor, $connector->getRequestConvertor());
    }
    
    public function testGetRequestConvertor()
    {
        $connector = new Connector();
        $convertor = $connector->getRequestConvertor();
        
        $this->assertInstanceOf(RequestConvertor::class, $convertor);
    }
    
    public function testSetResponseConvertor()
    {
        $connector = new Connector();
        $convertor = $this->createMock(ResponseConvertor::class);
        
        $connector->setResponseConvertor($convertor);
        
        $this->assertSame($convertor, $connector->getResponseConvertor());
    }
    
    public function testGetResponseConvertor()
    {
        $connector = new Connector();
        $convertor = $connector->getResponseConvertor();
        
        $this->assertInstanceOf(ResponseConvertor::class, $convertor);
    }
    
    
    public function testReset()
    {
        $connector = new Connector();
        
        $revivedRequest = $this->createMock(ServerRequest::class);
        $request = $this->createMock(ServerRequest::class);
        $request->expects($this->exactly(2))->method('isStale')->willReturnOnConsecutiveCalls(false, true);
        $request->expects($this->once())->method('revive')->willReturn($revivedRequest);
        
        $revivedResponse = $this->createMock(Response::class);
        $response = $this->createMock(Response::class);
        $response->expects($this->exactly(2))->method('isStale')->willReturnOnConsecutiveCalls(false, true);
        $response->expects($this->once())->method('revive')->willReturn($revivedResponse);
        
        $newResponse = $this->createMock(Response::class);
        $body = $this->createMock(OutputBufferStream::class);
        $revivedResponse->method('getBody')->willReturn($body);
        $revivedResponse->expects($this->once())->method('withBody')->with(
            $this->callback(function ($newBody) use ($body) {
                $this->assertInstanceOf(get_class($body), $newBody);
                $this->assertNotSame($body, $newBody);
                return true;
            })
        )->willReturn($newResponse);
        
        $connector->setBaseRequest($request);
        $connector->setBaseResponse($response);
        
        $connector->reset();
        
        $this->assertSame($revivedRequest, $connector->getBaseRequest());
        $this->assertSame($newResponse, $connector->getBaseResponse());
    }
    
    
    /**
     * @param BrowserKitRequest $request
     * @param string            $method
     * @return boolean
     */
    public function assertRequest($request, $method)
    {
        $this->assertInstanceOf(BrowserKitRequest::class, $request);
        
        $this->assertEquals($method, $request->getMethod());
        $this->assertEquals('http://www.example.com/foo?bar=1', $request->getUri());
        
        $this->assertEquals(['color' => 'blue', 'mood' => 'sunny'], $request->getParameters());
        
        if ($method === 'POST') {
            $uploadedFiles = $request->getFiles();
            $this->assertArrayHasKey('file', $uploadedFiles);
            $this->assertEquals(UPLOAD_ERR_OK, $uploadedFiles['file']['error']);
            $this->assertEquals('one.txt', $uploadedFiles['file']['name']);
            $this->assertEquals('text/plain', $uploadedFiles['file']['type']);
            $this->assertEquals('File Uno', file_get_contents($uploadedFiles['file']['tmp_name']));
        }
        
        $this->assertEquals([
            'HTTP_REFERER' => 'http://www.example.com',
            'HTTP_USER_AGENT' => 'Test/1.0',
            'HTTP_HOST' => 'www.example.com',
            'HTTPS' => false
        ], $request->getServer());
        
        return true;
    }
    
    public function requestProvider()
    {
        return [
            ['GET'],
            ['POST']
        ];
    }
    
    /**
     * @dataProvider requestProvider
     * 
     * @param string $method
     */
    public function testRequest($method)
    {
        $baseRequest = $this->createMock(ServerRequestInterface::class);
        $baseResponse = $this->createMock(ResponseInterface::class);

        $psrRequest = $this->createMock(ServerRequestInterface::class);
        $psrResponse = $this->createMock(ResponseInterface::class);
        
        $response = $this->createMock(BrowserKitResponse::class);
        $response->method('getHeader')->willReturnMap([
            ['Content-Type', true, 'text/plain'],
            ['Set-Cookie', false, []]
        ]);
        
        $router = $this->createMock(Router::class);
        $router->expects($this->once())->method('__invoke')
            ->with($this->identicalTo($psrRequest), $this->identicalTo($baseResponse))
            ->willReturn($psrResponse);
        
        $requestConvertor = $this->createMock(RequestConvertor::class);
        $requestConvertor->expects($this->once())->method('convert')
            ->with($this->callback(function ($request) use ($method) {
                return $this->assertRequest($request, $method);
            }, $this->identicalTo($baseRequest)))
            ->willReturn($psrRequest);
        
        $responseConvertor = $this->createMock(ResponseConvertor::class);
        $responseConvertor->expects($this->once())->method('convert')
            ->with($this->identicalTo($psrResponse))
            ->willReturn($response);
        
        $connector = new Connector();
        $connector->setRouter($router);
        $connector->setBaseRequest($baseRequest);
        $connector->setBaseResponse($baseResponse);
        $connector->setRequestConvertor($requestConvertor);
        $connector->setResponseConvertor($responseConvertor);
        
        $uri = 'http://www.example.com/foo?bar=1';
        $parameters = ['color' => 'blue', 'mood' => 'sunny'];
        $files = $method === 'POST' ? [
            'file' => [
                'name' => 'one.txt',
                'type' => 'text/plain',
                'error' => UPLOAD_ERR_OK,
                'size' => filesize(vfsStream::url('root/one.txt')),
                'tmp_name' => vfsStream::url('root/one.txt'),
            ]
        ] : [];
        $server = [
            'HTTP_REFERER' => 'http://www.example.com',
            'HTTP_USER_AGENT' => 'Test/1.0'
        ];
        
        $connector->request($method, $uri, $parameters, $files, $server);
        
        $ret = $connector->getResponse();
        
        $this->assertSame($response, $ret);
    }
    
    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Router not set
     */
    public function testRequestWithoutRouter()
    {
        $connector = new Connector();
        $connector->request('GET', '/foo');
    }   
}
