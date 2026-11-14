<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/cron/alertas';

require dirname(__DIR__) . '/public/index.php';
