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
# AWS Lightsail/EC2 Bitnami PHP Server
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/logger.php";
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/telco.php";
// require_once __DIR__ . "/msg_sl.php";
// require_once __DIR__ . "/vendor/autoload.php";

// $address = "tel:8801866742387";
// $content = '3';

weblog($_ENV['APP_NAME']);

function updateSub($mysqli, $subscription) {
    $addresses = getSQLdata($mysqli, "SELECT address FROM ".app['state_table']);

    foreach ($addresses as $key => $value) {
        $address = $value['address'];
        $date = date("Y-m-d");
        $sub_status = $subscription->getStatus(app['app_id'], app['password'], $address);

        $sql = "Select address from ". app['user_table'] ." WHERE address= '$address';";
        $user = getSQLdata($mysqli, $sql);

        if (!isset($user['address'])) {
            $sql = "INSERT INTO ". app['user_table'] ." (address, sub_status, reg_date) VALUES ('$address', '$sub_status', '$date');";
            executeSQL($mysqli, $sql);
        } else {
            updateUserDB($mysqli, $address, ['sub_status'=>$sub_status]);
        }
    }
}

?>

</body>
</html>