<?php

namespace Jasny\Codeception;

use Jasny\Codeception\ResponseConvertor;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;

/**
 * @covers Jasny\Codeception\ResponseConvertor
 */
class ResponseConvertorTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    public function testConvert()
    {
        $stream = $this->createConfiguredMock(StreamInterface::class, [
            '__toString' => 'hello body'
        ]);
        
        $response = $this->createConfiguredMock(ResponseInterface::class, [
            'getStatusCode' => 200,
            'getBody' => $stream,
            'getHeaders' => [
                'Content-Type' => ['text/plain'],
                'Custom-Header' => ['abc']
            ]
        ]);
        
        $convertor = new ResponseConvertor();
        
        $psrResponse = $convertor->convert($response);
        
        $this->assertInstanceOf(BrowserKitResponse::class, $psrResponse);
        
        $this->assertEquals(200, $psrResponse->getStatus());
        $this->assertEquals('hello body', $psrResponse->getContent());
        $this->assertEquals([
            'Content-Type' => 'text/plain',
            'Custom-Header' => 'abc'
        ], $psrResponse->getHeaders());
    }
}
