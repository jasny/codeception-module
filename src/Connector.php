<?php

namespace Jasny\Codeception;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Jasny\HttpMessage\Response;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\UploadedFile;
use Jasny\HttpMessage\Uri;
use Jasny\HttpMessage\Stream;
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
     * @var false
     */
    protected $useGlobalEnvironment = false;
    
    
    /**
     * Set the router
     * 
     * @param Router $router
     * @param string $legacy
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
     * Get or set the global environment flag
     * 
     * @param boolean $enable
     */
    public function useGlobalEnvironment($enable = null)
    {
        if (isset($enable)) {
            $this->useGlobalEnvironment = $enable;
        }
        
        return $this->useGlobalEnvironment;
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
        parse_str($uri->getQuery(), $queryParams);
        
        if ($request->getMethod() === 'GET') {
            $queryParams = array_merge($queryParams, $request->getParameters());
            $uri = $uri->withQuery(http_build_query($queryParams));
        }
        
        return [$uri, $queryParams];
    }
    
    /**
     * Create a PSR-7 Server request object.
     * 
     * @return ServerRequest
     */
    protected function createPsrRequest()
    {
        $psrRequest = new ServerRequest();
        
        if ($this->useGlobalEnvironment) {
            $psrRequest = $psrRequest->withGlobalEnvironment(true);
        }
        
        return $psrRequest;
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
        
        $psrRequest = $this->createPsrRequest()
            ->withServerParams($request->getServer())
            ->withMethod($request->getMethod())
            ->withRequestTarget((string)($uri->withScheme('')->withHost('')->withPort('')->withUserInfo('')))
            ->withCookieParams($request->getCookies())
            ->withUri($uri)
            ->withQueryParams($queryParams)
            ->withBody(new Stream($stream))
            ->withUploadedFiles($this->convertUploadedFiles($request->getFiles()));
        
        if ($request->getMethod() !== 'GET' && $request->getParameters() !== null) {
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
            $psrResponse->getStatusCode(),
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
            throw new \Exception("Router not set");
        }
        
        $psrRequest = $this->convertRequest($request);
        
        $router = $this->getRouter();
        $psrResponse = $router->handle($psrRequest, new Response());
        
        return $this->convertResponse($psrResponse);
    }
}
