<?php
// Setup PHP
date_default_timezone_set('America/Edmonton');

// Define some paths
define('CONFIG_PATH', __DIR__ . '/config');
define('LIBRARY_PATH', __DIR__ . '/library');
define('LOG_PATH', __DIR__ . '/logs');
define('TMP_PATH', __DIR__ . '/tmp');
define('VENDOR_PATH', __DIR__ . '/vendor');

// Setup autoloading
if (!file_exists(VENDOR_PATH)) {
    die("Vendor directory missing.");
}
require_once VENDOR_PATH . '/autoload.php';

// Load the config
$config = array();
$configFiles = array(
    CONFIG_PATH . '/config.yml',
    CONFIG_PATH . '/config.custom.yml'
);
$yaml = new \Symfony\Component\Yaml\Parser();
foreach ($configFiles as $file) {
    if (file_exists($file)) {
        $configData = $yaml->parse(file_get_contents($file));
        $config = array_replace_recursive($config, $configData);
    }
}

// Setup logging
$log = new \Monolog\Logger('app');
$log->pushHandler(new \Monolog\Handler\StreamHandler(LOG_PATH . '/app.log', \Monolog\Logger::WARNING));
