<?php

require_once 'vendor/autoload.php';

use Shutterstock\Presto\Presto;

$a = new Presto();
$r = $a->get('http://blog.jacobemerick.com');

var_dump($r);

