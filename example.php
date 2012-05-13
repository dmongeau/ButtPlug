<?php

class Service {
	
	public $secret = 'CLE_SECRETE';
	public $debug = true;
	public $listMethods = false;
	
	public function get($data) {
		
		return $data;
	}
	
	public function error($data) {
		
		return $data;
	}
	
}

require_once 'ButtPlug.php';
ButtPlug::create(new Service());

