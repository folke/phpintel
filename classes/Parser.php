<?php

class PHPIntel_Parser extends PHPParser_NodeVisitorAbstract {
    public $classes = array();
    public $functions = array();
    public $properties = array();
    private $_activeClass = false;
    private $scope;

    public function __construct() {
        $this->scope = new PHPIntel_Scope();
    }

    public static function parse($code) {
        $t = microtime(true);
        $parser = new PHPParser_Parser(new PHPParser_Lexer_Emulative);
        $visitor = new PHPIntel_Parser;
        $traverser = new PHPIntel_Search($visitor);
        $ast = $parser->parse($code);
        $traverser->traverse($ast);
        return $visitor;
    }

    public function leaveNode(PHPParser_Node $node) {
        if ($node instanceof PHPParser_Node_Stmt_Class or $node instanceof PHPParser_Node_Stmt_Interface) {
            $this->classes[] = $this->_activeClass;
            $this->_activeClass->resolve();            
            $this->_activeClass = false;
        }
    }
    
    public function enterNode(PHPParser_Node $node) {
        if ($node instanceof PHPParser_Node_Stmt_Class or $node instanceof PHPParser_Node_Stmt_Interface)
            $this->_activeClass = new PHPIntel_Model_Class($node);
        if ($node instanceof PHPParser_Node_Stmt_ClassMethod) {
            $func = new PHPIntel_Model_Function($node);
            if ($this->_activeClass)
                $this->_activeClass->addFunction($func);
            else
                $this->functions[] = $func;
            return false;
        }
        if (($node instanceof PHPParser_Node_Const)) {
            $prop = new PHPIntel_Model_Property($node);
            if ($this->_activeClass)
                $this->_activeClass->addProperty($prop);
            else
                $this->properties[] = $prop;
        }
        if (($node instanceof PHPParser_Node_Stmt_Property)) {
            foreach($node->props as $p) {
                $p->type = $node->type;
                $prop = new PHPIntel_Model_Property($p);
                if ($this->_activeClass)
                    $this->_activeClass->addProperty($prop);
                else
                    $this->properties[] = $prop;
            }
        }

        if ($node instanceof PHPParser_Node_Expr_Assign) {
            $left = PHPIntel_Parser::simplify($node->var);
            $right = PHPIntel_Parser::simplify($node->expr);
            foreach($left as $l) {
                foreach ($right as $r) {
                    $this->scope->assign($l, $r);
                }
            }
        }

        if ($node instanceof PHPParser_Node_Stmt_Foreach){
            $left = PHPIntel_Parser::simplify($node->valueVar);
            $right = PHPIntel_Parser::simplify($node->expr);
            foreach($left as $l) {
                foreach ($right as $r) {
                    // echo "$l = $r\n";
                    $r .= PHPIntel_Scope::ARRAY_ACCESS;
                    $this->scope->assign($l, $r);
                }
            }
        }
    }

    private function _traceGlobals() {
        $ret = array();
        foreach ($this->scope->getVariables() as $local) {
            $globalTraced = array();
            $this->scope->trace("\${$local}", $globalTraced);

            foreach ($globalTraced as $expr) {
                if (!PHPIntel_Parser::isFunctionLocal($expr)) {
                    $ret[$local][] = $expr;
                }
            }
        }
        return $ret;
    }

    public function toArray() {
        $this->_traceGlobals();
        $ret = array();
        foreach($this->classes as $c) {
            $ca = $c->toArray();
            $ret['classes'][$c->getName()] = $ca;
        }
        foreach($this->functions as $c) {
            $ca = $c->toArray();
            $ret['functions'][$ca['name']] = $ca;
        }
        foreach($this->properties as $c) {
            $ca = $c->toArray();
            $ret['properties'][$ca['name']] = $ca;
        }
        foreach ($this->_traceGlobals() as $global => $expressions) {
            $ret['globals'][$global] = $expressions;
        }
        return $ret;
    }

    private static function _combine($left, $right, $format) {
        $ret = array();
        foreach ($left as $l) {
            foreach ($right as $r) {
                $ret[] = sprintf($format, $l, $r);
            }
        }
        return $ret;
    }

    public static function isFunctionLocal($expr) {
        return ($expr[0] == '$' && strpos($expr, '$this') !== 0) || strpos($expr, 'new $') === 0;
    }

    public static function isClassLocal($expr, $className = false) {
        $ret = preg_match('/^(\$this|self|parent)/', $expr);
        if ($ret || $className === false)
            return $ret;
        return strpos($expr, "new $className()->") === 0 || strpos($expr, "$className::") === 0;
    }

    public static function simplify($node) {
        if (!is_object($node)) {
            return empty($node) ? array() : array($node);
        }
        
        switch ($node->getType()) {
            case 'Expr_ArrayDimFetch':
                return self::_combine(self::simplify($node->var), array(''), "%s" . PHPIntel_Scope::ARRAY_ACCESS);
            case 'Expr_ArrayItem':
                return self::_combine(self::simplify($node->value), array(''), "%s" . PHPIntel_Scope::ARRAY_CREATE);
            case 'Expr_ClassConstFetch':
                return self::_combine(self::simplify($node->class), self::simplify($node->name), "%s::%s");
            case 'Expr_MethodCall':
                return self::_combine(self::simplify($node->var), self::simplify($node->name), '%s->%s()');
            case 'Expr_PropertyFetch':
                return self::_combine(self::simplify($node->var), self::simplify($node->name), '%s->%s');
            case 'Expr_StaticCall':
                return self::_combine(self::simplify($node->class), self::simplify($node->name), '%s::%s()');
            case 'Expr_StaticPropertyFetch':
                return self::_combine(self::simplify($node->class), self::simplify($node->name), '%s::$%s');
            case 'Expr_FuncCall':
                return array(self::simplify($node->name)[0] . '()');
            case 'Expr_Variable':
                return array('$' . $node->name);
            case 'Expr_New':
                return array('new ' . self::simplify($node->class)[0] . '()');
                break;
            case 'Name':
                return array('' . $node);
        }
        $ret = array();
        foreach(array('stmts', 'if', 'else', 'items') as $prop) {
            $attrs = $node->$prop;
            if (is_array($attrs)) {
                foreach ($attrs as $s) {
                    $children = self::simplify($s);
                    foreach ($children as $child) {
                        $ret[] = $child;
                    }
                }
            } elseif (is_object($attrs)) {
                $children = self::simplify($attrs);
                foreach ($children as $child) {
                    $ret[] = $child;
                }
            }
        }
        return $ret;
    }

}