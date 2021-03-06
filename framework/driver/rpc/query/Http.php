<?php
namespace framework\driver\rpc\query;

class Http
{
	// namespace
    protected $ns;
	// 配置项
    protected $config;
	// client实例
    protected $client;
	// filter设置
    protected $filters;
	// 构建处理器
    protected $build_handler;
	// 响应处理器
    protected $response_handler;
    
    /*
     * 构造函数
     */
    public function __construct($client, $name , $config)
    {
        if (isset($name)) {
            $this->ns[] = $name;
        }
        $this->client = $client;
        $this->config = $config;
    }

    /*
     * 魔术方法，设置namespace
     */
    public function __get($name)
    {
        $this->ns[] = $name;
        return $this;
    }
    
    /*
     * 魔术方法，调用rpc方法
     */
    public function __call($method, $params)
    {
        switch ($method) {
            case $this->config['filter_method_alias']   ?? 'filter':
                $this->filters[] = $params;
                return $this;
            case $this->config['ns_method_alias']       ?? 'ns':
                $this->ns[] = $params[0];
                return $this;
            case $this->config['then_method_alias']     ?? 'then':
                $this->response_handler = $params[0];
                return $this;
            case $this->config['build_method_alias']    ?? 'build':
                $this->build_handler = $params[0];
                return $this;
            default:
                $this->ns[] = $method;
                return $this->call($params);
        }
    }
    
    /*
     * 调用
     */
    protected function call($params)
    {
        $method = $params && is_array(end($params)) ? 'POST' : 'GET';
        $client = $this->client->make($method, $this->ns ?? [], $this->filters, $params);
        if (isset($this->build_handler)) {
            $this->build_handler($client);
        }
        if (isset($this->response_handler)) {
            return $$this->response_handler($client);
        } else {
            return $this->client->response($client);
        }
    }
}