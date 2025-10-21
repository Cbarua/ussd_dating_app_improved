<?php

header("Content-Type: application/json");
require_once __DIR__ . "/app/logger.php";
require_once __DIR__ . "/app/config.php";
require_once __DIR__ . "/app/telco.php";

[
    'subscriberId' => $subscriberId,
    'applicationMetaData' => $metaData
] = json_decode(file_get_contents('php://input'), true);

otplog('New log');

if (!isset($subscriberId, $metaData)) {
    $response = ['status' => 'error','message'=> 'Invalid input'];
    otplog($response);
    echo json_encode($response);
    exit;
}

otplog($subscriberId);
otplog($metaData);

# test
if ($subscriberId === 'tel:94760123456') {
    $success = [
        "referenceNo" => "9476012345616789149261813965842",
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
    $apiResponse = $success;
    
    echo json_encode($apiResponse);
    // exit();
} else {

    $OTP = new OTP(app['otp_request_url'], app['otp_verify_url'], app['app_id'], app['password']);
    $apiResponse = $OTP->request($subscriberId, $metaData);
    
    otplog($apiResponse);
    echo json_encode($apiResponse);
}

$phone = $subscriberId;
$refNo = $apiResponse['referenceNo'] ?? null;

$mysqli->begin_transaction();
try {
    // Check if user already exists
    $stmt = $mysqli->prepare("SELECT id, times FROM otp_users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $times = $user['times'];
        $updated_times = intval($times) + 1;

        $status = 'User re-onboarded.';
        $is_verified = 0;
        if (strtolower($apiResponse['statusDetail']) === 'user already registered') {
            $status = 'ALREADY REGISTERED';
            $is_verified = 1;
        }

        $response = ['status' => 'success', 'message' => $status];

        // Update existing user's status for re-verification
        $stmt_update = $mysqli->prepare("UPDATE otp_users SET is_verified = ?, refNo = ?, status = ?, times = ? WHERE id = ?");
        $stmt_update->bind_param("issii", $is_verified, $refNo, $status, $updated_times, $user_id);
        $stmt_update->execute();
        $stmt_update->close();

        if ($response['message'] === 'User re-onboarded.') {
            $response['message'] = "{$response['message']} {$updated_times} times.";
        }
        otplog($response);
    } else {
        $os = $metaData['os'];
        $device = $metaData['device'];
        $ip = $metaData['ip'];
        $response = ['status' => 'success', 'message' => 'New user created.'];
        // Insert new user
        $stmt_insert = $mysqli->prepare("INSERT INTO otp_users (phone, os, device, ip, refNo, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("ssssss", $phone, $os, $device, $ip, $refNo, $response['message']);
        $stmt_insert->execute();
        $stmt_insert->close();
        otplog($response);
    }
    
    $mysqli->commit();

} catch (Exception $e) {
    $mysqli->rollback();
    $response = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    otplog($response);
}

exit();

?>