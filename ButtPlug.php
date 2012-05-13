<?php

/**
 *
 * ButtPlug
 *
 * Really simple REST server
 *
 */
class ButtPlug {
	
	const VERSION = 0.1;
	
	protected $_apiClass;
	protected $_secret;
	
	public $request = array();
	public $response = array();
	
	public $config = array(
		'input_namespace' => 'buttplug'
	);
	
	public static function create($apiClass, $request = null, $config = array()) {
		
		try {
			
			$Plug = new self($config);
			$Plug->setApiClass($apiClass);
			$Plug->handleRequest(isset($request) ? $request:$_REQUEST);
			$Plug->sendResponse();
			
		} catch(Exception $e) {
			self::error($e);
		}
	}
	
	public function __construct($config = array()) {
		
		ini_set('display_errors', 0);
		error_reporting(E_NONE);
		set_error_handler(array($this,'errorHandler'));
		
		$this->config = array_merge($this->config,$config);
		
	}
	
	public function setApiClass($apiClass) {
		
		if(!isset($apiClass->secret) || empty($apiClass->secret)) {
			throw new Exception('Your receiver class must contains a secret key',500);
		}
		
		$this->_apiClass = $apiClass;
		$this->_secret = $apiClass->secret;
	}
	
	public function handleRequest($url = null) {
		
		//Get query
		if(is_array($url)) $query = $url;
		else $query = $this->_getInputsFromURL($url);
		
		$query = $this->_normalizeInputs($query);
		
		//Filter query by namespace
		if(!empty($this->config['input_namespace'])) {
			$data = array();
			$namespace = $this->config['input_namespace'].'_';
			$namespaceLength = strlen($namespace);
			foreach($query as $key => $value) {
				if(substr($key,0,$namespaceLength) == $namespace) {
					$data[substr($key,$namespaceLength)] = $value;
				}
			}
		} else {
			$data = $query;
		}
		
		try {
			
			//Get request
			$method = isset($data['method']) && !empty($data['method']) ? $data['method']:null;
			$signature = isset($data['signature']) && !empty($data['signature']) ? $data['signature']:null;
			$callback = isset($data['callback']) && !empty($data['callback']) ? $data['callback']:null;
			unset($data['method'],$data['signature'],$data['callback']);
			
			//Validate request
			if(empty($method)) throw new Exception('No method',400);
			if(empty($signature)) throw new Exception('No signature',401);
			if(isset($this->_apiClass->debug) && $this->_apiClass->debug === false) {
				if(!$this->verifySignature($signature,$data)) throw new Exception('Invalid signature',403);
			}
			
			//Create request
			$this->request = array(
				'method' => $method,
				'signature' => $signature,
				'callback' => isset($data['callback']) && !empty($data['callback']) ? $data['callback']:null,
				'data' => $data
			);
			
			//Execute request
			switch($this->request['method']) {
				
				//Core Methods
				case '_version':
					$response = self::VERSION;
				break;
				case '_listMethods':
					if(!isset($this->_apiClass->listMethods) || $this->_apiClass->listMethods === true) {
						$response = get_class_methods($this->_apiClass);
					} else {
						throw new Exception('Method listing has been disabled',401);
					}
				break;
				
				//API Methods
				default:
					if(!method_exists($this->_apiClass,$this->request['method'])) throw new Exception('Unknown method',405);
					$response = $this->_apiClass->{$this->request['method']}($this->request['data']);
				break;
			}
		
			//Set response
			$this->response = array(
				'method' => $this->request['method'],
				'success' => true,
				'response' => $response
			);
			
		} catch(Exception $e) {
			
			$method = isset($method) ? $method:null;
			$callback = isset($callback) ? $callback:null;
			$e = new ButtPlug_Exception($e->getMessage(),$e->getCode(),$method,$callback);
			self::error($e);
			
		}
		
		
	}
	
	protected function _normalizeInputs($query) {
		$inputs = array();
		foreach($query as $key => $value) {
			if($obj = self::isJSON($value))  {
				$inputs[$key] = $obj;
			} else {
				$inputs[$key] = $value;
			}
		}
		return $inputs;
	}
	
	protected function _getInputsFromURL($url) {
		if(strpos($url, '?') !== false) {
			$query = substr($url,strpos($url, '?')+1);
		} elseif(strpos($url, '&') !== false) {
			$query = $url;
		} else {
			throw new Exception('No request');
		}
		
		$parts = explode('&',$query);
		$inputs = array();
		foreach($parts as $part) {
			$part = explode('=',$part);
			$inputs[$part[0]] = isset($part[1]) ? $part[1]:null;
		}
		return $inputs;
	}
	
	public function verifySignature($signature,$inputs) {
		
		$request = md5(base64_encode(serialize($inputs)));
		
		if($signature != md5($this->_secret.'&'.$request)) return false;
		
		return true;
		
	}
	
	public function sendResponse() {
		
		self::response($this->response,isset($this->request['callback']) ? $this->request['callback']:null);
		
	}
	
	public function errorHandler($code, $message, $file, $line) {
		
		if(!isset($this->_apiClass) || (isset($this->_apiClass->debug) && $this->_apiClass->debug === false)) {
			self::error(new Exception('Server error',500));
		} else {
			self::error(new Exception('Server error '.$code.': '.$message.' ('.$file.':'.$line.')',500));
		}
		
	}
	
	public static function isJSON($string) {
		$obj = json_decode($string);
		return json_last_error() == JSON_ERROR_NONE ? $obj:false;
	}
	
	public static function response($data,$callback = null) {
		
		header('Content-type: text/plain; charset="utf-8"');
		if(!empty($callback)) echo $callback,'(',json_encode($data),');';
		else echo json_encode($data);
		exit();
		
	}
	
	public static function error($e) {
		
		self::response(array(
			'method' => is_a($e,'ButtPlug_Exception') ? $e->getMethod():null,
			'success' => false,
			'error' => array(
				'message' => is_a($e,'Exception') ? $e->getMessage():$e,
				'code' => is_a($e,'Exception') ? $e->getCode():0
			)
		),is_a($e,'ButtPlug_Exception') ? $e->getCallback():null);
		
	}
	
	
	
	public static function decodeData($data) {
		return is_array($data) ? $data:json_decode($data,true);
	}
	
	
	
}


class ButtPlug_Exception extends Exception {
	protected $method;
	protected $callback;
	
	public function __construct($message = '',$code = 0, $method = null, $callback = null) {
		$this->message = $message;
		$this->code = $code;
		$this->method = $method;
		$this->callback = $callback;
	}
	
	public function getMethod() {
		return $this->method;
	}
	
	public function getCallback() {
		return $this->callback;
	}
}