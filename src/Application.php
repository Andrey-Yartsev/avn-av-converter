<?php
/**
 * User: pel
 * Date: 06/09/2018
 */

namespace Converter;

use Converter\exceptions\HttpException;
use Converter\exceptions\NotFoundHttpException;

class Application
{
    protected $routes = [];

    public function __construct()
    {
        
    }

    public function run()
    {
        try {
            $method = 'GET';
            $url = $this->normalizeUrl('');
            list($callable, $arguments) = $this->findRoute($url, $method);

            if (!$callable) {
                throw new NotFoundHttpException('Route not found.');
            }

            return $this->send(call_user_func_array($callable, $arguments));
        } catch (\Exception $e) {
            return $this->sendException($e);
        }
    }

    /**
     * @param string $data
     * @return string
     */
    public function send($data)
    {
        $this->sendResponse(200, $data);
    }

    /**
     * @param \Exception $exception
     * @return string
     */
    public function sendException($exception)
    {
        if ($exception instanceof HttpException) {
            $httpCode = $exception->statusCode;
        } else {
            $httpCode = 500;
        }
        $message = [
            'error' => [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]
        ];
        $this->sendResponse($httpCode, $message);
    }

    public function sendResponse($httpCode = 200, $message)
    {
        if (php_sapi_name() !== 'cli') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Length: ' . strlen($message));
            http_response_code($httpCode);
        }
        echo json_encode($message);
        exit(0);
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