<?php

header("Content-Type: application/json");
require_once __DIR__ . "/app/logger.php";
require_once __DIR__ . "/app/config.php";
require_once __DIR__ . "/app/telco.php";

[
    'referenceNo' => $referenceNo,
    'otp' => $otp
] = json_decode(file_get_contents('php://input'), true);

otplog($otp);
otplog($referenceNo);

// test
if ($referenceNo === '9476012345616789149261813965842') {
    // $message['status'] = 'success';
    $message['status'] = 'failed';
    $response = ['statusDetail' => 'Could not find OTP', 'statusCode' => null];
    
    echo json_encode($message);
    // exit();
} else {
    # Fixed OTP PIN and OTP class instance having the same variable
    $OTP = new OTP(app['otp_request_url'], app['otp_verify_url'], app['app_id'], app['password']);
    $response = $OTP->verify($referenceNo, $otp);
    
    otplog($response);
    
    $message = ['status' => ''];
    
    if (!empty($response['subscriptionStatus'])) {
        $sub_status = $response['subscriptionStatus'];
        $address = $response['subscriberId'];
        $sub_date = date('Y-m-d');
    
        otplog("OTP subscription: $sub_status");
           
        $message['status'] = addOTPUsers($mysqli, $address, $sub_status) ?: 'failed';
        $message['subscriptionStatus'] = $response['subscriptionStatus'];
    } else {
        $message['status'] = $response['statusDetail'] ?: 'failed';
    }
    
    otplog($message);
    echo json_encode($message);
}


$subscriberId = $response['subscriberId'] ?? null;
$status = null;

if (!empty($response['subscriptionStatus'])) {
    $status = $response['subscriptionStatus'];
} else {
    $status = $response['statusDetail'];
}

$conn = $mysqli;

$conn->begin_transaction();
try {
    // Check if user already exists
    $stmt = $conn->prepare("SELECT id FROM otp_users WHERE refNo = ?");
    $stmt->bind_param("s", $referenceNo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        $response = ['status' => 'failed', 'message' => $status];
        if (!empty($subscriberId)) {
            // Update existing user's status for re-verification
            $stmt_update = $conn->prepare("UPDATE otp_users SET is_verified = 1, subscriberId = ?, otp = ?, status = ? WHERE id = ?");
            $stmt_update->bind_param("sisi", $subscriberId, $otp, $status, $user_id);
            $response['status'] = 'success';
            $response['subscriberId'] = $subscriberId;
            $stmt_update->execute();
            $stmt_update->close();
        } elseif (!empty($status)) {
            $stmt_update = $conn->prepare("UPDATE otp_users SET is_verified = 0, otp = ?, status = ? WHERE id = ?");
            $stmt_update->bind_param("isi", $otp, $status, $user_id);
            $stmt_update->execute();
            $stmt_update->close();
        }
        otplog($response);
    }
    
    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    $response = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    otplog($response);
}

exit();

?>