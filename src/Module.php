<?php

namespace Jasny\Codeception;

use Jasny\Router;
use Codeception\Configuration;
use Codeception\Lib\Framework;
use Codeception\TestInterface;
use Jasny\Codeception\Connector;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;

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
     * Enable output buffering
     * 
     * @throws \RuntimeException
     */
    protected function startOutputBuffering()
    {
        ob_start();

        if (ob_get_level() < 1) {
            throw new \RuntimeException("Failed to start output buffering");
        }
    }
    
    /**
     * Disable output buffering
     */
    protected function stopOutputBuffering()
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    
    
    /**
     * Initialize the global environment
     */
    protected function initGlobalEnvironment()
    {
        if (!empty($this->config['global_environment'])) {
            $this->client->setBaseRequest((new ServerRequest())->withGlobalEnvironment(true));
            $this->client->setBaseResponse((new Response())->withGlobalEnvironment(true));
        }
    }
    
    /**
     * Reset the global environment to how it was.
     */
    public function resetGlobalEnvironment()
    {
        if (!empty($this->config['global_environment'])) {
            $this->client->reset();
        }
    }
    
    
    /**
     * Call before suite
     * 
     * @param array $settings
     */
    public function _beforeSuite($settings = [])
    {
        parent::_beforeSuite($settings);
        
        if (!empty($this->config['global_environment'])) {
            $this->startOutputBuffering();
        }
    }
    
    /**
     * Call after suite
     */
    public function _afterSuite()
    {
        if (!empty($this->config['global_environment'])) {
            $this->stopOutputBuffering();
        }
        
        parent::_afterSuite();
    }
    
    
    /**
     * Initialize the module
     */
    public function _initialize()
    {
        $this->initRouter();
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

        $this->initGlobalEnvironment();
        
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
            session_abort();
        }
        
        $this->resetGlobalEnvironment();

        parent::_after($test);
    }
}
