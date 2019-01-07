<?php
namespace framework\core\http;

use framework\util\Arr;

class Client
{
    const EOL = "\r\n";
    // cURL句柄
    private $ch;
    // 启用调试
    private $debug;
    // 错误信息
    private $error;
    // 请求设置
    private $request;
    // 响应内容
    private $response;
    // 是否返回响应headers
    private $return_headers;
    
    public function __construct($method, $url)
    {
        $this->debug = APP_DEBUG;
        $this->request = (object) compact('url', 'method');
    }

    /*
     * 返回GET实例
     */
    public static function get($url)
    {
        return new self('GET', $url);
    }
    
    /*
     * 返回POST实例
     */
    public static function post($url)
    {
        return new self('POST', $url);
    }
    
    /*
     * 多进程批量请求
     */
    public static function multi(array $queries, callable $handle = null, $select_timeout = 0.1)
    {
        $mh = curl_multi_init();
        foreach ($queries as $i => $query) {
            $ch = $query->build();
            $indices[strval($ch)] = $i;
            curl_multi_add_handle($mh, $ch);
        }
        do{
            if (($status = curl_multi_exec($mh, $active)) !== CURLM_CALL_MULTI_PERFORM) {
                if ($status !== CURLM_OK) {
                    break;
                }
                while ($done = curl_multi_info_read($mh)) {
                    $ch = $done['handle'];
                    $index = $indices[strval($ch)];
                    $query = $queries[$index];
                    $query->setResponse(curl_multi_getcontent($ch));
                    if (isset($handle)) {
                        $return[$index] = $handle($query, $index);
                    } else {
                        $return[$index] = $query;
                    }
                    curl_multi_remove_handle($mh, $ch);
                    if ($active > 0) {
                        curl_multi_select($mh, $select_timeout);
                    }
                }
            }
        } while ($active > 0);
        curl_multi_close($mh);
        return $return ?? null;
    }

    /*
     * 设置请求的body内容
     */
    public function body($body, $type = null)
    {
        $this->request->body = $body;
        if ($type) {
            $this->request->headers['Content-Type'] = $type;
        }
        return $this;
    }
    
    /*
     * 设置请求的body内容为数组被json_encode后的字符串
     */
    public function json(array $data)
    {
        $this->request->body = jsonencode($data);
        $this->request->headers['Content-Type'] = 'application/json; charset=UTF-8';
        return $this;
    }

    /*
     * 设置表单数据，数据默认为application/x-www-form-urlencoded格式否则为multipart/form-data
     */
    public function form(array $data, $multipart = false)
    {
        if ($multipart) {
			$this->request->body = $data;
        } else {
            $this->request->body = http_build_query($data);
            $this->request->headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }
        return $this;
    }

    /*
     * 本地文件上传请求，只支持post方法，通常在form方法后调用
     */
    public function file($name, $content, $filename = null, $mimetype = null)
    {
        if (substr($name, -2) === '[]') {
            $this->request->body[substr($name, 0, -2)][] = $this->setCurlFile($content, $filename, $mimetype);
        } else {
            $this->request->body[$name] = $this->setCurlFile($content, $filename, $mimetype);
        }
        return $this;
    }
    
    /*
     * 变量内容上传，与file方法相似
     */
    public function buffer($name, $content, $filename = null, $mimetype = null)
    {
        if (empty($this->request->boundary)) {
            $this->request->boundary = uniqid();
            $this->request->headers['Content-Type'] = 'multipart/form-data; boundary='.$this->request->boundary;
            if (is_array($this->request->body)) {
                foreach ($this->request->body as $pk => $pv) {
                    $body[] = '--'.$this->request->boundary;
                    $body[] = "Content-Disposition: form-data; name=\"$pk\"";
                    $body[] = '';
                    $body[] = $pv;
                }
                $body[] = '';
                $this->request->body = implode(self::EOL, $body);
            } else {
                $this->request->body = null;
            }
        } else {
            $this->request->body = substr($this->request->body, 0, -19);
        }
        $this->request->body .= $this->setMultipartFile($name, $content, $filename, $mimetype);
        return $this;
    }
    
