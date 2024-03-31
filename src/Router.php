<?php

namespace Pattoxd\Router;


/**
 * The Router class handles routing and dispatching requests to the appropriate controller and method.
 */
class Router
{

    /**
     * The default controller name.
     *
     * @var string
     */
    public $controller = 'Home';

    /**
     * The default method name.
     *
     * @var string
     */
    public $method = 'index';

    /**
     * The array of routes.
     *
     * @var array
     */
    public $routes = [];

    /**
     * The base path of the application.
     *
     * @var string
     */
    public $base_path;

    /**
     * Create a new Router instance.
     *
     * @param string $base_path The base path of the application.
     */
    public function __construct($base_path)
    {
        $this->base_path = $base_path;
    }

    /**
     * Add a route to the routes array.
     *
     * @param string $method     The HTTP method of the route.
     * @param string $uri        The URI pattern of the route.
     * @param string $controller The controller to be called for the route.
     * @return void
     */
    public function add($method, $uri, $controller)
    {
        $this->routes[] = compact('method', 'uri', 'controller');
    }

    /**
     * Add a GET route to the routes array.
     *
     * @param string $uri        The URI pattern of the route.
     * @param string $controller The controller to be called for the route.
     * @return void
     */
    public function get($uri, $controller)
    {
        $this->add('GET', $uri, $controller);
    }

    /**
     * Add a POST route to the routes array.
     *
     * @param string $uri        The URI pattern of the route.
     * @param string $controller The controller to be called for the route.
     * @return void
     */
    public function post($uri, $controller)
    {
        $this->add('POST', $uri, $controller);
    }

    /**
     * Add a DELETE route to the routes array.
     *
     * @param string $uri        The URI pattern of the route.
     * @param string $controller The controller to be called for the route.
     * @return void
     */
    public function delete($uri, $controller)
    {
        $this->add('DELETE', $uri, $controller);
    }

    /**
     * Add a PATCH route to the routes array.
     *
     * @param string $uri        The URI pattern of the route.
     * @param string $controller The controller to be called for the route.
     * @return void
     */
    public function patch($uri, $controller)
    {
        $this->add('PATCH', $uri, $controller);
    }

    /**
     * Add a PUT route to the routes array.
     *
     * @param string $uri        The URI pattern of the route.
     * @param string $controller The controller to be called for the route.
     * @return void
     */
    public function put($uri, $controller)
    {
        $this->add('PUT', $uri, $controller);
    }

    /**
     * Route the request to the appropriate controller and method.
     *
     * @param string $uri    The request URI.
     * @param string $method The request method.
     * @return bool True if the route is found and executed, false otherwise.
     */
    public function route($uri, $method)
    {
        // Divide the URI into the actual URI and any URL parameters
        [$uri, $params] = $this->divideUriFromParams($uri);

        // Iterate through the routes and find a matching route
        foreach ($this->routes as $route) {
            // Divide the route URI into the actual URI and any URL parameters
            [$route['uri'], $route['urlParams']] = $this->divideUriFromParams($route['uri']);

            // Check if the route URI and method match the requested URI and method
            if (!($route['uri'] == $uri) || !($route['method'] == strtoupper($method))) {
                continue;
            }

            // Associate the URL parameters with their corresponding route parameters
            $route['urlParams'] = $this->asociateParams($route['urlParams'], $params);

            // Clean up the URL parameters
            $route['urlParams'] = $this->cleanParams($route['urlParams']);

            // Get the controller name from the route URI
            $controller = ucwords($this->parseUri($route['uri'])[0]);
            if (substr($controller, -1) == 's') {
                $controller = substr($controller, 0, -1);
            }

            // Get the path to the controller file
            $controllerPath = '/Controllers/' . $controller . 's.php';
            $fullPath = base_path($controllerPath);

            // Check if the controller file exists
            if (!file_exists($fullPath)) {
                $this->abort();
            }

            // Include the controller file
            require_once $fullPath;

            // Get the fully qualified class name of the controller
            $class = $this->fullPathToController($controllerPath);
            $class = trim($class, '\\');

            // Check if the controller class exists
            if (!class_exists($class)) {
                $this->abort();
            }

            // Check if the method exists in the controller class
            if (!isset($this->parseUri($route['uri'])[1])) {
                $this->abort();
            }

            // Set the controller and method names
            $this->controller = $controller;
            $this->method = $this->parseUri($route['uri'])[1];

            // Check if the method exists in the controller class
            if (!method_exists($class, $this->method)) {
                $this->abort();
            }

            // Call the controller method with the URL parameters
            call_user_func($route['controller'], $route['urlParams']);

            // Get the headers and body of the request
            $headers = $this->getHeaders();
            $data = $this->getBody();

            // Call the controller method with the request data
            call_user_func(
                [$class, $this->method],
                [
                    'data' => $data,
                    'headers' => $headers,
                    'urlParams' => $route['urlParams'],
                ]
            );

            return true;
        }

        // No matching route found, abort the request
        $this->abort();
    }

