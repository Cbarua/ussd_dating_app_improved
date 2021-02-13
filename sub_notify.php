<?php

require_once __DIR__ . "/logger.php";

$jsonRequest = json_decode(file_get_contents('php://input'));
if ($jsonRequest) {
    ussdlog($jsonRequest);
}

?>