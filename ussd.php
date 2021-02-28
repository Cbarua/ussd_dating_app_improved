<?php

# Necessary libraries
require_once __DIR__ . "/app/config.php";          
$_ENV['PLATFORM'] === 'bdapps' ? require_once __DIR__ . "/app/msg_en.php" : require_once __DIR__ . "/app/msg_sl.php";
require_once __DIR__ . "/app/telco.php";
require_once __DIR__ . "/app/ussd_helper_funcs.php";


# Set application Header
header('Content-type: application/json');

# Instantiate necessary class
$receiver 	   = new UssdReceiver();
$ussd_sender   = new UssdSender(app['ussd_url'], app['app_id'], app['password']);
$sms_sender    = new SMSSender(app['sms_url'], app['app_id'], app['password']);
$subscription  = new Subscription(app['sub_msg_url'], app['sub_status_url'], app['sub_base_url']);


# Get USSD Request properties
$content 			= 	$receiver->getMessage();        // get the message content
$address 			= 	$receiver->getAddress();        // get the sender's address
// $request_id 			= 	$receiver->getRequestID();      // get the request ID
// $application_id 		= 	$receiver->getapplicationId();  // get application ID
// $encoding 			=	$receiver->getEncoding();       // get the encoding value
// $version 			= 	$receiver->getVersion();        // get the version
$session_id 		= 	$receiver->getSessionId();      // get the session ID
$ussd_operation 	= 	$receiver->getUssdOperation();  // get the ussd operation

ussdlog(
    // '['.date('D M j G:i:s T Y').'] '."\n".
    "ussd_operation : $ussd_operation\n".
    "source_address : $address\n".
    // "request_id     : $request_id\n".
    "session_id     : $session_id\n".
    // "application_id : $application_id\n".
    "message        : $content\n"
    // "encoding      : $encoding\n".
    // "version       : $version\n"
);

# App state
$sql = "Select * from ". app['state_table'] ." WHERE address= '$address'";
$state = getSQLdata($mysqli, $sql);

if (!isset($state['address'])) {
    $sql = "INSERT INTO ". app['state_table'] ."(address) VALUES ('$address');";
    executeSQL($mysqli, $sql);
}


if ($ussd_operation === "mo-init") {
    # User subscription status
    # mspace ussd and sms subscription required update
    $sub_status = $_ENV['PLATFORM'] === 'mspace' ? app['sub_reg'] : $subscription->getStatus(app['app_id'], app['password'], $address);
    ussdlog("Platform: ".$_ENV['PLATFORM']."\nSub url: ".app['sub_msg_url']."\nSubscription: ".var_export($sub_status, true), false);

    if ($sub_status === app['sub_unreg']) {
        $message = msg['register'];
        updateStateDB($mysqli, $address, "", "InitUSSD");
    } elseif ($sub_status === app['sub_reg']) {
        $message = addUser($mysqli, $address, $sub_status);
    } elseif ($sub_status === app['sub_pending']) {
        $message = msg['pending_e'];
    } 
    # bdapps subscription confirmation required update
    elseif ($sub_status === app['sub_not_confirmed']) {
        $message = msg['not_confirmed_e'];
    }
    # ideamart INITIAL CHARGING PENDING
    elseif (strpos($sub_status, "PENDING") !== false) {
        $message = msg['register'];
        updateStateDB($mysqli, $address, "", "InitUSSD");
    } 
    else {
        $message = "App error! Line: ". __LINE__;
    }
    
} else {

    switch ($state['flow']) {
        case 'InitUSSD':
            if ($content === "0") {
                $message = msg['exit'];
            } elseif ($content === "1") {
                $response = $subscription->RegUser(app['app_id'], app['password'], $address);

                if (isset($response['subscriptionStatus']) || $response['statusCode'] === 'S1000') {
                    $sub_status = $response['subscriptionStatus'];

                    ussdlog("Sub Status: ".$sub_status);

                    # bdapps subscription confirmation required update
                    if ($sub_status === app['sub_not_confirmed']) {
                        $message = msg['pending_confirm'];
                    } else {
                        $message = addUser($mysqli, $address, $sub_status);
                    }
                } else {
                    ussdlog("Reg User Response\n".var_export($response, true));
                    $message = "App error! Line: ". __LINE__;
                }
            } else {
                $message = msg['nav_e'] . msg['register'];
            }
            break;
        case 'Register':
            $message = register($state['stage'], $address, $content, $mysqli);
            break;
        case 'Menu':
            $message = menu($state['stage'], $address, $content, $mysqli);
            break;
        case 'Search':
            $message = search($state['stage'], $address, $content, $mysqli);
            break;
        default:
            $message = "App error! Line: ". __LINE__;
            break;
    }
}

ussdlog("Message\n".var_export($message, true));

try {
    # strpos returns NULL if an array is given. strpos($message, "App error!") !== false = true
    if (is_array($message)) {
        $ussd_sender->ussd($session_id, $message['ussd'], $address);
        $sms_sender->sms($message['sms'], $address);
    } else {
        $fin_list = [msg['exit'], msg['pending_e'], msg['not_confirmed_e'], msg['pending_confirm']];

        if (in_array($message, $fin_list)) {
            $ussd_sender->ussd($session_id, $message, $address, 'mt-fin');
        } elseif (strpos($message, "App error!") !== false) {
            $ussd_sender->ussd($session_id, $message, $address, 'mt-fin');
        } else {
            $ussd_sender->ussd($session_id, $message, $address);
        }
    }
} catch (UssdException $e) {
    ussdlog("USSD Error: {$e->getStatusCode()} | {$e->getStatusMessage()}");
}

?>