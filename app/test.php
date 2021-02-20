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
require_once __DIR__ . "/telco.php";
// require_once __DIR__ . "/msg_sl.php";
// require_once __DIR__ . "/vendor/autoload.php";

$address = "tel:8801866742387";
$content = '3';

weblog($_ENV['APP_NAME']);

$address = "tel:A#3B44oIza4nXQPhpIHE+g5wN7qC8t4j/uM5dTNAl2HbGqdCKP1nWafntUrvxzK1KpLNy";
$subscription  = new Subscription(app['sub_msg_url'], app['sub_status_url'], app['sub_base_url']);
// $sub_status = $subscription->getStatus(app['app_id'], app['password'], $address);
$base_size = $subscription->getBaseSize(app['app_id'], app['password']);
// weblog($sub_status);
weblog($base_size);

?>

</body>
</html>