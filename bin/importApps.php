<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

use fkooman\Config\Config;
use fkooman\Json\Json;

use fkooman\OAuth\Server\PdoOAuthStorage;
use fkooman\OAuth\Server\ClientRegistration;

$config = Config::fromIniFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.ini");

$storage = new PdoOAuthStorage($config);
$storage->initDatabase();

if ($argc !== 2) {
        echo "ERROR: specify manifest file or URL to parse" . PHP_EOL;
        die();
}

$manifestFile = $argv[1];
$fileContents = file_get_contents($manifestFile);
$data = Json::decode($fileContents);
if (NULL === $data || !is_array($data)) {
        echo "ERROR: manifest seems to be in wrong format" . PHP_EOL;
        die();
}

foreach ($data as $d) {
        // go over all app entries
        if (FALSE === $storage->getClient($d['key'])) {
                echo "Adding '" . $d['name'] . "'..." . PHP_EOL;
                $x = array (
                        "id" => $d['key'],
                        "name" => $d['name'],
                        "description" => $d['description'],
                        "secret" => NULL,
                        "type" => "user_agent_based_application",
                        "icon" => $d['icons']['128'],
                        "allowed_scope" => implode(" ", $d['permissions']),
                        "redirect_uri" => $d['app']['launch']['web_url'],
                );
                $y = ClientRegistration::fromArray($x);
                $storage->addClient($y->getClientAsArray());
        }
}
