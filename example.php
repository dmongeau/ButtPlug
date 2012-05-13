<?php

class Service {
	
	public $secret = 'CLE_SECRETE';	//Secret key
	public $debug = true; 			//Debug mode, the signature is not validated
	public $listMethods = true; 	//Allow the listMethods method
	
	public function get($data) {
		
		return $data;
	}
	
	public function update($data) {
		
		return $data;
	}
	
}

require_once 'ButtPlug.php';
ButtPlug::create(new Service());

