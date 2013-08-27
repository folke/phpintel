<?php

class PHPIntel_Scope {
    const ARRAY_ACCESS = '->__array_access()';
    const ARRAY_CREATE = '->__array_create()';

    private $scope = array();
    private $variables = array();

    public function assign($left, $right) {
        if (!is_array($left))
            $left = self::split($left);
        if (!is_array($right))
            $right = self::split($right);
        while(count($left) > 0 && $left[count($left) - 1] == self::ARRAY_ACCESS) {
            array_pop($left);
            $right[] = self::ARRAY_CREATE;
        }
        if (empty($left) || empty($right))
            return;
        if (PHPIntel_Parser::isFunctionLocal($left[0])) {
            $local = substr($left[0], 1);
            $this->variables[$local] = $local;
        }
        $top = &$this->scope;
        foreach ($left as $l) {
            $top = &$top['k'][$l];
        }
        $top['v'][] = implode('', $right);
    }

    public function getVariables() {
        return array_values($this->variables);
    }

    public function add($a, $b) {
        $this->assign($a, $b);
        $this->assign($b, $a);
    }

    public function lookup($key) {
        //print_r($key);
        $top = &$this->scope;
        foreach ($key as $l) {
            // echo "$l\n";
            if (isset($top['k'][$l]))
                $top = &$top['k'][$l];
            else
                return array();
        }
        return isset($top['v']) ? $top['v'] : array();
    }

    public static function split($expr) {
        if (empty($expr))
            return array();
        $expr = str_replace('->', '|->', $expr);
        $expr = str_replace('::', '|::', $expr);
        return explode('|', $expr);
    }

    public function trace($var, &$traced = array(), $depth = 0) {
        $orig = $var;
        $unwrappedVar = str_replace(self::ARRAY_CREATE . self::ARRAY_ACCESS, '', $var);
        $unwrappedVar = str_replace(self::ARRAY_ACCESS . self::ARRAY_CREATE, '', $unwrappedVar);
        while($unwrappedVar != $orig) {
            $orig = $unwrappedVar;
            $unwrappedVar = str_replace(self::ARRAY_CREATE . self::ARRAY_ACCESS, '', $var);
            $unwrappedVar = str_replace(self::ARRAY_ACCESS . self::ARRAY_CREATE, '', $unwrappedVar);
        }
        if ($traced[$var] || $traced[$unwrappedVar])
            return;
        $traced[$var] = $var;
        $traced[$unwrappedVar] = $unwrappedVar;
        $var = $unwrappedVar;
        $var = self::split($var);

        for($v = 0; $v < count($var); $v++) {
            $values = $this->lookup(array_slice($var, 0, $v+1));
            foreach ($values as $val) {
                if (count($var) == $v + 1) {
                    $newVar = $val;
                } else {
                    $newVar = $val . "" . implode('', array_slice($var, $v + 1));
                }
                if (!isset($traced[$newVar]) && $depth < 10) {
                    $this->trace($newVar, $traced, $depth + 1);
                }
            }
        }

    }
}