<?php

// Cherche l'autoloader : vendor local ou projet parent
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',       // Framework standalone (composer install dans framework/)
    __DIR__ . '/../../../autoload.php',          // Installé dans vendor/fennectra/framework/
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!defined('FENNEC_BASE_PATH')) {
    define('FENNEC_BASE_PATH', dirname(__DIR__));
}