    /*
     * 发送一个流，只支持PUT方法，在PUT大文件时使用节约内存
     */
    public function stream($fp)
    {
        $this->request->curlopts[CURLOPT_PUT] = 1;
        $this->request->curlopts[CURLOPT_INFILE] = $fp;
        $this->request->curlopts[CURLOPT_INFILESIZE] = fstat($fp)['size'];
        return $this;
    }

    /*
     * 设置单个header
     */
    public function header($name, $value)
    {
        $this->request->headers[$name] = $value;
        return $this;
    }
    
    /*
     * 设置多个header
     */
    public function headers(array $values)
    {
		$this->request->headers = isset($this->request->headers) ? $values + $this->request->headers : $values;
        return $this;
    }
	
    /*
     * 认证
     */
    public function auth($user, $pass)
    {
        $this->request->headers['Authorization'] = 'Basic '.base64_encode("$user:$pass");
        return $this;
    }
    
    /*
     * 设置请求超时时间
     */
    public function timeout($timeout)
    {
        $this->request->curlopts[CURLOPT_TIMEOUT] = (int) $timeout;
        return $this;
    }
	
    /*
     * 设置请求超时时间
     */
    public function allowRedirects($bool = true, int $max = 3)
    {
        $this->request->curlopts[CURLOPT_FOLLOWLOCATION] = $bool;
		if ($bool && $max > 0) {
			$this->request->curlopts[CURLOPT_MAXREDIRS] = $max;
		}
        return $this;
    }
    
    /*
     * 设置底层curl参数
     */
    public function curlopt($name, $value)
    {
        $this->request->curlopts[$name] = $value;
        return $this;
    }
    
    /*
     * 设置底层curl参数
     */
    public function curlopts(array $values)
    {
		$this->request->curlopts = isset($this->request->curlopts) ? $values + $this->request->curlopts : $values;
        return $this;
    }

    /*
     * 设置是否获取并解析请求响应的headers数据
     */
    public function returnHeaders($bool = true)
    {
		$this->return_headers = $bool;
        return $this;
    }
    
    /*
     * 设置debug模式
     */
    public function debug($bool = true)
    {
        $this->debug = $bool;
        return $this;
    }
    
    /*
     * 魔术方法，获取request response error信息
     */
    public function __get($name)
    {
        switch ($name) {
            case 'request':
                return $this->request;
            case 'response':
				if (!isset($this->response)) {
					$this->response();
				}
                return $this->response;
            case 'error':
                return $this->error;
        }
    }
    
    /*
     * 将请求的获得的body数据直接写入到本地文件，在body内容过大时可节约内存
     */
    public function save($file)
    {
        if (isset($this->response)) {
            return false;
        }
        if ($fp = fopen($file, 'w+')) {
            $this->request->curlopts[CURLOPT_FILE] = $fp;
            $this->setResponse(curl_exec($this->build()));
            $return = $this->response->status === 200 && $this->response->body === true;
            if ($return) {
                fclose($fp);
            } else {
                rewind($fp);
                $this->response->body = stream_get_contents($fp);
                fclose($fp);
                unlink($file);
            }
            return $return;
        }
        return $this->response = false;
    }
    
    /*
     * 获取Curl信息
     */
    public function getCurlInfo($name)
    {
		if (!isset($this->response)) {
			$this->setResponse(curl_exec($this->build()));
		}
        return curl_getinfo($this->ch, $name);
    }
    
    /*
     * 发送请求
     */
    public function response()
    {
		if (!isset($this->response)) {
			$this->setResponse(curl_exec($this->build()));
		}
		return $this->response;
    }
    
