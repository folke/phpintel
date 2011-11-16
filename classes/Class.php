<?php

class PhpIntel_Class {
    private $_name;
    private $_access;
    private $_static = false;
    private $_final = false;
    private $_doc;
    private $_extends;
    private $_functions = array();
    private $_properties = array();

    public function getName() {
        return $this->_name;
    }
    
    public function toArray() {
        $ret = array(
            'name' => $this->_name,
            'access' => $this->_access,
            'static' => $this->_static,
            'final' => $this->_final,
            'extends' => $this->_extends,
            'doc' => $this->_doc,
            'functions' => array(),
            'properties' => array(),
        );
        foreach($this->_functions as $f) {
            $fa = $f->toArray($this);
            $ret['functions'][$fa['name']] = $fa;
        }
        foreach($this->_properties as $f) {
            $fa = $f->toArray();
            $ret['properties'][$fa['name']] = $fa;
        }
        return $ret;
    }

    public function __construct($node) {
        $this->_name = $node->name;
        $this->_extends = $node->extends;
        $this->_access = 'default';
        if ($node->docComment)
            $this->_doc = $node->docComment;
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_PUBLIC)
            $this->_access = 'public';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_PRIVATE)
            $this->_access = 'private';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_PROTECTED)
            $this->_access = 'protected';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_STATIC)
            $this->_static = true;
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_FINAL)
            $this->_final = true;
    }

    public function addFunction($f){
        $this->_functions[] = $f;
    }

    public function addProperty($p){
        $this->_properties[] = $p;
    }

    public function __toString() {
        return ($this->_final ? 'final ': '') .
            ($this->_access !== 'default' ? "{$this->_access} ": '') .
            ($this->_static ? 'static ': '') .
            "class " . $this->_name .
            "\n    " . 
            implode("\n    ", $this->_functions) .
            "\n    " . 
            implode("\n    ", $this->_properties);
    }
}
