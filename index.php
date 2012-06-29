<?php
require_once 'ext/Slim/Slim/Slim.php';
require_once 'ext/Slim-Extras/Log Writers/TimestampLogFileWriter.php';
require_once 'lib/Config.php';
require_once 'lib/SlimOAuth.php';

$app = new Slim(array(
    // we need to disable Slim's session handling due to incompatibilies with
    // simpleSAMLphp sessions
    'session.handler' => null,
    'mode' => 'production',
    // 'mode' => 'development',
    'debug' => false,
    'log.writer' => new TimestampLogFileWriter(array('path' => 'data' . DIRECTORY_SEPARATOR . 'logs')),
));

$oauthConfig = new Config(__DIR__ . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.ini");

// OAuth
$s = new SlimOAuth($app, $oauthConfig);

$app->run();

?>
