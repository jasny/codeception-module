<?php

namespace Jasny\Codeception;

use Jasny\Codeception\Connector;
use Jasny\Router;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;
use Jasny\HttpMessage\OutputBufferStream;
use Psr\Http\Message\ResponseInterface;
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
    
    public function testSetResponse()
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
    public function testSetResponseWithStale()
    {
        $connector = new Connector();
        
        $response = $this->createMock(Response::class);
        $response->method('isStale')->willReturn(true);
        
        $connector->setBaseResponse($response);
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
     * @param ServerRequest $request
     * @return boolean
     */
    public function assertPsrGetRequest($request)
    {
        $this->assertInstanceOf(ServerRequest::class, $request);
        
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('http://www.example.com/foo?bar=1&color=blue&mood=sunny', (string)$request->getUri());
        
        $this->assertEquals(['bar' => 1, 'color' => 'blue', 'mood' => 'sunny'], $request->getQueryParams());
        $this->assertEmpty($request->getParsedBody());
        
        $this->assertEquals([
            'Referer' => ['http://www.example.com'],
            'User-Agent' => ['Test/1.0'],
            'Host' => ['www.example.com']
        ], $request->getHeaders());
        
        return true;
    }
    
    /**
     * @param ServerRequest $request
     * @return boolean
     */
    public function assertPsrPostRequest($request)
    {
        $this->assertInstanceOf(ServerRequest::class, $request);
        
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('http://www.example.com/foo?bar=1', (string)$request->getUri());
        
        $this->assertEquals(['bar' => 1], $request->getQueryParams());
        $this->assertEquals(['color' => 'blue', 'mood' => 'sunny'], $request->getParsedBody());
        
        $uploadedFiles = $request->getUploadedFiles();
        $this->assertArrayHasKey('file', $uploadedFiles);
        $this->assertEquals('one.txt', $uploadedFiles['file']->getClientFilename());
        $this->assertEquals('text/plain', $uploadedFiles['file']->getClientMediaType());
        $this->assertEquals('File Uno', (string)$uploadedFiles['file']->getStream());
        
        $this->assertEquals([
            'Referer' => ['http://www.example.com'],
            'User-Agent' => ['Test/1.0'],
            'Host' => ['www.example.com']
        ], $request->getHeaders());
        
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
        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->expects($this->once())->method('getStatusCode')->willReturn(200);
        $psrResponse->expects($this->once())->method('getBody')->willReturn('hello body');
        $psrResponse->expects($this->once())->method('getHeaders')->willReturn([
            'Content-Type' => 'text/plain',
            'Custom-Header' => 'abc'
        ]);
        
        $router = $this->createMock(Router::class);
        $router->expects($this->once())->method('__invoke')->with(
            $this->callback([$this, "assertPsr{$method}Request"]),
            $this->isInstanceOf(Response::class)
        )->willReturn($psrResponse);
        
        $connector = new Connector();
        $connector->setRouter($router);
        
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
        
        $response = $connector->getResponse();
        
        $this->assertInstanceOf(BrowserKitResponse::class, $response);
        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('hello body', $response->getContent());
        $this->assertEquals([
            'Content-Type' => 'text/plain',
            'Custom-Header' => 'abc'
        ], $response->getHeaders());
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
