<?php

namespace Jasny\Codeception;

use Jasny\Codeception\Connector;
use Jasny\Router;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;
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
}
