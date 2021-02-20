<?php

require_once __DIR__ . "/app/logger.php";

$jsonRequest = json_decode(file_get_contents('php://input'));
if ($jsonRequest) {
    ussdlog("Sub_notify: ".print_r($jsonRequest, true));
}

?>