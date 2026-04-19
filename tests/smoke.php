<?php

declare(strict_types=1);

$_SERVER['REQUEST_URI'] = $argv[1] ?? '/llms.txt';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_USER_AGENT'] = 'cli-smoke';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

include dirname(__DIR__) . '/index.php';
