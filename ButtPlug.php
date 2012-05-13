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
	
	public $apiClass;
	public $response;
	public $data;
	
	protected $_secret;
	
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
		
		$this->apiClass = $apiClass;
		$this->_secret = $apiClass->secret;
	}
	
	public function handleRequest($url = null) {
		
		//Get query
		if(is_array($url)) $query = $url;
		else $query = $this->_getInputsFromURL($url);
		
		//Filter query
		$this->data = array();
		$namespace = $this->config['input_namespace'].'_';
		$namespaceLength = strlen($namespace);
		foreach($query as $key => $value) {
			if(substr($key,0,$namespaceLength) == $namespace) {
				$this->data[substr($key,$namespaceLength)] = $value;
			}
		}
		
		//Validate request
		if(!isset($this->data['method'])) throw new Exception('No method',400);
		if(!isset($this->data['signature'])) throw new Exception('No signature',401);
		$method = $this->data['method'];
		$signature = $this->data['signature'];
		unset($this->data['signature']);
		unset($this->data['method']);
		if(isset($this->apiClass->debug) && $this->apiClass->debug === false) {
			if(!$this->verifySignature($signature,$this->data)) throw new Exception('Invalid signature',403);
		}
		
		//Execute request
		switch($method) {
			case 'version':
				$response = self::VERSION;
			break;
			case 'listMethods':
				if(!isset($this->apiClass->listMethods) || $this->apiClass->listMethods === true) {
					$response = get_class_methods($this->apiClass);
				} else {
					throw new Exception('Method listing has been disabled',401);
				}
			break;
			
			default:
				if(!method_exists($this->apiClass,$method)) throw new Exception('Unknown method',405);
				$response = $this->apiClass->$method($this->data);
			break;
		}
		
		//Set response
		$this->response = array(
			'success' => true,
			'response' => $response
		);
		
		
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
		
		self::response($this->response);
		
	}
	
	public function errorHandler($code, $message, $file, $line) {
		
		if(!isset($this->apiClass) || (isset($this->apiClass->debug) && $this->apiClass->debug === false)) {
			self::error(new Exception('Server error',500));
		} else {
			self::error(new Exception('Server error '.$code.': '.$message.' ('.$file.':'.$line.')',500));
		}
		
	}
	
	public static function response($data) {
		
		header('Content-type: text/plain; charset="utf-8"');
		echo json_encode($data);
		exit();
		
	}
	
	public static function error($e) {
		
		self::response(array(
			'success' => false,
			'error' => array(
				'message' => is_a($e,'Exception') ? $e->getMessage():$e,
				'code' => is_a($e,'Exception') ? $e->getCode():0
			)
		));
		
	}
	
	
	
	public static function decodeData($data) {
		return is_array($data) ? $data:json_decode($data,true);
	}
	
	
	
}