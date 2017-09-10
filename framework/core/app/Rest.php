<?php
namespace framework\core\app;

use framework\App;
use framework\util\Xml;
use framework\core\Router;
use framework\core\Loader;
use framework\core\Config;
use framework\core\http\Request;
use framework\core\http\Response;

class Rest extends App
{
    protected $ns;
    protected $config = [
        'route_mode' => 0,
        'param_mode' => 0,
        'query_to_params' => 0,
        'controller_depth' => 0,
        'controller_prefix' => 'controller',
    ];
    
    protected function dispatch()
    {
        $this->ns = 'app\\'.$this->config['controller_prefix'].'\\';
        $method = strtolower(Request::method());
        if (in_array($method, ['get','post', 'put', 'delete', 'options', 'head', 'patch'], true)) {
            $path = trim(Request::path(), '/');
            if ($path) {
                $path = explode('/', $path);
            }
            switch ($this->config['route_mode']) {
                case 0:
                    return $this->defaultDispatch($path, $method);
                case 1:
                    return $this->routeDispatch($path, $method);
                case 2:
                    return $this->defaultDispatch($path, $method) ?: $this->routeDispatch($path, $method);
            }
        }
        return false;
    }

    protected function handle()
    {
        $this->setPostParams();
        $action = $this->dispatch['action'];
        $params = $this->dispatch['params'];
        $controller = $this->dispatch['controller'];
        if ($this->config['param_mode'] === 2) {
            if (isset($this->dispatch['method'])) {
                $method = $this->dispatch['method'];
            } else {
                $method =  new \ReflectionMethod($controller, $action);
            }
        }
        $this->dispatch = null;
        switch ($this->config['param_mode']) {
            case 1:
                return $controller->$action(...$params);
            case 2:
                $parameters = [];
                if ($method->getnumberofparameters() > 0) {
                    if ($this->config['query_to_params']) {
                        $params = array_merge($_GET, $params);
                    }
                    foreach ($method->getParameters() as $param) {
                        if (isset($params[$param->name])) {
                            $parameters[] = $params[$param->name];
                        } elseif($param->isDefaultValueAvailable()) {
                            $parameters[] = $param->getdefaultvalue();
                        } else {
                            $this->abort(404);
                        }
                    }
                }
                return $method->invokeArgs($controller, $parameters);
            default:
                return $controller->$action();
        }
    }
    
    protected function error($code = null, $message = null)
    {
        Response::status($code ?: 500);
        Response::json(['error' => compact('code', 'message')], false);
    }
    
    protected function response($return)
    {
        Response::json(['result' => $return], false);
    }
    
    protected function defaultDispatch($path, $method) 
    {
        $count = count($path);
        $depth = $this->config['controller_depth'];
        if ($depth > 0) {
            if ($count >= $depth) {
                $class = $this->ns.implode('\\', $count === $depth ? $path : array_slice($path, 0, $depth));
            }
        } else {
            $this->config['param_mode'] = 0;
            $class = $this->ns.implode('\\', $path);
        }
        if (isset($class) && Loader::importPrefixClass($class)) {
            $controller = new $class();
            if (is_callable([$controller, $method])) {
                $params = null;
                if ($depth && $count > $depth) {
                    $params = array_slice($path, $depth);
                    if ($this->config['param_mode'] === 2) {
                        $params = $this->getKvParams($params);
                    }
                }
                return ['controller' => $controller, 'action' => $action, 'params' => $params];
            }
        }
        return false;
    }

    protected function routeDispatch($path, $method)
    {
        $dispatch = Router::dispatch($path, Config::get('router'), $method);
        if ($dispatch) {
            $action = array_pop($dispatch[0]);
            $class = $this->ns.implode('\\', $dispatch[0]);
            if (Loader::importPrefixClass($class)) {
                $controller = new $class();
                $refmethod = new \ReflectionMethod($controller, $action);
                if (!$refmethod->isPublic()) {
                    if ($refmethod->isProtected()) {
                        $refmethod->setAccessible(true);
                    } else {
                        return false;
                    }
                }
                $this->method = $refmethod;
                $this->config['param_mode'] = 2;
                return ['controller'=> $controller, 'action' => $action, 'params' => $dispatch[1]];
            }
        }
        return false;
    }

    protected function getKvParams(array $path)
    {
        $params = [];
        $len = count($path);
        for ($i =0; $i < $len; $i = $i+2) {
            $params[$path[$i]] = $path[$i+1] ?? null;
        }
        return $params;
    }
    
    protected function setPostParams()
    {
        $type = Request::header('Content-Type');
        if ($type) {
            switch (trim(strtok(strtolower($type), ';'))) {
                case 'application/json':
                    Request::set('post', jsondecode(Request::body()));
                    break;
                case 'application/xml';
                    Request::set('post', Xml::decode(Request::body()));
                    break;
                case 'multipart/form-data'; 
                    break;
                case 'application/x-www-form-urlencoded'; 
                    break;
                default:
                    Request::set('post', Request::body());
                    break;
            }
        }
    }
}
