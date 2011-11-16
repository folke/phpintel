<?php


class PhpIntel_FunctionVisitor extends PHPParser_NodeVisitorAbstract {
    private $assigns = array();
    private $returns = array();
    const DEBUG = true;

    public function getReturns() {
        return $this->returns;
    }

    public function getAssigns() {
        return $this->assigns;
    }

    private function assign($left, $right) {
        $left = $this->createObject($left);
        $right = $this->createObject($right);

        if(self::DEBUG) echo "[assign] $left = $right\n";
        $right = $this->resolve($right);
        if(self::DEBUG) echo "[resolv] $left = $right\n";
        
        $top = &$this->assigns[$left->getKey()];
        foreach($left->path as $p)
            $top = &$top[$p['key']];
        $top['value'] = $right;

        if ($right instanceof PhpIntel_Array) {
            foreach($right->items as $k => $it) {
                $top["{accessArray:{$k}}"]['value'] = $it;
            }
        }
        if(self::DEBUG) 
            $this->dumpAssigns();
        if(self::DEBUG) echo "------\n";
    }

    private function _resolveOther($other, $obj, $p = 0) {
        if (isset($obj->path[$p]) && isset($other[$obj->path[$p]['key']]))
            return $this->_resolveOther($other[$obj->path[$p]['key']], $obj, $p + 1);
        if (isset($other['value'])) {
            $other = $other['value'];
            $ret = clone $other;
            $ret->path = array_merge($other->path, array_slice($obj->path, $p));
            return $ret;
        }
        return false;
    }
    public function resolve($obj, $depth = 0) {
        //if ($obj->type !== 'var')
        //    return $obj;
        if (isset($this->assigns[$obj->getKey()])) {
            $other = &$this->assigns[$obj->getKey()];
            $ret = $this->_resolveOther($other, $obj, 0);
            if ($ret === false)
                return $obj;
            return $this->resolve($ret, $depth + 1);
        }
        return $obj;
    }

    public function dumpAssigns($top = false, $left = '') {
        if ($top == false)
            $top = $this->assigns;
        if (isset($top['value'])) {
            echo "$left = {$top['value']}\n";
        }

        foreach($top as $k => $v) {
            if ($k == 'value')
                continue;
            $this->dumpAssigns($v, "$left{$k}");
        }
    }

    private function createObject($node) {
        if ($node instanceof PHPParser_Node_Expr_Variable)
            return new PhpIntel_Object($node->name, 'var');
        elseif ($node instanceof PHPParser_Node_Expr_PropertyFetch)
            return $this->createObject($node->var)->fetchProp($node->name);
        elseif ($node instanceof PHPParser_Node_Expr_New)
            return new PhpIntel_Object($node->class->getFirst(), 'object');
        elseif ($node instanceof PHPParser_Node_Expr_MethodCall)
            return $this->createObject($node->var)->callMethod($node->name);
        elseif ($node instanceof PHPParser_Node_Scalar)
            return new PhpIntel_Object($node->value, strtolower(substr(get_class($node), 15)));
        elseif ($node instanceof PHPParser_Node_Expr_ConstFetch)
            return new PhpIntel_Object($node->name->getFirst(), 'expr_const');
        elseif ($node instanceof PHPParser_Node_Expr_Concat) {
            $left = $this->resolve($this->createObject($node->left));
            $right = $this->resolve($this->createObject($node->right));
            return new PhpIntel_Object("$left$right", 'scalar_string');
        } elseif ($node instanceof PHPParser_Node_Expr_StaticPropertyFetch) {
            $ret = new PhpIntel_Object($node->class->getFirst(), 'class');
            return $ret->fetchStaticProp($node->name);
        } elseif ($node instanceof PHPParser_Node_Expr_Cast_Int) {
            return new PhpIntel_Object($this->resolve($this->createObject($node->expr), 'scalar_lnumber'));
        } elseif ($node instanceof PHPParser_Node_Expr_StaticCall) {
            $ret = new PhpIntel_Object($node->class->getFirst(), 'class');
            return $ret->callStaticMethod($node->name);
        } elseif ($node instanceof PHPParser_Node_Expr_FuncCall) {
            return new PhpIntel_Object($node->name->getFirst(), 'func');
        } elseif ($node instanceof PHPParser_Node_Expr_Array) {
            $ret = new PhpIntel_Array();
            $i = 0;
            foreach($node->items as $it) {
                $key = $it->key ? $this->createObject($it->key) : new PhpIntel_Object($i, 'scalar_lnumber');
                $value = $this->resolve($this->createObject($it->value));
                $ret->items["$key"] = $value;
                $i++;
            }
            return $ret;
            //return new PhpIntel_Object('', 'array');
        } elseif ($node instanceof PHPParser_Node_Expr_ArrayDimFetch) {
            // var & dim
            $var = $this->createObject($node->var);
            // TODO: fix array access
            if (false && !($var instanceof PhpIntel_Array)) {
                $a = new PhpIntel_Array();
                $a->name = $var->name;
                $a->type = $var->type;
                $a->path = $var->path;
                $var = $a;
            }
            if ($node->dim) {
                $dim = $this->createObject($node->dim);
                $dim = $this->resolve($dim);
            } else {
                $dim = '';
            }
            $var->accessArray("$dim");
            return $var;
        } elseif ($node instanceof PHPParser_Node_Expr_Ternary) {
            // TODO: fix usage of multi objects
            $if = $this->resolve($this->createObject($node->if));
            $else = $this->resolve($this->createObject($node->else));
            $ret = new PhpIntel_Multi();
            $ret->items[] = $if;
            $ret->items[] = $else;
            return $ret;
        }
        //return new PhpIntel_Object('dummy', 'var');

        print_r($node); 
        throw new Exception("Do not know how to handle " . get_class($node));
    }
    
    public function leaveNode(PHPParser_Node $node) {
        if ($node instanceof PHPParser_Node_Expr_Assign) {
            $this->assign($node->var, $node->expr);
        } elseif ($node instanceof PHPParser_Node_Stmt_Return) {
            if ($node->expr) {
                $ret = $this->createObject($node->expr);
                $rets = array($ret);
                if ($ret instanceof PhpIntel_Multi)
                    $rets = $ret->items;
                foreach($rets as $ret) {
                    if(self::DEBUG) echo "[return] $ret\n";
                    $ret = $this->resolve($ret);
                    if(self::DEBUG) echo "[return.resolv] $ret\n";
                    $this->returns[] = $ret;
                }
            }
        } elseif ($node instanceof PHPParser_Node_Param) {
            if ($node->type) {
                $this->assigns["{var:{$node->name}}"]['value'] = new PhpIntel_Object($node->type->getFirst(), 'object');
            }
        }
        return;
    }
    
    public function enterNode(PHPParser_Node $node) {
    }
}
