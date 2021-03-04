<?php

# Necessary libraries
require_once __DIR__ . "/app/config.php";          
$_ENV['PLATFORM'] === 'bdapps' ? require_once __DIR__ . "/app/msg_en.php" : require_once __DIR__ . "/app/msg_sl.php";
require_once __DIR__ . "/app/telco.php";

# Set application Header
// header('Content-type: application/json');

# Instantiate necessary class
$receiver       = new SMSReceiver();
$sender         = new SMSSender(app['sms_url'], app['app_id'], app['password']);
$subscription   = new Subscription(app['sub_msg_url'], app['sub_status_url'], app['sub_base_url']);

# Get SMS Request properties
// $version        = $receiver->getVersion();
// $application_id = $receiver->getApplicationId();
$address        = $receiver->getAddress();
$content        = $receiver->getMessage();
// $request_id     = $receiver->getRequestId();
// $encoding       = $receiver->getEncoding();
// $json           = $receiver->getJson();


smslog(
    // '['.date('D M j G:i:s T Y').'] '."\n".
    "source_address : $address\n".
    // "request_id     : $request_id\n".
    // "encoding       : $encoding\n".
    // "application_id : $application_id\n".
    "message        : $content\n"
    // "version        : $version\n"
);

# User subscription status
# mspace ussd and sms subscription required update
$sub_status = $_ENV['PLATFORM'] === 'mspace' ? app['sub_reg'] : $subscription->getStatus(app['app_id'], app['password'], $address)['subscriptionStatus'];
smslog("Platform: ".$_ENV['PLATFORM']."\nSub url: ".app['sub_msg_url']."\nSubscription: ".print_r($sub_status, true), false);

$user_sql = "Select username, sub_status from ". app['user_table'] ." WHERE address= '$address'";
$user = getSQLdata($mysqli, $user_sql);

if (isset($user['sub_status']) && $user['sub_status'] !== $sub_status) {
    updateUserDB($mysqli, $address, ['sub_status' => $sub_status, 'sub_date' => $date]);
}

try {
    if ($sub_status === app['sub_reg']) {
        $parts = explode(' ', $content, 3);
        $username = $parts[1];
        $content = $parts[2];
        $message = msg['help'];

        if ($parts[0] === app['keyword']) {
    
            if(isset($username, $content)) {
    
                $address_sql = "Select address from ". app['user_table'] ." WHERE username= '$username'";
                $receiver_address  = getSQLdata($mysqli, $address_sql)['address'];

                if(is_null($user['username'])) {
                    $message = msg['username_e'];
                } 
                elseif(is_null($receiver_address)) {
                    $message = msg['chat_no_user'];
                } else {
                    $message = $user['username'].": $content";
                    $address = $receiver_address;
                }
            }  
        }
    } elseif ($sub_status === app['sub_unreg']) {
        $message = msg['subscribe_first'];
    } elseif ($sub_status === app['sub_pending']) {
        $message = msg['pending_e'];
    } elseif ($sub_status === app['sub_not_confirmed']) {
        $message = msg['not_confirmed_e'];
    } else {
        $message = "App error! Line: ". __LINE__;
    }
    
    smslog("Message\n".$message);

    $sender->sms($message, $address);  
} 
catch (SMSServiceException $e) {
    smslog($receiver->getAddress() . "\nSMS ERROR: {$e->getErrorCode()} | {$e->getErrorMessage()}"); 
}

?>