    /*
     * build请求数据
     */
    protected function build()
    {
        $ch = curl_init($this->request->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (isset($this->request->method)){
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($this->request->method));
        }
        if (isset($this->request->body)){
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->request->body);
        }
        if (isset($this->request->headers)) {
			foreach ($this->request->headers as $name => $value) {
				$headers[] = "$name: $value";
			}
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($this->debug) {
            $this->return_headers = true;
            $this->request->curlopts[CURLINFO_HEADER_OUT] = true;
        }
        if ($this->return_headers) {
			$this->request->curlopts[CURLOPT_HEADER] = true;
        }
        if (isset($this->request->curlopts)) {
            ksort($this->request->curlopts);
            curl_setopt_array($ch, $this->request->curlopts);
        }
        return $this->ch = $ch;
    }
    
    /*
     * 处理请求响应内容
     */
    protected function setResponse($content)
    {
        $this->response = new class () {
            public function json($name = null, $default = null) {
				$data = jsondecode($this->body);
				return $name === null ? $data : Arr::get($data, $name, $default);
            }
            public function __toString() {
                return $this->body;
            }
        };
        $this->response->status = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->response->body   = $content;
        if ($this->return_headers) {
			$this->response->headers = $this->getResponseHeadersFromResult($content);
        }
        if (!($this->response->status >= 200 && $this->response->status < 300)) {
            $this->setError();
        }
    }
	
    /*
     * 处理错误信息
     */
    protected function setError()
    {
        if ($this->response->status) {
            $code = $this->response->status;
            $message = Status::CODE[$code] ?? 'unknown status';
        } else {
            $code = curl_errno($this->ch);
            $message = curl_error($this->ch);
        }
        $this->error = new class ($code, $message, $this->request) {
            private $request;
            public function __construct($code, $message, $request) {
                $this->code = $code;
                $this->message = $message;
                $this->request = $request;
            }
            public function __toString() {
                return ($this->code ? "[$this->code]$this->message" : 'unknown http error')
                       .": {$this->request->method} {$this->request->url}";
            }
        };
    }
    
    /*
     * 获取headers
     */
    protected function getResponseHeadersFromResult($str)
    {
        if (is_string($str) && ($size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE))) {
			$this->response->body = substr($str, $size);
	        foreach (explode(self::EOL, substr($str, 0, $size)) as $v) {
	            $l = explode(":", $v, 2);
	            if(isset($l[1])) {
	                $k = trim($l[0]);
	                $v = trim($l[1]);
	                if (isset($headers[$k])) {
	                    if (count($headers[$k]) === 1) {
	                        $headers[$k] = [$headers[$k], $v];
	                    } else {
	                        $headers[$k][] = $v;
	                    }
	                } else {
	                    $headers[$k] = $v;
	                }
	            }
	        }
	        return $headers ?? null;
        }
    }
    
    /*
     * 设置curl文件上传
     */
    protected function setCurlFile($filepath, $filename, $mimetype)
    {
        $file = new \CURLFile(realpath($filepath));
        if (isset($mimetype)) {
            $file->setMimeType($mimetype);
        }
        if (isset($filename)) {
            $file->setPostFilename($filename);
        }
        return $file;
    }
    
    /*
     * 设置multipart协议上传
     */
    protected function setMultipartFile($name, $content, $filename, $mimetype)
    {
        if (empty($filename)) {
            $filename = $name;
        }
        if (empty($mimetype)) {
            $mimetype = 'application/octet-stream';
        }
        return implode(self::EOL, [
            '--'.$this->request->boundary,
            "Content-Disposition: form-data; name=\"$name\"; filename=\"$filename\"",
            "Content-Type: $mimetype",
            "Content-Transfer-Encoding: binary",
            '',
            (string) $content,
            "--{$this->request->boundary}--",
            ''
        ]);
    }
    
    public function __destruct()
    {
        empty($this->ch) || curl_close($this->ch);
    }
}
