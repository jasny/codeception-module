<?php

namespace Jasny\Codeception;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Jasny\HttpMessage\Response;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\UploadedFile;
use Jasny\HttpMessage\Uri;
use Jasny\HttpMessage\Stream;
use Jasny\HttpMessage\OutputBufferStream;
use Jasny\Router;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\BrowserKit\Request as BrowserKitRequest;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;

/**
 * Codeception connector for Jasny\MVC
 */
class Connector extends Client
{
    /**
     * @var Router
     */
    protected $router;
    
    /**
     * Request with the current global environent
     * @var ServerRequestInterface
     */
    protected $baseRequest;
    
    /**
     * Request with the current global environent
     * @var ResponseInterface
     */
    protected $baseResponse;
    
    
    /**
     * Set the router
     * 
     * @param Router $router
     */
    public function setRouter(Router $router)
    {
        $this->router = $router;
    }
    
    /**
     * Get the router
     * 
     * @return Router
     */
    public function getRouter()
    {
        return $this->router;
    }
    
    
    /**
     * Set the base request
     * 
     * @param ServerRequestInterface $request
     */
    public function setBaseRequest(ServerRequestInterface $request)
    {
        if ($request instanceof ServerRequest && $request->isStale()) {
            throw new \RuntimeException("Unable to set base request: ServerRequest is stale");
        }
        
        $this->baseRequest = $request;
    }
    
    /**
     * Get the base request
     * 
     * @return ServerRequestInterface
     */
    public function getBaseRequest()
    {
        if (!isset($this->baseRequest)) {
            $this->baseRequest = new ServerRequest();
        }
        
        return $this->baseRequest;
    }
    
    
    /**
     * Set the base response
     * 
     * @param ResponseInterface $response
     */
    public function setBaseResponse(ResponseInterface $response)
    {
        if ($response instanceof Response && $response->isStale()) {
            throw new \RuntimeException("Unable to set base response: Response is stale");
        }
        
        $this->baseResponse = $response;
    }
    
    /**
     * Get the base response
     * 
     * @return ResponseInterface
     */
    public function getBaseResponse()
    {
        if (!isset($this->baseResponse)) {
            $this->baseResponse = new Response();
        }
        
        return $this->baseResponse;
    }
    
    /**
     * Reset the request and response.
     * This is only required when the request and/or response are bound to the global environment.
     */
    public function reset()
    {
        if (isset($this->baseRequest) && $this->baseRequest instanceof ServerRequest && $this->baseRequest->isStale()) {
            $this->baseRequest = $this->baseRequest->revive();
        }

        if (isset($this->baseResponse) && $this->baseResponse instanceof Response && $this->baseResponse->isStale()) {
            $this->baseResponse = $this->baseResponse->revive();
        }
        
        // Clear output buffer
        if (isset($this->baseResponse) && $this->baseResponse->getBody() instanceof OutputBufferStream) {
            $this->baseResponse = $this->baseResponse->withBody(clone $this->baseResponse->getBody());
        }
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
     * Convert a codeception request to a Jasny PSR-7 server request
     * 
     * @param BrowserKitRequest $request
     * @return ServerRequest
     */
    protected function convertRequest(BrowserKitRequest $request)
    {
        list($uri, $queryParams) = $this->buildFullUri($request);
        
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $request->getContent());
        fseek($stream, 0);
        
        $baseRequest = $this->getBaseRequest();
        
        if ($baseRequest instanceof ServerRequest) {
            $serverParams = $this->determineServerParams($request, $uri, $queryParams);
            $baseRequest = $baseRequest->withServerParams($request->getServer() + $serverParams);
        }
        
        $psrRequest = $baseRequest
            ->withBody(new Stream($stream))
            ->withMethod($request->getMethod())
            ->withRequestTarget((string)($uri->withScheme('')->withHost('')->withPort('')->withUserInfo('')))
            ->withCookieParams($request->getCookies())
            ->withUri($uri)
            ->withQueryParams($queryParams)
            ->withUploadedFiles($this->convertUploadedFiles($request->getFiles()));
        
        if ($request->getMethod() !== 'GET' && !empty($request->getParameters())) {
            $psrRequest = $psrRequest->withParsedBody($request->getParameters());
        }
        
        return $psrRequest;
    }
    
    /**
     * Convert a Jasny PSR-7 response to a codeception response
     * 
     * @param ResponseInterface $psrResponse
     * @return BrowserKitResponse
     */
    protected function convertResponse(ResponseInterface $psrResponse)
    {
        return new BrowserKitResponse(
            (string)$psrResponse->getBody(),
            $psrResponse->getStatusCode() ?: 200,
            $psrResponse->getHeaders()
        );
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
            } elseif (!isset($file['tmp_name']) && !isset($file['name'])) {
                $fileObjects[$fieldName] = $this->convertUploadedFiles($file);
            } else {
                $fileObjects[$fieldName] = new UploadedFile($file);
            }
        }
        
        return $fileObjects;
    }
    
    
    /**
     * Makes a request.
     * 
     * @param BrowserKitRequest $request
     * @return BrowserKitResponse
     */
    protected function doRequest($request)
    {
        if ($this->getRouter() === null) {
            throw new \BadMethodCallException("Router not set");
        }
        
        $this->reset(); // Reset before each HTTP request
        
        $psrRequest = $this->convertRequest($request);
        
        $router = $this->getRouter();
        $psrResponse = $router->handle($psrRequest, $this->getBaseResponse());
        
        return $this->convertResponse($psrResponse);
    }
}
