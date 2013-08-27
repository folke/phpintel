<?php

error_reporting(E_ALL ^ E_NOTICE);
require dirname(__FILE__) . '/lib/PHP-Parser/lib/bootstrap.php';

function __autoload($class_name) {
    include $class_name . '.php';
}

spl_autoload_register(function($className){
    if (preg_match('/PHPIntel_(.*)/', $className, $matches)) {
        $parts = explode('_', $matches[1]);
        $path = "/classes/";
        if (count($parts) > 1) {
            $path .= implode('/', array_map(strtolower, array_slice($parts, 0, count($parts) - 1))) . '/';
        }
        $klass = $parts[count($parts) - 1];
        $classFile = dirname(__FILE__) . "$path$klass.php";
        if (file_exists($classFile))
            include $classFile;
    }
});
