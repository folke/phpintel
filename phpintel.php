<?php
ini_set('short_open_tag', true);
ini_set('memory_limit', '128M');


include 'config.inc.php';

$code = file_get_contents($argc == 2 ? $argv[1] : 'php://stdin');
try {
    $parser = PHPIntel_Parser::parse($code);
    echo json_encode($parser->toArray());
} catch (PHPParser_Error $e) {
    echo 'Parse Error: ', $e->getMessage();
    exit(1);
}
