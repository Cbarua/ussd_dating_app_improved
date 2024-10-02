<?php

require_once __DIR__ . "/app/logger.php";
require_once __DIR__ . "/app/config.php";

if (empty($_ENV['USSD']) && $_ENV['PLATFORM'] === 'ideamart') {
    require_once __DIR__ . "/app/telco.php";
    require_once __DIR__ . "/app/msg_sl.php";
}

$jsonRequest = json_decode(file_get_contents('php://input'), true);
if (isset(
    $jsonRequest['applicationId'],
    $jsonRequest['status'],
    $jsonRequest['subscriberId'],
    $jsonRequest['timeStamp']
)) {
    dashlog('Subscription Notification: ' . var_dump_ret($jsonRequest));
    if ($jsonRequest['applicationId'] === app['app_id']) {
        $regex_api_timestamp = "/(?<year>^\d{4})(?<month>\d{2})(?<day>\d{2})/";
        preg_match($regex_api_timestamp, $jsonRequest['timeStamp'], $date);

        $date = $date['year'] . "-" . $date['month'] . "-" . $date['day'];
        $address = "tel:" . $jsonRequest['subscriberId'];
        $sub_status = $jsonRequest['status'];

        $sql = "Select address, username from " . app['user_table'] . " WHERE address= '$address';";
        $user = getSQLdata($mysqli, $sql);

        # bdapps masked number change update
        if ($_ENV['PLATFORM'] === 'bdapps' && $sub_status === app['sub_unreg']) {
            updateUserDB($mysqli, $address, ['name' => '', 'username' => '', 'birthdate' => '']);
        }

        if (isset($user['address'])) {
            updateUserDB($mysqli, $address, ['sub_status' => $sub_status, 'sub_date' => $date]);
        } else {
            $sql = "INSERT INTO " . app['user_table'] . " (address, sub_status, sub_date) VALUES ('$address', '$sub_status', '$date');";
            executeSQL($mysqli, $sql);
        }

        if ($sub_status !== app['sub_unreg']) {
            if (empty($user['username'])) {
                for ($i=0; $i < 10 ; $i++) { 
                    $username = mt_rand(10000, 99999);
                    $sql = "UPDATE " . app['user_table'] . " SET username = '$username' WHERE address= '$address';";
    
                    if ($mysqli->query($sql)) {
                        dblog("Success: " . $sql);
                        break;
                    } else {
                        dblog("Error: " . $mysqli->error . "\n" . $sql);
                    }
                }
            }
            else {
                $username = $user['username'];
            }

            $sender = new SMSSender(app['sms_url'], app['app_id'], app['password']);
            $message = msg['username_info'] . $username . "\n" . msg['help_chat'];
            smslog("Message\n" . $message);
            $sender->sms($message, $address);
        }
    }
}