    /**
     * Parse the URI into an array of segments.
     *
     * @param string $uri The URI to parse.
     * @return array The array of URI segments.
     */
    protected function parseUri($uri)
    {
        $uri = parse_url($uri)['path'];
        $uri = trim($uri, '/');
        $uri = explode('/', $uri);
        return $uri;
    }

    /**
     * Divide the URI into the actual URI and any URL parameters.
     *
     * @param string $uri The URI to divide.
     * @return array The array containing the actual URI and URL parameters.
     */
    protected function divideUriFromParams($uri)
    {
        $uri = trim($uri, '/');
        $params = [];
        $arrUri = explode('/', $uri);
        if (count($arrUri) > 2) {
            $uri = $arrUri[0] . '/' . $arrUri[1];
            for ($i = 2; $i < count($arrUri); $i++) {
                array_push($params, $arrUri[$i]);
            }
        }
        return [$uri, $params];
    }

    /**
     * Associate the URL parameters with their corresponding route parameters.
     *
     * @param array $routeParams The route parameters.
     * @param array $params      The URL parameters.
     * @return array The array of associated parameters.
     */
    protected function asociateParams($routeParams, $params)
    {
        foreach ($routeParams as $key => $param) {
            $param = trim($param, ':');
            $routeParams[$param] = array_shift($params);
        }
        return $routeParams;
    }

    /**
     * Clean up the URL parameters by removing any numeric keys.
     *
     * @param array $routeParams The route parameters.
     * @return array The cleaned up array of parameters.
     */
    protected function cleanParams($routeParams)
    {
        foreach ($routeParams as $key => $param) {
            if (is_int($key)) {
                unset($routeParams[$key]);
            }
        }
        return $routeParams;
    }

    /**
     * Convert the controller file path to a fully qualified class name.
     *
     * @param string $controller The controller file path.
     * @return string The fully qualified class name.
     */
    protected function fullPathToController($controller)
    {
        $controller = str_replace('/', '\\', $controller);
        $controllerClass = str_replace('.php', '', $controller);
        return $controllerClass;
    }

    /**
     * Abort the request with a 404 response.
     *
     * @param int $code The HTTP response code.
     * @return void
     */
    protected function abort($code = 404)
    {
        http_response_code($code);
        echo "404 Not Found";
        die();
    }

    /**
     * Get the request headers.
     *
     * @return array The array of request headers.
     */
    protected function getHeaders()
    {
        $headers = getallheaders();
        $headersJson = json_encode($headers);
        return json_decode($headersJson, true);
    }

    /**
     * Get the request body.
     *
     * @return array The array representation of the request body.
     */
    protected function getBody()
    {
        $bodyJson = file_get_contents("php://input");
        $bodyArray = json_decode($bodyJson, true);
        return $bodyArray;
    }
}
