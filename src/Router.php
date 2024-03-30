<?php

namespace Pattoxd\Router;

class Router
{

    public $controller = 'Home';
    public $method = 'index';

    public $routes = [];

    public function add($method, $uri, $controller)
    {
        $this->routes[] = compact('method', 'uri', 'controller');
    }

    public function get($uri, $controller)
    {
        $this->add('GET', $uri, $controller);
    }

    public function post($uri, $controller)
    {
        $this->add('POST', $uri, $controller);
    }

    public function delete($uri, $controller)
    {
        $this->add('DELETE', $uri, $controller);
    }

    public function patch($uri, $controller)
    {
        $this->add('PATCH', $uri, $controller);
    }

    public function put($uri, $controller)
    {
        $this->add('PUT', $uri, $controller);
    }

    public function route($uri, $method)
    {
        [$uri, $params] = $this->divideUriFromParams($uri);

        foreach ($this->routes as $route) {
            [$route['uri'], $route['urlParams']] = $this->divideUriFromParams($route['uri']);

            if (!($route['uri'] == $uri) || !($route['method'] == strtoupper($method))) {
                continue;
            }

            $route['urlParams'] = $this->asociateParams($route['urlParams'], $params);
            $route['urlParams'] = $this->cleanParams($route['urlParams']);

            $controller = ucwords($this->parseUri($route['uri'])[0]);
            if (substr($controller, -1) == 's') {
                $controller = substr($controller, 0, -1);
            }
            $controllerPath = '/Controllers/' . $controller . 's.php';
            $fullPath = base_path($controllerPath);

            if (!file_exists($fullPath)) {
                $this->abort();
            }

            require_once $fullPath;
            $class = $this->fullPathToController($controllerPath);
            $class = trim($class, '\\');

            if (!class_exists($class)) {
                $this->abort();
            }

            if (!isset($this->parseUri($route['uri'])[1])) {
                $this->abort();
            }

            $this->controller = $controller;
            $this->method = $this->parseUri($route['uri'])[1];
            if (!method_exists($class, $this->method)) {
                $this->abort();
            }

            call_user_func($route['controller'], $route['urlParams']);
            $headers = $this->getHeaders();
            $data = $this->getBody();

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

        $this->abort();
    }

    protected function parseUri($uri)
    {
        $uri = parse_url($uri)['path'];
        $uri = trim($uri, '/');
        $uri = explode('/', $uri);
        return $uri;
    }

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

    protected function asociateParams($routeParams, $params)
    {
        foreach ($routeParams as $key => $param) {
            $param = trim($param, ':');
            $routeParams[$param] = array_shift($params);
        }
        return $routeParams;
    }

    protected function cleanParams($routeParams)
    {
        foreach ($routeParams as $key => $param) {
            if (is_int($key)) {
                unset($routeParams[$key]);
            }

        }
        return $routeParams;
    }

    protected function fullPathToController($controller)
    {
        $controller = str_replace('/', '\\', $controller);
        $controllerClass = str_replace('.php', '', $controller);
        return $controllerClass;
    }
    protected function abort($code = 404)
    {
        http_response_code($code);
        echo "404 Not Found";
        die();
    }

    protected function getHeaders()
    {
        $headers = getallheaders();
        $headersJson = json_encode($headers);
        return json_decode($headersJson, true);
    }

    protected function getBody()
    {
        $bodyJson = file_get_contents("php://input");
        $bodyArray = json_decode($bodyJson, true);
        return $bodyArray;
    }
}
