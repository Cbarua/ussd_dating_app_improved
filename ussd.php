<?php

# Necessary libraries
require_once __DIR__ . "/app/config.php";          
require_once __DIR__ . "/app/msg_sl.php";
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
// $requestId 			= 	$receiver->getRequestID();      // get the request ID
$application_id 		= 	$receiver->getapplicationId();  // get application ID
// $encoding 			=	$receiver->getEncoding();       // get the encoding value
// $version 			= 	$receiver->getVersion();        // get the version
$session_id 		= 	$receiver->getSessionId();      // get the session ID
$ussd_operation 		= 	$receiver->getUssdOperation();  // get the ussd operation

// ussdlog(
//     // '['.date('D M j G:i:s T Y').'] '."\n".
//     "ussdOperation : $ussd_operation\n".
//     "sourceAddress : $address\n".
//     // "requestId     : $requestId\n".
//     "Session_id     : $session_id\n".
//     // "applicationId : $applicationId\n".
//     "message       : $content\n"
//     // "encoding      : $encoding\n".
//     // "version       : $version\n"
// );

# App state
$sql = "Select * from ". app['state_table'] ." WHERE address= '$address'";
$state = getSQLdata($mysqli, $sql);

if (!isset($state['address'])) {
    $sql = "INSERT INTO ". app['state_table'] ."(address) VALUES ('$address');";
    executeSQL($mysqli, $sql);
    $state['flow'] = "InitUSSD";
}


if ($ussd_operation === "mo-init") {

    $sub_status = "REGISTERED";
    // $sub_status = $subscription->getStatus($application_id, $password, $address);
    // ussdlog("Subscription: ".$sub_status, false);

    if ($sub_status === app['sub_unreg']) {
        $message = msg['register'];
    } elseif ($sub_status === app['sub_reg']) {
        $message = addUser($mysqli, $address, $sub_status);
    } else {
        $message = "App error: ". __LINE__;
    }
    
} else {

    switch ($state['flow']) {
        case 'InitUSSD':
            if ($content === "0") {
                $ussd_sender->ussd($session_id, msg['exit'], $address, 'mt-fin');
            } elseif ($content === "1") {
                $sub_status = "REGISTERED";

                if ($state['address']) {
                    updateStateDB($mysqli, $address, 'main', 'Menu');
                } else {
                    $sql = "INSERT INTO ". app['state_table'] ." VALUES ('$address', 'Register', 'name');";
                    executeSQL($mysqli, $sql);
                }
                $message = addUser($mysqli, $address, $sub_status);
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

try {
    if ($message === msg['exit']) {
        $ussd_sender->ussd($session_id, $message, $address, 'mt-fin');
    }
    
    if (is_array($message)) {
        $ussd_sender->ussd($session_id, $message['ussd'], $address);
        $sms_sender->sms($message['sms'], $address);
    } else {
        $ussd_sender->ussd($session_id, $message, $address);
    }
} catch (UssdException $e) {
    ussdlog("USSD Error: {$e->getStatusCode()} | {$e->getStatusMessage()}");
}

?>