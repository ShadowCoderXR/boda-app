<?php
require 'vendor/autoload.php';
use Illuminate\Support\Str;
$u = (string)Str::uuid7();
echo $u . " -> " . $u[14] . "\n";
