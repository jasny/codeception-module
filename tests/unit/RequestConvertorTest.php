<?php

namespace Jasny\Codeception;

use Jasny\Codeception\RequestConvertor;
use Jasny\HttpMessage\ServerRequest;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\BrowserKit\Request as BrowserKitRequest;
use org\bovigo\vfs\vfsStream;

/**
 * @covers Jasny\Codeception\RequestConvertor
 */
class RequestConvertorTest extends \Codeception\TestCase\Test
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
            'one.txt' => 'File Uno',
            'two.txt' => 'File Dos',
            'three.txt' => 'File Tres'
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
        $this->assertEquals('file-one.txt', $uploadedFiles['file']->getClientFilename());
        $this->assertEquals('text/plain', $uploadedFiles['file']->getClientMediaType());
        $this->assertEquals('File Uno', (string)$uploadedFiles['file']->getStream());
        
        $this->assertEquals([
            'Referer' => ['http://www.example.com'],
            'User-Agent' => ['Test/1.0'],
            'Host' => ['www.example.com']
        ], $request->getHeaders());
        
        return true;
    }
    
    
    public function convertProvider()
    {
        return [
            ['GET'],
            ['POST']
        ];
    }
    
    /**
     * @dataProvider convertProvider
     * 
     * @param string $method
     */
    public function testConvert($method)
    {
        $baseRequest = new ServerRequest(); // Mocking would be better, but I'm lazy

        $request = $this->createConfiguredMock(BrowserKitRequest::class, [
            'getUri' => 'http://www.example.com/foo?bar=1',
            'getMethod' => $method,
            'getParameters' => ['color' => 'blue', 'mood' => 'sunny'],
            'getFiles' => $method === 'POST' ? [
                'file' => [
                    'name' => 'file-one.txt',
                    'type' => 'text/plain',
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize(vfsStream::url('root/one.txt')),
                    'tmp_name' => vfsStream::url('root/one.txt'),
                ]
            ] : [],
            'getServer' => [
                'HTTP_REFERER' => 'http://www.example.com',
                'HTTP_USER_AGENT' => 'Test/1.0'
            ],
            'getCookies' => []
        ]);
        
        $convertor = new RequestConvertor();
        
        $psrRequest = $convertor->convert($request, $baseRequest);
        
        $fn = "assertPsr{$method}Request";
        $this->$fn($psrRequest);
    }
    
    /**
     * @depends testConvert
     */
    public function testConvertUploadedFiles()
    {
        $one = [
            'name' => 'file-one.txt',
            'type' => 'text/plain',
            'error' => UPLOAD_ERR_OK,
            'size' => filesize(vfsStream::url('root/one.txt')),
            'tmp_name' => vfsStream::url('root/one.txt')
        ];
        
        $more = [
            [
                'name' => 'file-two.txt',
                'type' => 'text/plain',
                'error' => UPLOAD_ERR_OK,
                'size' => filesize(vfsStream::url('root/two.txt')),
                'tmp_name' => vfsStream::url('root/two.txt')
            ],
            [
                'name' => 'file-three.txt',
                'type' => 'text/plain',
                'error' => UPLOAD_ERR_OK,
                'size' => filesize(vfsStream::url('root/three.txt')),
                'tmp_name' => vfsStream::url('root/three.txt')
            ]
        ];

        $uploadedMock = $this->createMock(UploadedFileInterface::class);
        
        $oops = [
            'error' => UPLOAD_ERR_NO_FILE
        ];

        $request = $this->createConfiguredMock(BrowserKitRequest::class, [
            'getFiles' => [
                'file' => $one,
                'more' => $more,
                'mock' => $uploadedMock,
                'oops' => $oops
            ],
            'getMethod' => 'POST',
            'getParameters' => [],
            'getServer' => [],
            'getCookies' => []
        ]);
        
        $baseRequest = new ServerRequest(); // Mocking would be better, but I'm lazy
        
        $convertor = new RequestConvertor();
        
        $psrRequest = $convertor->convert($request, $baseRequest);
        $uploadedFiles = $psrRequest->getUploadedFiles();
        
        $this->assertIsArray($uploadedFiles);
        $this->assertArrayHasKey('file', $uploadedFiles);
        $this->assertEquals('file-one.txt', $uploadedFiles['file']->getClientFilename());
        $this->assertEquals('text/plain', $uploadedFiles['file']->getClientMediaType());
        $this->assertEquals('File Uno', (string)$uploadedFiles['file']->getStream());

        $this->assertArrayHasKey('more', $uploadedFiles);
        $this->assertIsArray($uploadedFiles['more']);
        $this->assertEquals([0, 1], array_keys($uploadedFiles['more']));
        
        $this->assertEquals('file-two.txt', $uploadedFiles['more'][0]->getClientFilename());
        $this->assertEquals('text/plain', $uploadedFiles['more'][0]->getClientMediaType());
        $this->assertEquals('File Dos', (string)$uploadedFiles['more'][0]->getStream());
        
        $this->assertEquals('file-three.txt', $uploadedFiles['more'][1]->getClientFilename());
        $this->assertEquals('text/plain', $uploadedFiles['more'][1]->getClientMediaType());
        $this->assertEquals('File Tres', (string)$uploadedFiles['more'][1]->getStream());
        
        $this->assertArrayHasKey('mock', $uploadedFiles);
        $this->assertSame($uploadedMock, $uploadedFiles['mock']);
        
        $this->assertArrayHasKey('oops', $uploadedFiles);
        $this->assertEquals(UPLOAD_ERR_NO_FILE, $uploadedFiles['oops']->getError());
    }
    
    public function testConvertHeaders()
    {
        $request = $this->createConfiguredMock(BrowserKitRequest::class, [
            'getUri' => 'http://www.example.com/',
            'getMethod' => 'GET',
            'getParameters' => [],
            'getFiles' => [],
            'getServer' => [
                'FOO' => 'BAR',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_REFERER' => 'http://www.example.com',
                'HTTP_USER_AGENT' => 'Test/1.0',
                'HTTP_NOT_SET' => null
            ],
            'getCookies' => []
        ]);
        
        $baseRequest = $this->createMock(ServerRequestInterface::class);
        $baseRequest->method('withProtocolVersion')->willReturnSelf();
        $baseRequest->method('withBody')->willReturnSelf();
        $baseRequest->method('withMethod')->willReturnSelf();
        $baseRequest->method('withRequestTarget')->willReturnSelf();
        $baseRequest->method('withCookieParams')->willReturnSelf();
        $baseRequest->method('withUri')->willReturnSelf();
        $baseRequest->method('withQueryParams')->willReturnSelf();
        $baseRequest->method('withParsedBody')->willReturnSelf();
        $baseRequest->method('withUploadedFiles')->willReturnSelf();
        
        $baseRequest->expects($this->exactly(3))->method('withHeader')
            ->withConsecutive(
                ['Content-Type', ['application/json']],
                ['Referer', ['http://www.example.com']],
                ['User-Agent', ['Test/1.0']]
            )
            ->willReturnSelf();
            
        $convertor = new RequestConvertor();
        
        $convertor->convert($request, $baseRequest);
    }
}
