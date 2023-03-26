<?php

require_once __DIR__ . "/app/logger.php";
require_once __DIR__ . "/app/config.php";
require_once __DIR__ . "/app/telco.php";
require_once __DIR__ . "/app/ussd_helper_funcs.php";

[
    'referenceNo' => $referenceNo,
    'otp' => $otp
] = json_decode(file_get_contents('php://input'), true);

otplog($otp);
otplog($referenceNo);

# Fixed OTP PIN and OTP class instance having the same variable
$OTP = new OTP(app['otp_request_url'], app['otp_verify_url'], app['app_id'], app['password']);
$response = $OTP->verify($referenceNo, $otp);

otplog($response);

$message = ['status' => ''];

// $response['subscriptionStatus'] = 'REGISTERED';
// $response['subscriberId'] = 'tel:94760785456';

if (!empty($response['subscriptionStatus'])) {
    $sub_status = $response['subscriptionStatus'];
    $address = $response['subscriberId'];
    $sub_date = date('Y-m-d');

    otplog("OTP subscription: $sub_status");
       
    $message['status'] = addOTPUsers($mysqli, $address, $sub_status) ?: 'failed';
    
} else {
    $message['status'] = $response['statusDetail'] ?: 'failed';
}

// $message['status'] = 'success';

header("Content-Type: application/json");
echo json_encode($message);
exit();

?>