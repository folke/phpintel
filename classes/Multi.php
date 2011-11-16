<?php

class PhpIntel_Multi extends PhpIntel_Object {
    public $items = array();

    public function add($obj) {
        $this->items[] = $obj;
    }

    public function &_do($name, $type) {
        foreach($this->items as &$it)
            $it->_do($name, $type);
        return $this;
    }
}
