<?php

// the root directory
define('ROOT', dirname(__FILE__));

// directory separator
define('DS', '/');

// data directory
define('DATA_DIR', ROOT . DS . 'data' . DS);

// library directory.
define('LIB_DIR', ROOT . DS . 'lib' . DS);

// configuration directory.
define('CONF_DIR', ROOT . DS . 'conf' . DS);


// marker to know it is bootstrapped.
define('BOOTSTRAPPED', 1);

/**
 * Include an library.
 * 
 * @param string $name name of the library. e.g. nmap.
 */
function require_lib($name) {
	require_once(LIB_DIR . $name . '.php');
}


// Turn off all error reporting
//error_reporting( 0 );
