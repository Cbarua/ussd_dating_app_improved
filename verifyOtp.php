<?php

require_once __DIR__ . "/app/logger.php";
require_once __DIR__ . "/app/config.php";
require_once __DIR__ . "/app/telco.php";
require_once __DIR__ . "/app/ussd_helper_funcs.php";

[
    'referenceNo' => $referenceNo,
    'otp' => $otp
] = json_decode(file_get_contents('php://input'), true);

dblog($otp);

$otp = new OTP(app['otp_request_url'], app['otp_verify_url'], app['app_id'], app['password']);
$response = $otp->verify($referenceNo, $otp);

$message = ['status' => ''];

// $response['subscriptionStatus'] = 'true';

if (!empty($response['subscriptionStatus'])) {
    $sub_status = $response['subscriptionStatus'];
    $address = $response['subscriberId'];
    $sub_date = date('Y-m-d');

    dblog("OTP subscription: $sub_status");
    
    if (addUser($mysqli, $address, $sub_status)) {
        $message['status'] = 'success';
    }
} else {
    dblog('OTP User Response: '. var_dump_ret($response));
    $message['status'] = 'failed';
}

header("Content-Type: application/json");
echo json_encode($message);
exit();

?>