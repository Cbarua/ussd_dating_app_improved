<?php

# Necessary libraries
require_once __DIR__ . "/app/config.php";          
require_once __DIR__ . "/app/msg_sl.php";
require_once __DIR__ . "/app/telco.php";

# Set application Header
// header('Content-type: application/json');

# Instantiate necessary class
$sender = new SMSSender(app['sms_url'], app['app_id'], app['password']);
$receiver = new SMSReceiver();

# Get SMS Request properties
$version       = $receiver->getVersion();
$application_id = $receiver->getApplicationId();
$address       = $receiver->getAddress();
$message       = $receiver->getMessage();
$request_id     = $receiver->getRequestId();
$encoding      = $receiver->getEncoding();
// $json          = $receiver->getJson();


smslog(
    // '['.date('D M j G:i:s T Y').'] '."\n".
    "sourceAddress : $address\n".
    "requestId     : $request_id\n".
    "encoding      : $encoding\n".
    "applicationId : $application_id\n".
    "message       : $message\n".
    "version       : $version\n"
);

/*********Operations***********/
try {

    $parts = explode(' ', $message, 3);
    $username = $parts[1];
    $content = $parts[2];
    $sms = msg['help'];
    
    if ($parts[0] === app['keyword']) {

        if(isset($username, $content)) {

            $address_sql = "Select address from ". app['user_table'] ." WHERE username= '$username'";
            $receiver_address  = getSQLdata($mysqli, $address_sql)['address'];

            $username_sql = "Select username from ". app['user_table'] ." WHERE address= '$address'";
            $sender_username = getSQLdata($mysqli, $username_sql)['username'];
            
            if(isset($sender_username)) {
                $sms = msg['complete_e'];
            } 
            elseif($receiver_address) {
                $sms = "From $sender_username : $content";
                $address = $receiver_address;
                // smslog("sms = $sms\n"."to ");
            }
        }  
    }

    $sender->sms($sms, $address);   
} 
catch (SMSServiceException $e) {
    // smslog($receiver->getAddress() . "\nSMS ERROR: {$e->getErrorCode()} | {$e->getErrorMessage()}"); 
}

?>