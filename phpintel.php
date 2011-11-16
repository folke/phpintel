<?php
include 'config.inc.php';

$parser     = new PHPParser_Parser;
$visitor = new PhpIntel_NodeVisitor;
$traverser     = new PHPParser_NodeTraverser;
$traverser->addVisitor($visitor);

$code = file_get_contents($argc == 2 ? $argv[1] : 'php://stdin');

$testing = false;
if ($testing)
$code = '
<?php
    class Test{

        public function f1() {
            $a = new T2();
            $a->c->d = new T1();
            $c = $a->c->d->test();
            return $c;
        }

        public function f2() {
            $a = new T2();
            return $a;
        }
        public function f3() {
            $a = new T3();
            $b = $a->test();
            return $b;
        }
        public function f4() {
            $a = new T4();
            $b = $a->test();
            $c = $b->xxx;
            return $c;
        }
        public function f5() {
            $a = new T4();
            $b = new T5();
            return $a->b;
        }
        public function f6() {
            $a = new T4();
            $b = new T5();
            $b->c = $a;
            return $b->c;
        }
        public function f7() {
            $a = new T4();
            $b = new T5();
            $d->xxx = $b->test()->a;
            $v = $d->xxx;
            return $v->pp;
        }
        public function f8() {
            return $this->bbb;
        }
        public function f9() {
            $a = new T1();
            return $this->a;
        }
        public function f11() {
            $a = array(new T1(), new T3());
            $a[0] = 123;
            $b = $a;
            return $b[1];
        }
        public function f10() {
            $a1 = array();
            $a1["k1"] = new T2();
            return $a1["k1"];
        }
        public function f12() {
            $a = new Util();
            $y = array("foo" => $a->find(), "cc" => 1234);
            $x = 999;
            $a->xxx->b[999][1234] = $y["foo"];
            $c = $a->xxx;
            return $c->b[$x][$y["cc"]];
        }
        public function f13(Util $a, $b) {
            $c = $a->test();
            return $c;
        }
        public function f14() {
            return array(new T1(), new T2());
        }
        public function f15(Util $a, $b) {
            return $a->test();
        }
    }
';

function assert_func($ret, $func, $expect) {
    $expect = array($expect); 
    $result = $ret['classes']['Test']['functions'][$func]['returns'];
    $rr = array();
    foreach($result as $r)
        $rr[] = "$r";
    $result = $rr;
    $f = $ret['classes']['Test']['functions'][$func];
    if ($expect != $result) {
        print_r($ret['classes']['Test']['functions'][$func]['node']);
        echo "[----] $func ==> expected {$expect[0]}, got:\n";
        foreach($result as $r)
            print_r($r);
        die("\n");
    } else
        echo "[++++] $func ==> expected {$expect[0]}\n";
}

function test($ret) {
    assert_func($ret, 'f1', '{object:T1}->test()');
    assert_func($ret, 'f2', '{object:T2}');
    assert_func($ret, 'f3', '{object:T3}->test()');
    assert_func($ret, 'f4', '{object:T4}->test()->xxx');
    assert_func($ret, 'f5', '{object:T4}->b');
    assert_func($ret, 'f6', '{object:T4}');
    assert_func($ret, 'f7', '{object:T5}->test()->a->pp');
    assert_func($ret, 'f8', '{var:this}->bbb');
    assert_func($ret, 'f9', '{var:this}->a');
    assert_func($ret, 'f10', '{object:T2}');
    assert_func($ret, 'f11', '{object:T3}');
    assert_func($ret, 'f12', '{object:Util}->find()');
    assert_func($ret, 'f13', '{object:Util}->test()');
    assert_func($ret, 'f14', 'array({scalar_lnumber:0}=>{object:T1}, {scalar_lnumber:1}=>{object:T2})');
    assert_func($ret, 'f15', '{object:Util}->test()');
}

try {
    $stmts = $parser->parse(new PHPParser_Lexer($code));
    $stmts = $traverser->traverse($stmts);
    $ret = $visitor->toArray();
    if ($testing)
        test($ret);
    else {
        foreach($ret['classes'] as $c) {
            echo $c['name'] . "\n";
            foreach($c['functions'] as $f) {
                if ($f['name'] == 'isShortUrl')
                    var_dump($f['node']);
                echo "    {$f['name']}\n";
                foreach($f['returns'] as $r)
                    echo "        [return] $r\n";
                foreach($f['assigns'] as $t => $a)
                    foreach($a as $k => $v)
                        foreach($v as $vv)
                            echo "        [assign] $t$k = $vv\n";
            }
        }
    }

} catch (PHPParser_Error $e) {
    echo 'Parse Error: ', $e->getMessage();
}
