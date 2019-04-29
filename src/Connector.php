<?php

namespace Jasny\Codeception;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Jasny\HttpMessage\Response;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\GlobalEnvironmentInterface;
use Jasny\HttpMessage\OutputBufferStream;
use Jasny\RouterInterface;
use Jasny\Codeception\RequestConvertor;
use Jasny\Codeception\ResponseConvertor;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\BrowserKit\Request as BrowserKitRequest;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;

/**
 * Codeception connector for Jasny\MVC
 */
class Connector extends Client
{
    /**
     * @var RouterInterface
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
     * @var RequestConvertor
     */
    protected $requestConvertor;
    
    /**
     * @var ResponseConvertor
     */
    protected $responseConvertor;
    
    
    /**
     * Set the router
     *
     * @param RouterInterface $router
     */
    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
    }
    
    /**
     * Get the router
     *
     * @return RouterInterface
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
        if ($request instanceof GlobalEnvironmentInterface && $request->isStale()) {
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
        
        $request = $this->baseRequest;
        
        if ($request instanceof GlobalEnvironmentInterface && $request->isStale() === false) {
            $request = $request->withGlobalEnvironment(true); // Make sure base request is stale
        }
        
        return $request;
    }
    
    
    /**
     * Set the base response
     *
     * @param ResponseInterface $response
     */
    public function setBaseResponse(ResponseInterface $response)
    {
        if ($response instanceof GlobalEnvironmentInterface && $response->isStale()) {
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
        
        $response = $this->baseResponse;
        
        if ($response instanceof GlobalEnvironmentInterface && $response->isStale() === false) {
            $response = $response->withGlobalEnvironment(true); // Make sure base response is stale
        }
        
        return $response;
    }
    

    /**
     * Reset the request
     */
    protected function resetInput()
    {
        if ($this->baseRequest instanceof GlobalEnvironmentInterface) {
            $this->baseRequest = $this->baseRequest->revive();
        }
    }

    /**
     * Reset the response
     */
    protected function resetOutput()
    {
        if ($this->baseResponse instanceof GlobalEnvironmentInterface) {
            $this->baseResponse = $this->baseResponse->revive();
        }
        
        // Clear output buffer
        if (isset($this->baseResponse) && $this->baseResponse->getBody() instanceof OutputBufferStream) {
            $this->baseResponse = $this->baseResponse->withBody(clone $this->baseResponse->getBody());
        }
    }
    
    /**
     * Reset the request and response.
     * This is only required when the request and/or response are bound to the global environment.
     */
    public function reset()
    {
        $this->resetInput();
        $this->resetOutput();
    }

    
    /**
     * Set the request convertor
     *
     * @param RequestConvertor $convertor
     */
    public function setRequestConvertor(RequestConvertor $convertor)
    {
        $this->requestConvertor = $convertor;
    }
    
    /**
     * Get the request convertor
     *
     * @return RequestConvertor
     */
    public function getRequestConvertor()
    {
        if (!isset($this->requestConvertor)) {
            $this->requestConvertor = new RequestConvertor();
        }
        
        return $this->requestConvertor;
    }
    
    
    /**
     * Set the response convertor
     *
     * @param ResponseConvertor $convertor
     */
    public function setResponseConvertor(ResponseConvertor $convertor)
    {
        $this->responseConvertor = $convertor;
    }
    
    /**
     * Get the response convertor
     *
     * @return ResponseConvertor
     */
    public function getResponseConvertor()
    {
        if (!isset($this->responseConvertor)) {
            $this->responseConvertor = new ResponseConvertor();
        }
        
        return $this->responseConvertor;
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
        
        $psrRequest = $this->getRequestConvertor()->convert($request, $this->getBaseRequest());
        
        $router = $this->getRouter();
        $psrResponse = $router->handle($psrRequest, $this->getBaseResponse());
        
        return $this->getResponseConvertor()->convert($psrResponse);
    }
}
