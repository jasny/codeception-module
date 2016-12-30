<?php

namespace Jasny\Codeception;

use Jasny\Router;
use Codeception\Configuration;
use Codeception\Lib\Framework;
use Codeception\TestInterface;
use Jasny\Codeception\Connector;

/**
 * Module for running functional tests using Jasny MVC
 */
class Module extends Framework
{
    /**
     * Required configuration fields
     * @var array
     */
    protected $requiredFields = ['router'];
    
    /**
     * @var Router 
     */
    public $router;
    
    
    /**
     * Load the router by including the file.
     * @codeCoverageIgnore
     * 
     * @param string $file
     * @return Router
     */
    protected function loadRouter($file)
    {
        return include $file;
    }
    
    /**
     * Initialize the router
     */
    protected function initRouter()
    {
        $router = $this->loadRouter(Configuration::projectDir() . $this->config['router']);
        
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
        $this->client->useGlobalEnvironment(!empty($this->config['global_environment']));
        
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
