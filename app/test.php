<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="https://fonts.googleapis.com/css?family=IBM+Plex+Mono&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 16px;
        }
    </style>
    <title>Test</title>
</head>
<body>

<?php
require_once __DIR__ . "/logger.php";
require_once __DIR__ . "/config.php";
// require_once __DIR__ . "/msg_sl.php";
// require_once __DIR__ . "/vendor/autoload.php";

$address = "tel:8801866742387";
$content = '3';

weblog($_ENV['APP_ID']);

?>

</body>
</html>