<?php

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/telco.php";

$subscription  = new Subscription(app['sub_msg_url'], app['sub_status_url'], app['sub_base_url']);
$sql = "SELECT address, sub_status, sub_date FROM ". app['user_table'] ." WHERE NOT sub_status = '".app['sub_unreg']."' AND NOT sub_status = 'BLACKLISTED' AND NOT address LIKE '%dummy%';";
$db_data = getSQLdata($mysqli, $sql);

echo "Dashboard Update \n";

if ($db_data) {
    $rows = isset($db_data['address']) ? [$db_data] : $db_data;
    $total_rows = count($rows);
    $idx = 0;
    foreach ($rows as $row) {
        $idx++;
        $address = $row['address'];
        if (isset($row['sub_date']) && $row['sub_date'] === date('Y-m-d')) {
            echo "($idx/$total_rows) Skipping today's subscription: $address\n";
            continue; // Skip if the subscription date is today
        }

        echo "($idx/$total_rows) Checking status of $address ... ";
        $sub_status = $row['sub_status'];
        sleep(1); // Sleep for 1 second to avoid overwhelming the API
        $response = $subscription->getStatus(app['app_id'], app['password'], $address);
        $statusCode = $response['statusCode'] ?? null;
        $statusDetail = $response['statusDetail'] ?? null;

        if ($statusCode === 'S1000') {
            $new_status = $response['subscriptionStatus'] ?? null;
            if ($new_status && $sub_status !== $new_status) {
                updateUserDB($mysqli, $address, ['sub_status' => $new_status, 'sub_date' => date('Y-m-d')]);
                echo "Updated ($sub_status -> $new_status)\n";
            } else {
                echo "No Change ($sub_status)\n";
            }
        } elseif (stripos($statusDetail, 'blacklist') !== false) {
            updateUserDB($mysqli, $address, ['sub_status' => 'BLACKLISTED', 'sub_date' => date('Y-m-d')]);
            echo "Updated to BLACKLISTED\n";

        } elseif (stripos($statusDetail, 'User Already UnRegistered') !== false) {
            updateUserDB($mysqli, $address, ['sub_status' => app['sub_unreg'], 'sub_date' => date('Y-m-d')]);
            echo "Updated to UNREGISTERED\n";
        } else {
            $err_msg = $statusDetail ?? 'API Error/Timeout';
            echo "Failed: $err_msg\n";
        }
    }
}

?>