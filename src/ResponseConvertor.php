<?php

namespace Jasny\Codeception;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\BrowserKit\Response as BrowserKitResponse;

/**
 * Convert a PSR-7 response to a codeception response
 */
class ResponseConvertor
{
    /**
     * Convert a PSR-7 response to a codeception response
     *
     * @param ResponseInterface $psrResponse
     * @return BrowserKitResponse
     */
    public function convert(ResponseInterface $psrResponse)
    {
        return new BrowserKitResponse(
            (string)$psrResponse->getBody(),
            $psrResponse->getStatusCode() ?: 200,
            $this->flattenHeaders($psrResponse->getHeaders())
        );
    }
    
    /**
     * Flatten headers
     *
     * @param array $headers
     * @return array
     */
    protected function flattenHeaders(array $headers)
    {
        foreach ($headers as &$value) {
            $value = is_array($value) ? end($value) : $value;
        }
        
        return $headers;
    }
}
