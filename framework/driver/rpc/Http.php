<?php
namespace framework\driver\rpc;

class Http extends Rpc
{
    protected $client;
    
    protected $config = [
        /*
        // 服务端点
        'endpoint'              => null,
        // URL后缀名
        'url_suffix'            => null,
        // URL风格转换
        'url_style'             => null,
        // 请求公共headers
        'http_headers'          => null,
        // 请求公共curlopts
        'http_curlopts'         => null,
        // ns方法别名
        'ns_method_alias'       => 'ns',
        // filter方法别名
        'filter_method_alias'   => 'filter',
        // build方法别名
        'build_method_alias'    => 'build',
        // then方法别名
        'then_method_alias'     => 'then',
        // 批请求call方法别名
        'batch_call_method_alias'   => 'call',
        // 批请求select超时
        'batch_select_timeout'  => 0.1,
        // 请求内容编码
        'requset_encode'        => null,
        // 响应内容解码
        'response_decode'       => null,
        // 响应结果字段
        'response_result_field' => null,
        // 忽略错误返回false
        'response_ignore_error' => null,
        // 错误码定义字段
        'error_code_field'      => null,
        // 错误信息定义字段
        'error_message_field'   => null,
        */
    ];

    public function __construct($config)
    {
        $this->config = $config + $this->config;
        $this->client = new client\Http($this->config);
    }
    
    /*
     * query实例
     */
    public function query($name = null)
    {
        return new query\Http($this->client, $name, $this->config);
    }
    
    /*
     * 批请求
     */
    public function batch($common_ns = null, callable $common_build_handler = null)
    {
        return new query\HttpBatch($this->client, $common_ns, $this->config, $common_build_handler);
    }
}