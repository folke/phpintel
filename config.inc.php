<?php

require dirname(__FILE__) . '/lib/PHP-Parser/lib/bootstrap.php';

function __autoload($class_name) {
    include $class_name . '.php';
}

spl_autoload_register(function($className){
    if (preg_match('/PhpIntel_(.*)/', $className, $matches)) {
        $classFile = dirname(__FILE__) . "/classes/{$matches[1]}.php";
        if (file_exists($classFile))
            include $classFile;
    }
});
