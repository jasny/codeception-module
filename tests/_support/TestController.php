<?php

use Jasny\Controller;
use Jasny\HttpMessage\UploadedFile;
use Codeception\Util\ReflectionHelper;

/**
 * Controller for functional tests
 */
class TestController extends Controller
{
    use Controller\RouteAction;
    use Controller\View\Twig;
    
    /**
     * Turn a set of UploadedFile objects in to associated arrays
     * 
     * @param UploadedFile[]|array $files
     * @return array
     */
    protected function filesToArray(array $files)
    {
        $result = [];
        
        foreach ($files as $fieldName => $uploadedFile) {
            /* @var $uploadedFile UploadedFile|array */
            if (is_array($uploadedFile)) {
                $result[$fieldName] = $this->filesToArray($uploadedFile);
            } else {
                $result[$fieldName] = [
                    'name' => $uploadedFile->getClientFilename(),
                    'tmp_name' => ReflectionHelper::readPrivateProperty($uploadedFile, 'tmpName'),
                    'size' => $uploadedFile->getSize(),
                    'type' => $uploadedFile->getClientMediaType(),
                    'error' => $uploadedFile->getError(),
                ];
            }
        }

        return $result;
    }
    
    /**
     * Get path of the view files
     *
     * @return string
     */
    protected function getViewPath()
    {
        return __DIR__ . '/views';
    }    
    
    
    /**
     * Show a view
     */
    public function defaultAction()
    {
        $this->view('index');
    }
    
    /**
     * Ping action
     */
    public function pingAction()
    {
        $this->output(['ack' => time()], 'json');
    }
    
    /**
     * Handle the REST request
     */
    public function restAction()
    {
        $request = $this->getRequest();
        
        $this->output([
            'requestMethod' => $request->getMethod(),
            'requestUri' => $request->getRequestTarget(),
            'queryParams' => $request->getQueryParams(),
            'formParams' => $request->getParsedBody() ?: [],
            'rawBody' => (string)$request->getBody(),
            'headers' => $request->getHeaders(),
            'X-Auth-Token' => $request->getHeaderLine('X-Auth-Token'),
            'files' => $this->filesToArray($request->getUploadedFiles())
        ], 'json');
    }
}
