<?php

namespace Jasny\Codeception\Module;

use Jasny\Router;
use Codeception\Configuration;
use Codeception\Lib\Framework;
use Codeception\TestInterface;
use Jasny\Codeception\Connector\JasnyMVC as Connector;

/**
 * Module for running functional tests using Jasny MVC
 */
class JasnyMVC extends Framework
{
    /**
     * Required configuration fields
     * @var array
     */
    protected $requiredFields = ['router'];
    
    /**
     * @var Router 
     */
    protected $router;
    
    
    /**
     * Initialize the router
     */
    protected function initRouter()
    {
        if (substr($this->config['router'], -2) === '()') {
            $router = call_user_func(substr($this->config['router'], 0, -2));
        } else {
            $router = include Configuration::projectDir() . $this->config['router'];
        }
        
        if (!$router instanceof Router) {
            throw new \UnexpectedValueException("Failed to get router from '{$this->config['router']}'");
        }
        
        $this->router = $router;
    }
    
    /**
     * Initialize the module
     */
    public function _initialize()
    {
        $this->initRouter();
        
        parent::_initialize();
    }
    
    
    /**
     * Before each test
     * 
     * @param TestInterface $test
     */
    public function _before(TestInterface $test)
    {
        $this->client = new Connector();
        $this->client->setRouter($this->router);
        
        parent::_before($test);
    }
    
    /**
     * After each test
     * 
     * @param TestInterface $test
     */
    public function _after(TestInterface $test)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        parent::_after($test);
    }
}
