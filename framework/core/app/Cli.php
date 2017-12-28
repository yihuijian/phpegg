<?php
namespace framework\core\app;

use framework\App;
use framework\core\Getter;
use framework\core\Command;
use framework\core\Controller;

class Cli extends App
{
    protected $config = [
        // 默认命令
        'default_commands' => null,
        // 
        'default_call_method' => null,
        // 匿名函数是否启用Getter魔术方法
        'enable_closure_getter' => true,
    ];
    
    protected $parsed_argv = [
        'script'    => null,
        'name'      => true,
        'params'    => [],
        'options'   => []
    ];
    protected $enable_readline = false;

    public function command(...$params)
    {
        $count = count($params);
        if ($count === 1) {
            if (is_array($params[0])) {
                $this->dispatch = $params[0] + $this->dispatch;
            } else {
                $this->dispatch = $params[0];
                $this->parsed_argv['name'] = null;
            }
            return $this;
        } elseif ($count === 2) {
            $this->dispatch[$params[0]] = $params[1];
            return $this;
        }
        throw new \RuntimeException('Command params error');
    }
    
    public function read($prompt = null)
    {
		if ($this->enable_readline){
			return readline($prompt);
		}
		echo $prompt;
		return fgets(STDIN);
    }
    
    public function write($text)
    {
        fwrite(STDOUT, $text);
    }
    
    public function getArgv()
    {
        return $this->parsed_argv;
    }
    
    protected function dispatch()
    {
        if (PHP_SAPI !== 'cli') {
            throw new \RuntimeException('NOT CLI SAPI');
        }
        define('IS_CLI', true);
        $this->enable_readline = extension_loaded('readline');
        return $this->config['default_commands'] ?: [];
    }
    
    protected function call()
    {
        $this->parseArgv();
        if (!$name = $this->parsed_argv['name']) {
            $call = $this->dispatch;
        } elseif (isset($this->dispatch[$name])) {
            $call = $this->dispatch[$name];
        } else {
            self::abort(404);
        }
        if ($call instanceof \Closure) {
            if (empty($this->config['enable_closure_getter'])) {
                $command = new class ($this) extends Command {};
            } else {
                $command = new class ($this) extends Command {
                    use Getter;
                };
            }
            $ref  = new \ReflectionFunction($call);
            $call = \Closure::bind($call, $command, Command::class);
        } else {
            if (!is_subclass_of($call, Command::class)) {
                throw new \RuntimeException('call error');
            }
            $method = $this->config['default_call_method'] ?? '__invoke';
            $ref    = new \ReflectionMethod($call, $method);
            $call   = [new $call($this), $method];
        }
        if (($params = Controller::methodBindListParams($ref, $this->parsed_argv['params'])) === false) {
            self::abort(400);
        }
        return $call(...$params);
    }
    
    protected function error($code = null, $message = null)
    {
        var_dump($code, $message);
    }
    
    protected function response($return = null)
    {
        self::exit(2);
        exit((int) $return);
    }
    
    protected function parseArgv()
    {
        $argv = $_SERVER['argv'];
        $this->parsed_argv['script'] = array_shift($argv);
        if ($this->parsed_argv['name']) {
            if (!$this->parsed_argv['name'] = array_shift($argv)) {
                self::abort(404);
            }
        }
        if (($count = count($argv)) > 0) {
            $is_option = false;
            for ($i = 0; $i < $count; $i++) {
                if (!$is_option && strpos($argv[$i], '-') === false) {
                    $this->parsed_argv['params'][] = $argv[$i];
                    continue;
                }
    			$is_option = true;
    			if (substr($argv[$i], 0, 1) !== '-') {
    				continue;
    			}
    			$arg = str_replace('-', '', $argv[$i]);
    			$value = null;
    			if (isset($argv[$i + 1]) && substr($argv[$i + 1], 0, 1) != '-') {
    				$value = $argv[$i + 1];
    				$i++;
    			}
    			$this->parsed_argv['options'][$arg] = $value;
    			$is_option = false;
            }
        }
    }
}
