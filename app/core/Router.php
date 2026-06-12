<?php
/**
 * Router - System-wide routing engine
 * Handles all HTTP requests and routes them to controllers
 */

class Router {
    private $routes = [];
    private $basePath;
    
    public function __construct($basePath = '') {
        $this->basePath = rtrim($basePath, '/');
    }
    
    /**
     * Register a GET route
     */
    public function get($path, $callback) {
        $this->addRoute('GET', $path, $callback);
    }
    
    /**
     * Register a POST route
     */
    public function post($path, $callback) {
        $this->addRoute('POST', $path, $callback);
    }
    
    /**
     * Register a route for any method
     */
    private function addRoute($method, $path, $callback) {
        $path = $this->basePath . '/' . trim($path, '/');
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'callback' => $callback
        ];
    }
    
    /**
     * Dispatch the request to the appropriate handler
     */
    public function dispatch() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }
            
            $pattern = $this->convertToRegex($route['path']);
            
            if (preg_match($pattern, $requestUri, $matches)) {
                array_shift($matches); // Remove full match
                
                // Call the callback with parameters
                $callback = $route['callback'];
                
                if (is_callable($callback)) {
                    return call_user_func_array($callback, $matches);
                }
                
                if (is_array($callback)) {
                    list($controller, $method) = $callback;
                    if (is_string($controller)) {
                        $controller = new $controller();
                    }
                    return call_user_func_array([$controller, $method], $matches);
                }
            }
        }
        
        // No route matched - 404
        $this->handle404();
    }
    
    /**
     * Convert route path to regex pattern
     */
    private function convertToRegex($path) {
        // Replace :param with named capture group
        $pattern = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    /**
     * Handle 404 errors
     */
    private function handle404() {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Route not found'
        ]);
        exit;
    }
}
