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
    "source_address : $address\n" .
    // "request_id     : $request_id\n".
    // "encoding       : $encoding\n".
    // "application_id : $application_id\n".
    "message        : $content\n"
    // "version        : $version\n"
);

# User subscription status
# mspace ussd and sms subscription required update
if ($_ENV['PLATFORM'] === 'mspace') {
    $sub_status = app['sub_reg'];
} else {
    $response = $subscription->getStatus(app['app_id'], app['password'], $address);
    if (!empty($response['subscriptionStatus'])) {
        $sub_status = $response['subscriptionStatus'];
    } else {
        smslog('Sub Status Response: ' . var_dump_ret($response));
    }
}

smslog('Platform: ' . $_ENV['PLATFORM'] .
    "\nSub url: " . app['sub_msg_url'] .
    "\nSubscription: " . var_dump_ret($sub_status), false);

$user_sql = "Select username, sub_status from " . app['user_table'] . " WHERE address= '$address'";
$user = getSQLdata($mysqli, $user_sql);

if (!empty($sub_status) && $user['sub_status'] !== $sub_status) {
    updateUserDB($mysqli, $address, ['sub_status' => $sub_status]);
}

try {
    if ($sub_status === app['sub_reg']) {

        if (is_null($user['username'])) {
            $message = empty($_ENV['USSD']) ? msg['username_e_no_ussd'] : msg['username_e'];
            smslog("Message\n" . $message);
            $sender->sms($message, $address);
            exit();
        }
        
        // set username
        if (str_contains($content, 'setname ')) {
            $parts = explode('setname ', $content, 3);
            $name = strtolower(trim($parts[1]));

            $regex_name = "/^[a-z]{3,10}$/i";

            $is_valid = preg_match($regex_name, $name) === 1;

            if ($is_valid) {
                $similar_usernames = getSQLdata($mysqli, "SELECT username from " . app['user_table'] . " WHERE username LIKE '$name%'"); // Using wildcard character '%'
                $username = $name . count($similar_usernames);
            } else {
                $username = $name;
            }

            $name = ucfirst($name);
            updateUserDB($mysqli, $address, ['name' => $name, 'username' => $username]);

            $message = msg['username_info'] . $username . "\n" . msg['help_chat'];
            smslog("Message\n" . $message);
            $sender->sms($message, $address);
            exit();
        }

        $parts = explode(' ', $content, 3);
        $username = $parts[1];
        $content = $parts[2];
        $message = empty($_ENV['USSD']) ? msg['help_chat'] : msg['help'];

        if (strtolower($parts[0]) === app['keyword']) {
            if (!empty($username) && !empty($content)) {

                $address_sql = "Select address from " . app['user_table'] . " WHERE username= '$username'";
                $receiver_address  = getSQLdata($mysqli, $address_sql)['address'];

                if (is_null($receiver_address)) {
                    $message = msg['chat_no_user'] . "\n " . msg['help_chat'];
                } else {
                    $message = $user['username'] . ": $content";
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
        $message = "App error! Line: " . __LINE__;
    }

    smslog("Message\n" . $message);

    $sender->sms($message, $address);
} catch (SMSServiceException $e) {
    smslog($receiver->getAddress() . "\nSMS ERROR: {$e->getErrorCode()} | {$e->getErrorMessage()}");
}
