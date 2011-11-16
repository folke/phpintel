<?php

class PhpIntel_NodeVisitor extends PHPParser_NodeVisitorAbstract {
    public $classes = array();
    public $functions = array();
    public $properties = array();
    private $_activeClass = false; 

    public function leaveNode(PHPParser_Node $node) {
        if ($node instanceof PHPParser_Node_Stmt_Class) {
            $this->classes[] = $this->_activeClass;
            $this->_activeClass = false;
        }
    }
    
    public function enterNode(PHPParser_Node $node) {
        if ($node instanceof PHPParser_Node_Stmt_Class)
            $this->_activeClass = new PhpIntel_Class($node);
        if ($node instanceof PHPParser_Node_Stmt_ClassMethod) {
            $func = new PhpIntel_Function($node);
            if ($this->_activeClass)
                $this->_activeClass->addFunction($func);
            else
                $this->functions[] = $func;
        }
        if (($node instanceof PHPParser_Node_Const)) {
            $prop = new PhpIntel_Property($node);
            if ($this->_activeClass)
                $this->_activeClass->addProperty($prop);
            else
                $this->properties[] = $prop;
        }
        if (($node instanceof PHPParser_Node_Stmt_Property)) {
            foreach($node->props as $p) {
                $p->type = $node->type;
                $prop = new PhpIntel_Property($p);
                if ($this->_activeClass)
                    $this->_activeClass->addProperty($prop);
                else
                    $this->properties[] = $prop;
            }
        }
    }

    public function toArray() {
        $ret = array();
        foreach($this->classes as $c) {
            $ca = $c->toArray();
            $ret['classes'][$ca['name']] = $ca;
        }
        foreach($this->functions as $c) {
            $ca = $c->toArray();
            $ret['functions'][$ca['name']] = $ca;
        }
        foreach($this->properties as $c) {
            $ca = $c->toArray();
            $ret['properties'][$ca['name']] = $ca;
        }
        return $ret;
    }
}
