<?php

class PHPIntel_Model_Property {
    private $name;
    private $modifiers = array();
    private $value;
    private $doc;
    private $type;
    private $line;

    public function __construct($node) {
        $this->line = $node->getLine();
        $this->name = $node->name;
        if ($node->docComment)
            $this->doc = $node->docComment;
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_PUBLIC)
            $this->modifiers[] = 'public';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_PRIVATE)
            $this->modifiers[] = 'private';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_PROTECTED)
            $this->modifiers[] = 'protected';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_STATIC)
            $this->modifiers[] = 'static';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_FINAL)
            $this->modifiers[] = 'final';
        if ($node instanceof PHPParser_Node_Const)
            $this->modifiers[] = 'const';
        $pp = new PHPParser_PrettyPrinter_Zend;
        if ($node->value)
            $this->value = rtrim($pp->prettyPrint(array($node->value)), ';');
        if ($node->default)
            $this->value = rtrim($pp->prettyPrint(array($node->default)), ';');
    }

    public function setReturns($ret) {
        $this->type = $ret;
    }

    public function isStatic() {
        return in_array('static', $this->modifiers);
    }

    public function isConstant() {
        return in_array('const', $this->modifiers);
    }

    public function getName() {
        return $this->name;
    }

    public function __toString() {
        return implode(' ', $this->modifiers) .
            $this->name .
            ($this->value ? " = " . $this->value : '') . ';';
    }

    public function toArray() {
        $ret = array(
            'name'  => $this->name,
            'type' => $this->type,
            'modifiers' => $this->modifiers,
            'value' => $this->value,
            'doc' => $this->_doc,
            'line'  => $this->line,
        );
        foreach ($ret as $key => $value) {
            if (empty($ret[$key]))
                unset($ret[$key]);
        }
        return $ret;
    }
}
