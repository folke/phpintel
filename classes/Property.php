<?php


class PhpIntel_Property {
    private $_name;
    private $_access;
    private $_static = false;
    private $_final = false;
    private $_const;
    private $_value;
    private $_doc;
    
    public function toArray() {
        return array(
            'name' => $this->_name,
            'value' => $this->_value,
            'const' => $this->_const,
            'access' => $this->_access,
            'static' => $this->_static,
            'final' => $this->_final,
            'doc' => $this->_doc,
        );
    }

    public function __construct($node) {
        $this->_name = $node->name;
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
        if ($node instanceof PHPParser_Node_Const)
            $this->_const = true;
        $pp = new PHPParser_PrettyPrinter_Zend;
        if ($node->value)
            $this->_value = $pp->prettyPrint(array($node->value));
        if ($node->default)
            $this->_value = $pp->prettyPrint(array($node->default));
    }

    public function __toString() {
        return ($this->_final ? 'final ': '') .
            ($this->_access !== 'default' ? "{$this->_access} ": '') .
            ($this->_static ? 'static ': '') .
            ($this->_const ? 'const ': '') .
            $this->_name .
            ($this->_value ? "= " . $this->_value : '');
    }
}
