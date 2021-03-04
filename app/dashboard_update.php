<?php

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/telco.php";

$subscription  = new Subscription(app['sub_msg_url'], app['sub_status_url'], app['sub_base_url']);
$sql = "SELECT address, sub_status FROM ". app['user_table'] ." WHERE NOT sub_status = '".app['sub_unreg']."'";
$db_data = getSQLdata($mysqli, $sql);

dashlog("Dashboard Update");

for ($i=0; $i < count($db_data); $i++) {
    $address = $db_data[$i]['address']; 
    $sub_status = $db_data[$i]['sub_status'];
    $response = $subscription->getStatus(app['app_id'], app['password'], $address);

    if ($response['statusCode'] === 'S1000' && $sub_status !== $response['subscriptionStatus']) {
        updateUserDB($mysqli, $address, ['sub_status' => $response['subscriptionStatus']]);

        dashlog($address);
        dashlog("Previous Sub Status: " . $sub_status);
        dashlog("Updated Sub Status: " . $response['subscriptionStatus']);
    }
}

require_once dirname(__DIR__) . "/dashboard.php";

?>