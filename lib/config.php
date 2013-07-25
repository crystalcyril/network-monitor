<?php

if (!defined('BOOTSTRAPPED')) {
	exit('should not include this script directly');
}


class Config {

	private static $map;
	
	/**
	 * 
	 * @param unknown $key
	 */
	public static function get($key) {
		
		self::_init();
		
		return self::$map[$key];
		
	}
	
	/**
	 * 
	 */
	private static function _init() {
		
		// once only
		if (self::$map != null) return;
		
		$v = parse_ini_file(ROOT . DS . 'conf' . DS . 'config.ini');
		self::$map = $v;
		
	}
	
}