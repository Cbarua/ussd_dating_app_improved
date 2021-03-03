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
    ussdlog("Sub_notify\n".var_dump_ret($jsonRequest));

    if($jsonRequest['applicationId'] === app['app_id']) {
        $regex_api_timestamp = "/(?<year>^\d{4})(?<month>\d{2})(?<day>\d{2})/";
        preg_match($regex_api_timestamp, $jsonRequest['timeStamp'], $date);

        $date = $date['year']."-".$date['month']."-".$date['day'];
        $address = "tel:". $jsonRequest['subscriberId'];
        $sub_status = $jsonRequest['status'];

        $sql = "Select address from ". app['user_table'] ." WHERE address= '$address';";
        $user = getSQLdata($mysqli, $sql);

        # bdapps masked number change update
        if ($_ENV['PLATFORM'] === 'bdapps' && $sub_status === app['sub_unreg']) {
            updateUserDB($mysqli, $address, ['name' => '', 'username' => '', 'birthdate' => '']);
        }

        if (isset($user['address'])) {
            updateUserDB($mysqli, $address, ['sub_status' => $sub_status, 'sub_date' => $date]);
        } else {
            $sql = "INSERT INTO ". app['user_table'] ." (address, sub_status, sub_date) VALUES ('$address', '$sub_status', '$date');";
            executeSQL($mysqli, $sql);
        }
    }
}

?>