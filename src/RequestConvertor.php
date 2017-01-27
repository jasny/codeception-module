<?php

namespace Jasny\Codeception;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\UploadedFile;
use Jasny\HttpMessage\Uri;
use Jasny\HttpMessage\Stream;
use Symfony\Component\BrowserKit\Request as BrowserKitRequest;

/**
 * Convert a codeception request to a Jasny PSR-7 server request
 */
class RequestConvertor
{
    /**
     * Create the output stream handle
     * 
     * @param BrowserKitRequest $request
     * @return Stream
     */
    protected function createStream(BrowserKitRequest $request)
    {
        $stream = fopen('php://temp', 'a+');
        fwrite($stream, $request->getContent());
        
        return new Stream($stream);
    }
    
    /**
     * Build a full URI from a request
     * 
     * @param BrowserKitRequest $request
     * @return array [Uri, queryParams]
     */
    protected function buildFullUri(BrowserKitRequest $request)
    {
        $uri = new Uri($request->getUri());
        
        $queryParams = [];
        parse_str($uri->getQuery(), $queryParams);
        
        if ($request->getMethod() === 'GET') {
            $queryParams = array_merge($queryParams, $request->getParameters());
            $uri = $uri->withQuery(http_build_query($queryParams));
        }
        
        return [$uri, $queryParams];
    }

    /**
     * Set the request headers
     * 
     * @param ServerRequestInterface $baseRequest
     * @param array                  $params
     * @retrun ServerRequestInterface
     */
    protected function setRequestHeaders(ServerRequestInterface $baseRequest, array $params)
    {
        $headers = (new ServerRequest())->withServerParams($params)->getHeaders();
        $psrRequest = $baseRequest;

        foreach ($headers as $header => $values) {
            $psrRequest = $psrRequest->withHeader($header, $values);
        }
        
        return $psrRequest;
    }

    /**
     * Get additional server params from request.
     * @internal It would be nicer if this was solved by Jasny Http Message
     * 
     * @param BrowserKitRequest $request
     * @param UriInterface      $uri
     * @param array             $queryParams
     * @return array
     */
    protected function determineServerParams(BrowserKitRequest $request, UriInterface $uri, array $queryParams)
    {
        return [
            'REQUEST_METHOD' => $request->getMethod(),
            'QUERY_STRING' => http_build_query($queryParams),
            'REQUEST_URI' => (string)($uri->withScheme('')->withHost('')->withPort('')->withUserInfo(''))
        ];
    }
    
    /**
     * Set the server request properties
     * 
     * @param ServerRequestInterface $baseRequest
     * @param BrowserKitRequest      $request
     * @param Stream                 $stream
     * @param UriInterface           $uri
     * @param array                  $queryParams
     * @return ServerRequestInterface
     */
    protected function setRequestProperties(
        ServerRequestInterface $baseRequest,
        BrowserKitRequest $request,
        Stream $stream,
        UriInterface $uri,
        array $queryParams
    ) {
        return $baseRequest
            ->withProtocolVersion('1.1')
            ->withBody($stream)
            ->withMethod($request->getMethod())
            ->withRequestTarget((string)($uri->withScheme('')->withHost('')->withPort('')->withUserInfo('')))
            ->withCookieParams($request->getCookies())
            ->withUri($uri)
            ->withQueryParams($queryParams)
            ->withParsedBody($request->getMethod() !== 'GET' && !empty($request->getParameters())
                ? $request->getParameters() : null)
            ->withUploadedFiles($this->convertUploadedFiles($request->getFiles()));
    }
    
    /**
     * Convert a list of uploaded files to a Jasny PSR-7 uploaded files
     * 
     * @param array $files
     * @return UploadedFile[]|array
     */
    protected function convertUploadedFiles(array $files)
    {
        $fileObjects = [];
        
        foreach ($files as $fieldName => $file) {
            if ($file instanceof UploadedFileInterface) {
                $fileObjects[$fieldName] = $file;
            } elseif (!isset($file['tmp_name']) && !isset($file['error'])) {
                $fileObjects[$fieldName] = $this->convertUploadedFiles($file);
            } else {
                $fileObjects[$fieldName] = new UploadedFile($file);
            }
        }
        
        return $fileObjects;
    }

    
    /**
     * Convert a codeception request to a PSR-7 server request
     * 
     * @param BrowserKitRequest      $request
     * @param ServerRequestInterface $baseRequest
     * @return ServerRequest
     */
    public function convert(BrowserKitRequest $request, ServerRequestInterface $baseRequest)
    {
        $stream = $this->createStream($request);
        list($uri, $queryParams) = $this->buildFullUri($request);
        
        if ($baseRequest instanceof ServerRequest) {
            $serverParams = $this->determineServerParams($request, $uri, (array)$queryParams);
            $psrRequest = $baseRequest->withServerParams($request->getServer() + $serverParams);
        } else {
            $psrRequest = $this->setRequestHeaders($baseRequest, $request->getServer());
        }
        
        return $this->setRequestProperties($psrRequest, $request, $stream, $uri, (array)$queryParams);
    }
}

