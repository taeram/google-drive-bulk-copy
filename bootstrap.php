<?php
// Setup PHP
date_default_timezone_set('America/Edmonton');

// Define some paths
define('ROOT_PATH', realpath(__DIR__ . '/../'));
define('TMP_PATH', ROOT_PATH . '/tmp');
define('LOG_PATH', ROOT_PATH . '/logs');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('VENDOR_PATH', ROOT_PATH . '/vendor');
define('LIBRARY_PATH', ROOT_PATH . '/library');

// Setup autoloading
if (!file_exists(VENDOR_PATH)) {
    die("Vendor directory missing.");
}
require_once VENDOR_PATH . '/autoload.php';

// Load the config
$configFiles = array(
    APP_PATH . '/config/config.yml',
    APP_PATH . '/config/config.custom.yml'
);

$config = array();
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
