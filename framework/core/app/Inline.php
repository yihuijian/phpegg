<?php
namespace framework\core\app;

use framework\App;
use framework\core\View;
use framework\core\Config;
use framework\core\Dispatcher;
use framework\core\http\Status;
use framework\core\http\Request;
use framework\core\http\Response;

class Inline extends App
{
    protected $config = [
        // 调度模式，支持default route组合
        'dispatch_mode'     => ['default'],
        // 控制器公共路径
        'controller_path'   => 'controller',
        // 是否启用视图
        'enable_view'       => false,
        // 是否启用Getter魔术方法
        'enable_getter'     => true,
        // Getter providers
        'getter_providers'  => null,
        // 是否将返回值1改成null
        'return_1_to_null'  => false,
        // 默认调度的缺省调度
        'default_dispatch_index' => null,
        // 默认调度的控制器，为空不限制
        'default_dispatch_controllers' => null,
        // 默认调度时URL PATH中划线转成下划线
        'default_dispatch_hyphen_to_underscore' => false,
        // 路由调度的路由表，如果值为字符串则作为配置名引入
        'route_dispatch_routes' => null,
        // 是否路由动态调用
        'route_dispatch_dynamic' => false,
        // 提取路由调度参数到变量
        'route_dispatch_extract_params' => false,
    ];
    
    /*
     * 调度
     */
    protected function dispatch()
    {
        $path = trim(Request::path(), '/');
        foreach ((array) $this->config['dispatch_mode'] as $mode) {
            if ($dispatch = $this->{$mode.'Dispatch'}($path)) {
                return $dispatch;
            }
        }
    }
    
    /*
     * 调用
     */
    protected function call()
    {
        if ($this->config['enable_getter']) {
            $call = \Closure::bind(function($__file, $_PARAMS, $__extract) {
                if ($__extract && $_PARAMS) {
                    extract($_PARAMS, EXTR_SKIP);
                }
                return require $__file;
            }, getter($this->config['getter_providers']));
        } else {
            $call = static function($__file, $_PARAMS, $__extract) {
                if ($__extract && $_PARAMS) {
                    extract($_PARAMS, EXTR_SKIP);
                }
                return require $__file;
            };
        }
        $return = $call(
            $this->dispatch['controller_file'],
            $this->dispatch['params'] ?? null,
            $this->config['route_dispatch_extract_params']
        );
        return $return === 1 && $this->config['return_1_to_null'] ? null : $return;
    }

    /*
     * 错误
     */
    protected function error($code = null, $message = null)
    {
        if (isset(Status::CODE[$code])) {
            Response::status($code);
        }
        if (empty($this->config['enable_view'])) {
            Response::json(['error' => compact('code', 'message')]);
        } else {
            Response::html(View::error($code, $message));
        }
    }
    
    /*
     * 响应
     */
    protected function respond($return = null)
    {
        if (empty($this->config['enable_view'])) {
            Response::json(['result' => $return]);
        } else {
            Response::view('/'.$this->dispatch['controller'], $return);
        }
    }
    
    /*
     * 默认调度
     */
    protected function defaultDispatch($path) 
    {
        if ($path) {
            $controller = $this->config['default_dispatch_hyphen_to_underscore'] ? strtr($path, '-', '_') : $path;
            if (!isset($this->config['default_dispatch_controllers'])) {
                if ($controller_file = $this->getAndCheckControllerFile($controller)) {
                    return compact('controller', 'controller_file');
                }
            } elseif (in_array($controller, $this->config['default_dispatch_controllers'])) {
                return $this->defaultDispatchResult($controller);
            }
        } elseif ($this->config['default_dispatch_index']) {
            return $this->defaultDispatchResult($this->config['default_dispatch_index']);
        }
    }
    
    /*
     * 路由调度
     */
    protected function routeDispatch($path)
    {
        if (!empty($routes = $this->config['route_dispatch_routes'])) {
            $dispatch = Dispatcher::route(
                empty($path) ? [] : explode('/', $path),
                is_string($routes) ? Config::read($routes) : $routes,
                2,
                $this->config['route_dispatch_dynamic']
            );
            if ($dispatch) {
                if (!$dispatch[2] || ($controller_file = $this->getAndCheckControllerFile($dispatch[0]))) {
                    return [
                        'controller'        => $dispatch[0],
                        'controller_file'   => $controller_file ?? $this->getControllerFile($dispatch[0]),
                        'params'            => $dispatch[1]
                    ];
                }
            }
        }
    }
    
    /*
     * 获取控制器文件
     */
    protected function getControllerFile($controller)
    {
        return APP_DIR.$this->config['controller_path']."/$controller.php";
    }
    
    /*
     * 设置返回默认默认调度结果
     */
    protected function defaultDispatchResult($controller)
    {
        return ['controller' => $controller, 'controller_file' => $this->getControllerFile($controller)];
    }
    
    /*
     * 获取并验证控制器文件
     */
    protected function getAndCheckControllerFile($controller)
    {
        if (preg_match('/^[\w\-]+(\/[\w\-]+)*$/', $controller)
            && is_php_file($file = $this->getControllerFile($controller))
        ) {
            return $file;
        }
    }
}
