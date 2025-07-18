<?php

require_once __DIR__ . "/app/logger.php";
require_once __DIR__ . "/app/config.php";
require_once __DIR__ . "/app/telco.php";



$users = getSQLdata($mysqli, "SELECT address FROM paperchat_users WHERE sub_status = 'REGISTERED' AND sub_date BETWEEN '2025-06-19' AND CURDATE() ORDER BY `paperchat_users`.`sub_date` ASC LIMIT " . mt_rand(8, 14));

// echo var_dump_ret($users);

$subscription  = new Subscription(app['sub_msg_url'], app['sub_status_url'], app['sub_base_url']);

foreach ($users as $user) {
    $status = $subscription->UnregUser(app['app_id'], app['password'], $user['address']);
    echo $user['address'] . " " . $status . PHP_EOL;
}

?>