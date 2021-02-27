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

// $address = "tel:A#3B44oIza4nXQPhpIHE+g5wN7qC8t4j/uM5dTNAl2HbGqdCKP1nWafntUrvxzK1KpLNy";
$subscription  = new Subscription(app['sub_msg_url'], app['sub_status_url'], app['sub_base_url']);
// $sub_status = $subscription->getStatus(app['app_id'], app['password'], $address);
// $base_size = $subscription->getBaseSize(app['app_id'], app['password']);
// weblog($sub_status);
// weblog($base_size);


// weblog(var_dump(strtolower("12")));

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

// $today = date("Y-m-d");

// $sql = "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sex = 'male';";
// $sql .= "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sex = 'female';";
// $sql .= "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status = '".app['sub_reg']."';";
// $sql .= "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status = '".app['sub_unreg']."';";
// $sql .= "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status LIKE '%PENDING%';";
// $sql .= "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status = sub_status = '".app['sub_reg']."' AND reg_date = '$today';";
// $sql .= "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status = sub_status = '".app['sub_unreg']."' AND reg_date = '$today';";
// $sql .= "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status LIKE '%PENDING%' AND reg_date = '$today';";

// if ($mysqli->multi_query($sql)) {
//     do {
//         /* store first result set */
//         if ($result = $mysqli->store_result()) {
//             // weblog(var_dump($result));
//             while ($row = $result->fetch_row()) {
//                 weblog($row);
//             }
//             $result->free();
//         }
//         /* print divider */
//         if ($mysqli->more_results()) {
//             weblog("-----------------\n");
//         }
//     } while ($mysqli->next_result());
// }

// weblog(var_export($app_arr, true));

?>

</body>
</html>