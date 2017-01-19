<?php

namespace Jasny\Codeception;

use Jasny\Router;
use Codeception\Configuration;
use Codeception\Lib\Framework;
use Codeception\TestInterface;
use Jasny\Codeception\Connector;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Interop\Container\ContainerInterface;

/**
 * Module for running functional tests using Jasny MVC
 */
class Module extends Framework
{
    /**
     * Required configuration fields
     * @var array
     */
    protected $requiredFields = ['container'];
    
    /**
     * Application container
     * @var ContainerInterface 
     */
    protected $container;
    
    /**
     * Module started output buffering
     * @var boolean
     */
    protected $outputBuffering = false;
    
    
    /**
     * Load the container by including the file.
     * @codeCoverageIgnore
     * 
     * @param string $file
     * @return ContainerInterface
     */
    protected function loadContainer($file)
    {
        return include $file;
    }
    
    /**
     * Initialize the container
     */
    protected function initContainer()
    {
        $container = $this->loadContainer(Configuration::projectDir() . $this->config['container']);

        if (!$container instanceof ContainerInterface) {
            throw new \UnexpectedValueException("Failed to get a container from '{$this->config['container']}'");
        }

        $this->container = $container;
    }

    /**
     * Return the application container
     * 
     * @throws \BadMethodCallException
     */
    public function getContainer()
    {
        if (!isset($this->container)) {
            throw new \BadMethodCallException("Container isn't initialized");
        }
        
        return $this->container;
    }
    
    /**
     * Check if the response writes to the output buffer
     * 
     * @return boolean
     */
    protected function usesOutputBuffer()
    {
        return
            $this->getContainer()->has(ResponseInterface::class) &&
            $this->getContainer()->get(ResponseInterface::class)->getBody()->getMetadata('uri') === 'php://output';
    }
    
    /**
     * Enable output buffering
     * 
     * @throws \RuntimeException
     */
    protected function startOutputBuffering()
    {
        $this->obStart();

        if ($this->obGetLevel() < 1) {
            throw new \RuntimeException("Failed to start output buffering");
        }
    }
    
    /**
     * Disable output buffering
     */
    protected function stopOutputBuffering()
    {
        if ($this->obGetLevel() > 0) {
            $this->obEndClean();
        }
    }

    
    /**
     * Initialize the module
     */
    public function _initialize()
    {
        $this->initContainer();
    }
    
    /**
     * Call before suite
     * 
     * @param array $settings
     */
    public function _beforeSuite($settings = [])
    {
        parent::_beforeSuite($settings);
        
        if ($this->usesOutputBuffer()) {
            $this->startOutputBuffering();
        }
    }
    
    /**
     * Call after suite
     */
    public function _afterSuite()
    {
        if ($this->usesOutputBuffer()) {
            $this->stopOutputBuffering();
        }
        
        parent::_afterSuite();
    }
    
    /**
     * Before each test
     * 
     * @param TestInterface $test
     */
    public function _before(TestInterface $test)
    {
        $container = $this->getContainer();
        
        $this->client = new Connector();
        $this->client->setRouter($container->get(Router::class));

        if ($container->has(ServerRequestInterface::class)) {
            $this->client->setBaseRequest($container->get(ServerRequestInterface::class));
        }
        
        if ($container->has(ResponseInterface::class)) {
            $this->client->setBaseResponse($container->get(ResponseInterface::class));
        }
        
        parent::_before($test);
    }
    
    /**
     * After each test
     * 
     * @param TestInterface $test
     */
    public function _after(TestInterface $test)
    {
        if ($this->sessionStatus() === PHP_SESSION_ACTIVE) {
            $this->sessionAbort();
        }
        
        if (isset($this->client)) {
            $this->client->reset();
        }

        parent::_after($test);
    }
    
    
    /**
     * Wrapper around `ob_start()`
     * @codeCoverageIgnore
     */
    protected function obStart()
    {
        ob_start();
    }
    
    /**
     * Wrapper around `ob_get_level()`
     * @codeCoverageIgnore
     * 
     * @return int
     */
    protected function obGetLevel()
    {
        return ob_get_level();
    }
    
    /**
     * Wrapper around `ob_end_clean()`
     * @codeCoverageIgnore
     */
    protected function obEndClean()
    {
        ob_end_clean();
    }
    
    /**
     * Wrapper around `session_status()`
     * @codeCoverageIgnore
     * 
     * @return int
     */
    protected function sessionStatus()
    {
        return session_status();
    }
    
    /**
     * Wrapper around `session_abort()`
     * @codeCoverageIgnore
     */
    protected function sessionAbort()
    {
        return session_abort();
    }
}
