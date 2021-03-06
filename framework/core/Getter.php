<?php
namespace framework\core;

defined('app\env\GETTER_PROVIDERS_NAME') || define('app\env\GETTER_PROVIDERS_NAME', 'providers');

trait Getter
{
    /*
     * 获取Provider实例
     */
    public function __get($name)
    {
        $n = \app\env\GETTER_PROVIDERS_NAME;
        if (isset($this->$n) && isset($this->$n[$name])) {
			return Container::makeCustom($this->$n[$name]);
        } elseif ($v = Container::getProvider($name)) {
            if ($v[0] !== Container::T_MODEL) {
				return $this->$name = Container::get($name);
            }
			// 模型名称空间链实例
			return $this->$name = new class([$name], $v[1] ?? 1) {
	            private $_ns;
	            private $_depth;
	            public function __construct($ns, $depth) {
	                $this->_ns = $ns;
	                $this->_depth = $depth - 1;
	            }
	            public function __get($name) {
	                $this->_ns[] = $name;
	                if ($name[0] != '_') {
		                if ($this->_depth > 0) {
							return $this->$name = new self($this->_ns, $this->_depth);
		                } else {
							return $this->$name = Container::get(implode('.', $this->_ns));
		                }
	                }
					throw new \Exception('Undefined property: $'.implode('->', $this->_ns));
	            }
			};
        }
		throw new \Exception("Undefined property: $$name");
    }
}
