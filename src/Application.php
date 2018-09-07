<?php
/**
 * User: pel
 * Date: 06/09/2018
 */

namespace Converter;

class Application
{
    protected $routes = [];

    public function __construct()
    {
        
    }

    public function run()
    {
        $method = 'GET';
        $url = $this->normalizeUrl('');
        list($callable, $arguments) = $this->findRoute($url, $method);

        if (!$callable) {
            // route not found
        }

        try {
            return $this->send(call_user_func_array($callable, $arguments));
        } catch (\Exception $e) {
            return $this->sendException($e);
        }
    }

    public function send()
    {
        
    }

    public function sendException()
    {
        
    }

    /**
     * @param string $uri
     * @param callable|string $action
     * @param string $httpMethod
     * @return $this
     */
    public function addRoute($uri, $action, $httpMethod)
    {
        $this->routes[$uri][$httpMethod] = $action;
        return $this;
    }

    /**
     * @param string $uri
     * @return $this
     */
    public function removeRoute($uri)
    {
        if (isset($this->routes[$uri])) {
            unset($this->routes[$uri]);
        }

        return $this;
    }

    /**
     * @param string $uri
     * @param callable|string $action
     * @return $this
     */
    public function addGetRoute($uri, $action)
    {
        $this->addRoute($uri, $action, 'GET');
        return $this;
    }

    /**
     * @param string $uri
     * @param callable|string $action
     * @return $this
     */
    public function addPostRoute($uri, $action)
    {
        $this->addRoute($uri, $action, 'POST');
        return $this;
    }

    /**
     * @param string $uri
     * @param callable|string $action
     * @return $this
     */
    public function addPutRoute($uri, $action)
    {
        $this->addRoute($uri, $action, 'PUT');
        return $this;
    }

    /**
     * @param string $uri
     * @param callable|string $action
     * @return $this
     */
    public function addDeleteRoute($uri, $action)
    {
        $this->addRoute($uri, $action, 'DELETE');
        return $this;
    }

    /**
     * @param string $uri
     * @param callable|string $action
     * @return $this
     */
    public function addPatchRoute($uri, $action)
    {
        $this->addRoute($uri, $action, 'PATCH');
        return $this;
    }

    /**
     * @param string $uri
     * @param callable|string $action
     * @return $this
     */
    public function addHeadRoute($uri, $action)
    {
        $this->addRoute($uri, $action, 'HEAD');
        return $this;
    }

    /**
     * @param string $uri
     * @param callable|string $action
     * @return $this
     */
    public function addOptionsRoute($uri, $action)
    {
        $this->addRoute($uri, $action, 'OPTIONS');
        return $this;
    }

    /**
     * @param string $url
     * @param string|null $method
     * @return array|bool
     */
    public function findRoute($url, $method)
    {
        if (isset($this->routes[$url][$method]) && $this->routes[$url][$method] == $method) {
            return [$method, null];
        } else {
            foreach ($this->routes as $routeUrl => $routeMethods) {
                if (preg_match('|^'.$routeUrl.'$|', $url, $matches)) {
                    if (isset($routeMethods[$method])) {
                        array_shift($matches);
                        foreach ($matches as $match) {
                            $arguments[] = $match;
                        }
                        return [$routeMethods[$method], $arguments];
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param string $url
     * @return string
     */
    public function normalizeUrl($url)
    {
        if ($url === '/') {
            return $url;
        }
        if (substr($url, -1) == '/') {
            $url = substr($url, 0, -1);
        }
        if (substr($url, 0, 1) != '/') {
            $url = '/' . $url;
        }

        return $url;
    }
}