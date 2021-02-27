<?php

require_once __DIR__ . "/app/logger.php";
require_once __DIR__ . "/app/config.php";

$jsonRequest = json_decode(file_get_contents('php://input'), true);
if (isset(
    $jsonRequest['applicationId'],
    $jsonRequest['status'],
    $jsonRequest['subscriberId'],
    $jsonRequest['timeStamp']
)) {
    ussdlog("Sub_notify\n".print_r($jsonRequest, true));

    if($jsonRequest['applicationId'] === app['app_id']) {
        $regex_api_timestamp = "/(?<year>^\d{4})(?<month>\d{2})(?<day>\d{2})/";
        preg_match($regex_api_timestamp, $jsonRequest['timeStamp'], $date);

        $date = $date['year']."-".$date['month']."-".$date['day'];
        $address = "tel:". $jsonRequest['subscriberId'];
        $sub_status = $jsonRequest['status'];

        $sql = "Select address from ". app['user_table'] ." WHERE address= '$address';";
        $user = getSQLdata($mysqli, $sql);

        # If the user has a complete profile but sub_status is different from current
        if (isset($user['sub_status']) && $user['sub_status'] !== $sub_status) {
            $user_db = ['sub_status'=>$sub_status];
            # If the user re-registered
            if ($user['sub_status'] === app['sub_unreg']) {
                $user_db['reg_date'] = date("Y-m-d");
            }
            updateUserDB($mysqli, $address, $user_db);
        }

        if (!isset($user['address'])) {
            $sql = "INSERT INTO ". app['user_table'] ." (address, sub_status, reg_date) VALUES ('$address', '$sub_status', '$date');";
            executeSQL($mysqli, $sql);
        } else {
            updateUserDB($mysqli, $address, ['sub_status'=>$sub_status]);
        }
    }
}

?>