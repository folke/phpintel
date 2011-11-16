<?php

class PhpIntel_Object {
    public $path = array();
    public $name;
    public $type;
    
    public function __construct($name, $type = 'var') {
        $this->name = $name;
        $this->type = $type;
    }

    public function getKey() {
        return "{{$this->type}:{$this->name}}";
    }

    public function &_do($name, $type) {
        $this->path[] = array('name' => $name, 'type' => $type, 'key' => "{{$type}:{$name}}");
        return $this;
    }

    public function &fetchProp($name) {
        return $this->_do($name, 'fetchProp');
    }
    
    public function &accessArray($name) {
        return $this->_do($name, 'accessArray');
    }

    public function &fetchStaticProp($name) {
        return $this->_do($name, 'fetchStaticProp');
    }

    public function &callMethod($name) {
        return $this->_do($name, 'callMethod');
    }

    public function &callStaticMethod($name) {
        return $this->_do($name, 'callStaticMethod');
    }

    public function __toString() {
        $ret = "{{$this->type}:{$this->name}}";
        foreach($this->path as $p) {
            switch ($p['type']) {
                case 'fetchProp': $ret .= "->{$p['name']}"; break;
                case 'fetchStaticProp': $ret .= "::{$p['name']}"; break;
                case 'callMethod': $ret .= "->{$p['name']}()"; break;
                case 'callStaticMethod': $ret .= "::{$p['name']}()"; break;
                case 'accessArray': $ret .= "[{$p['name']}]"; break;
                default: $ret .= "{{$p['type']}:{$p['name']}}"; break;
            }
        }
        return $ret;
    }
}
