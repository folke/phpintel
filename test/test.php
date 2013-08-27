<?

class Foo {
    function foo() {}
}

class Bar {
    function bar() {}
}

class Test {
    function test($arg) {
        $a = array(new Foo());
        $a[] = new Bar();
        return $a;
    }
}

$t = new Test();
$b = $t->test($arg)[0][1];
