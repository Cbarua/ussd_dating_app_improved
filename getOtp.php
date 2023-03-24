<?php

require_once __DIR__ . "/app/logger.php";
require_once __DIR__ . "/app/config.php";
require_once __DIR__ . "/app/telco.php";

[
    'subscriberId' => $subscriberId,
    'applicationMetaData' => $metaData
] = json_decode(file_get_contents('php://input'), true);

dblog($subscriberId);
dblog($metaData);

$otp = new OTP(app['otp_request_url'], app['otp_verify_url'], app['app_id'], app['password']);
$response = $otp->request($subscriberId, $metaData);

dblog($response);

# test code
$success = [
    "referenceNo" => "9476078545616789149261813965842",
    "statusDetail" => "Request was successfully processed.",
    "version" => "1.0",
    "statusCode" => "S1000"
];

$fail = [
    "statusDetail" => "user already registered",
    "version" => "1.0",
    "statusCode" => "E1351"
];

// $responses = [$success, $fail];
// $response = $responses[rand(0, 1)];
// $response = $success;
# test code

header("Content-Type: application/json");
echo json_encode($response);
exit();

?>