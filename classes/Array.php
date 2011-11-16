<?php

class PhpIntel_Array extends PhpIntel_Object {
    public $items = array();

    public function __construct() {
        parent::__construct('array', 'array');
    }
    
    public function __toString() {
        $ret = "array(";
        foreach($this->items as $k => $v) {
            $ret .= "{$k}=>$v, ";
        }
        return rtrim($ret, ', ') . ")";
    }
}
