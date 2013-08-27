<?php

class PHPIntel_Model_Class {
    private $name;
    private $modifiers = array();
    private $extends;
    private $methods = array();
    private $properties = array();
    private $doc;
    private $line;

    public function getName() {
        return $this->name;
    }
    
    public function toArray() {
        $ret = array(
            'name'          => $this->name,
            'extends'       => $this->extends,
            'modifiers'     => $this->modifiers,
            'doc'           => $this->doc,
            'methods'       => array(),
            'properties'    => array(),
            'line'          => $this->line,
        );
        foreach($this->methods as $f) {
            $fa = $f->toArray($this);
            $ret['methods'][$fa['name']] = $fa;
        }
        foreach($this->properties as $f) {
            $fa = $f->toArray();
            $ret['properties'][$f->getName()] = $fa;
        }
        foreach ($ret as $key => $value) {
            if (empty($ret[$key]))
                unset($ret[$key]);
        }
        return $ret;
    }

    public function __construct($node) {
        $this->line = $node->getLine();
        $this->name = $node->name;
        $this->extends = empty($node->extends->parts) ? false : implode('', $node->extends->parts);
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
        if ($node instanceof PHPParser_Node_Stmt_Interface)
            $this->modifiers[] = 'interface';
    }

    public function resolve() {
        $scope = new PHPIntel_Scope();
        $scope->assign('$this', "new {$this->name}()");
        $scope->assign('self', $this->name);
        foreach ($this->methods as $func) {
            $funcName = '$this->' . $func->getName() . '()';
            if ($func->isStatic()) {
                $funcName = 'self::' . $func->getName() . '()';
            }
            foreach($func->getReturns() as $expr) {
                if (!PHPIntel_Parser::isFunctionLocal($expr)) {
                    $expr = preg_replace('/^parent::/', "new {$this->extends}()->", $expr);
                    $scope->assign($funcName, $expr);
                }
            }
            $func->updateGlobals($scope);
        }
        $classLocals = array();
        foreach ($this->methods as $func) {
            $funcName = '$this->' . $func->getName() . '()';
            if ($func->isStatic()) {
                $funcName = 'self::' . $func->getName() . '()';
            }
            $traced = array();
            $scope->trace($funcName, $traced);
            foreach ($traced as $t => $expr) {
                if (PHPIntel_Parser::isClassLocal($expr, $this->getName()))
                    unset($traced[$t]);
            }
            $func->setReturns(array_values($traced));

            // Resolve Method Locals
            $func->updateLocals($scope, $this->getName());
            foreach ($func->getClassLocals() as $l) {
                $classLocals[$l] = $l;
            }
        }

        foreach ($this->properties as $prop) {
            $propName = '$this->' . $prop->getName();
            unset($classLocals[$prop->getName()]);
            if ($prop->isStatic()) {
                $propName = 'self::$' . $prop->getName();
            } elseif ($prop->isConstant()) {
                $propName = 'self::' . $prop->getName();
            }
            $traced = array();
            $scope->trace($propName, $traced);
            foreach ($traced as $t => $expr) {
                if (PHPIntel_Parser::isClassLocal($expr, $this->getName()))
                    unset($traced[$t]);
            }
            $prop->setReturns(array_values($traced));
            // echo "  $prop\n        [return] " . implode(', ', $traced) . "\n";
        }
    }

    public function addFunction(PHPIntel_Model_Function $f){
        $this->methods[] = $f;
    }

    public function addProperty($p){
        $this->properties[] = $p;
    }

    public function __toString() {
        return implode(' ', $this->modifiers) .
            "class " . $this->name .
            "\n    " . 
            implode("\n    ", $this->methods) .
            "\n    " . 
            implode("\n    ", $this->properties);
    }
}
