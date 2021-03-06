<?php

use Jasny\Controller;

/**
 * Controller for legacy tests
 */
class LegacyTestController extends Controller
{
    use Controller\RouteAction;
    use Controller\View\Twig;
    
    /**
     * Group data as provided by $_FILES
     * 
     * @param array   $array
     * @return array
     */
    protected function groupUploadedFiles(array $array)
    {
        $files = [];
        
        foreach ($array as $key => $values) {
            if (!is_array($values['error'])) {
                $files[$key] = $values;
                continue;
            }
            
            $rearranged = [];
            foreach ($values as $property => $propertyValues) {
                foreach ($propertyValues as $subkey => $value) {
                    $rearranged[$subkey][$property] = $value;
                }
            }
            
            $files[$key] = $this->groupUploadedFiles($rearranged);
        }
        
        return $files;
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
        header('Content-Type: application/json');
        
        echo json_encode(['ack' => time()]);
    }
    
    /**
     * Handle the REST request
     */
    public function restAction()
    {
        $request = $this->getRequest();
        
        $this->output([
            'requestMethod' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
            'requestUri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'queryParams' => $_GET,
            'formParams' => $_POST,
            'rawBody' => (string)$request->getBody(), // Using php://input won't work, even in legacy mode
            'headers' => $request->getHeaders(),
            'X-Auth-Token' => isset($_SERVER['HTTP_X_AUTH_TOKEN']) ? $_SERVER['HTTP_X_AUTH_TOKEN'] : '',
            'files' => $this->groupUploadedFiles($_FILES)
        ], 'json');
    }
    
    /**
     * View rendered template
     *
     * @param string $name    Template name
     * @param array  $context Template context
     */
    public function view($name, array $context = array())
    {
        if (!pathinfo($name, PATHINFO_EXTENSION)) {
            $name .= '.html.twig';
        }

        $twig = $this->getViewer()->getTwig();
        $tmpl = $twig->loadTemplate($name);

        header('Content-Type: text/html; charset=' . $twig->getCharset());
        $tmpl->display($context);
    }
}
