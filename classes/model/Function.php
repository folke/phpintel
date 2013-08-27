<?php

class PHPIntel_Model_Function extends PHPParser_NodeVisitorAbstract {
    private $name;
    private $modifiers = array();
    private $params = array();
    private $doc;
    private $type = array();
    private $scope;
    private $classLocals = array();
    private $locals = array();
    private $line;

    public function toArray() {
        $ret = array(
            'name' => $this->name,
            'params' => $this->params,
            'modifiers' => $this->modifiers,
            'doc' => $this->doc,
            'type' => $this->type,
            'locals' => $this->locals,
            'line'  => $this->line,
        );
        foreach ($ret as $key => $value) {
            if (empty($ret[$key]))
                unset($ret[$key]);
        }
        return $ret;
    }

    public function getName() {
        return $this->name;
    }

    public function getReturns() {
        return $this->type;
    }

    public function setReturns($ret) {
        $this->type = $ret;
    }

    public function __construct($node) {
        $this->line = $node->getLine();
        $this->name = $node->name;
        $this->scope = new PHPIntel_Scope();
        if ($node->docComment)
            $this->doc = $node->docComment;
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_PUBLIC)
            $this->modifiers[] = 'public';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_ABSTRACT)
            $this->modifiers[] = 'abstract';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_PRIVATE)
            $this->modifiers[] = 'private';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_PROTECTED)
            $this->modifiers[] = 'protected';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_STATIC)
            $this->modifiers[] = 'static';
        if ($node->type & PHPParser_Node_Stmt_Class::MODIFIER_FINAL)
            $this->modifiers[] = 'final';
        $pp = new PHPParser_PrettyPrinter_Zend;
        if (is_array($node->params))
            foreach($node->params as $p) {
                $param = array(
                    'name' => $p->name,
                );
                if (!is_null($p->default))
                    $param['initial'] = rtrim($pp->prettyPrint(array($p->default)), ';');
                if (!is_null($p->type)){
                    // print_r($p->type);
                    $param['type'] = is_array($p->type->parts) ? implode('/', $p->type->parts) : $p->type ;
                    $this->scope->assign("\${$p->name}", "new {$param['type']}()");
                }
                $this->params[] = $param;
            }
        $this->_parseNode($node);
    }

    public function isStatic() {
        return in_array('static', $this->modifiers);
    }

    public function __toString() {
        return implode(' ', $this->modifiers) .
            "function " . $this->name .
            "(" . implode(', ', array_map(function($p){
                return $p['text'];
            }, $this->params)) . ")";
    }

    private function _parseNode($node) {
        $traverser = new PHPIntel_Search($this);
        $traverser->traverse(array($node));
    }

    public function enterNode(PHPParser_Node $node) {
        if ($node instanceof PHPParser_Node_Expr_Assign) {
            // if ($node->var->name == 'ccc')
            //     print_r($node);
            $left = PHPIntel_Parser::simplify($node->var);
            $right = PHPIntel_Parser::simplify($node->expr);
            foreach($left as $l) {
                foreach ($right as $r) {
                    // echo "$l = $r\n";
                    $this->scope->assign($l, $r);
                    // $this->scope->assign($r, $l);
                    if (PHPIntel_Parser::isClassLocal($l)) {
                        $this->classLocals[$l] = $l;
                    }
                }
            }
        } elseif ($node instanceof PHPParser_Node_Stmt_Return) {
            $ret = PHPIntel_Parser::simplify($node->expr);
            foreach ($ret as $r) {
                $traced = array();
                $this->scope->trace($r, $traced);
                foreach ($traced as $t) {
                    $this->type[] = $t;
                }
            }
        } elseif ($node instanceof PHPParser_Node_Param) {
            // print_r($node);
        } elseif ($node instanceof PHPParser_Node_Stmt_Foreach){
            $left = PHPIntel_Parser::simplify($node->valueVar);
            $right = PHPIntel_Parser::simplify($node->expr);
            foreach($left as $l) {
                foreach ($right as $r) {
                    // echo "$l = $r\n";
                    $r .= PHPIntel_Scope::ARRAY_ACCESS;
                    $this->scope->assign($l, $r);
                }
            }
        } else {
            return;
        }
        return false;
    }

    public function updateLocals($globalScope, $className) {
        $ret = array();
        foreach ($this->scope->getVariables() as $local) {
            $functionTraced = array();
            $this->scope->trace("\${$local}", $functionTraced);

            foreach ($functionTraced as $expr) {
                if (!PHPIntel_Parser::isFunctionLocal($expr)) {
                    $expr = preg_replace('/^parent::/', "new {$this->extends}()->", $expr);
                    $traced = array();
                    $globalScope->trace($expr, $traced);
                    foreach ($traced as $t => $expr) {
                        if (!PHPIntel_Parser::isClassLocal($expr))
                            $ret[$local][] = $expr;
                    }
                }
            }
        }
        $this->locals = $ret;
    }
    
    public function getClassLocals() {
        return array_values($this->classLocals);
    }

    public function updateGlobals($globalScope) {
        $ret = array();
        foreach ($this->classLocals as $r) {
            $traced = array();
            $this->scope->trace($r, $traced);
            foreach ($traced as $t) {
                if (!PHPIntel_Parser::isFunctionLocal($t))
                    $globalScope->assign($r, $t);
            }
        }
    }
}

