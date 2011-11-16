<?php

class PhpIntel_Function {
    private $_name;
    private $_params = array();
    private $_access;
    private $_static = false;
    private $_final = false;
    private $_doc;
    private $_returns = array();
    private $_assigns = array();
    private $_node;

    public function toArray($klass) {
        return array(
            'name' => $this->_name,
            'params' => $this->_params,
            'access' => $this->_access,
            'static' => $this->_static,
            'final' => $this->_final,
            'doc' => $this->_doc,
            'returns' => $this->_returns,
            'assigns' => $this->_assigns,
            'node'  => $this->_node,
        );
    }

    public function __construct($node) {
        $this->_node = $node;
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
        $pp = new PHPParser_PrettyPrinter_Zend;
        if (is_array($node->params))
            foreach($node->params as $p) {
                $this->_params[$p->name] = $pp->prettyPrint(array($p));
            }
        $this->_parseNode($node);
    }

    public function __toString() {
        return ($this->_final ? 'final ': '') .
            ($this->_access !== 'default' ? "{$this->_access} ": '') .
            ($this->_static ? 'static ': '') .
            "function " . $this->_name .
            "(" . implode(', ', $this->_params) . ")";
    }

    private function _parseNode($node) {
        if (PhpIntel_FunctionVisitor::DEBUG)
            echo "\n\n\n[[[ {$node->name} ]]]\n";
        $traverser     = new PHPParser_NodeTraverser;
        $visitor = new PhpIntel_FunctionVisitor;
        $traverser->addVisitor($visitor);
        $stmts = $traverser->traverse(array($node));
        $this->_returns = $visitor->getReturns();
        foreach($visitor->getAssigns() as $k => $v) {
            if (preg_match('/(\$this->|parent::|self::)(.*)/', $k, $matches))
                $this->_assigns[$matches[1]][$matches[2]] = $v;
        }
    }
}

