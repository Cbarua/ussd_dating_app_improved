<?php

require_once dirname(__DIR__) . "/vendor/autoload.php";
require_once __DIR__ . "/logger.php";

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

# Application Constants
$app_arr = array(
    # USSD Operations
    'mo_init' => "mo-init",
    'mt_cont' => "mt-cont",
    'mt_fin' => "mt-fin",

    # Subscription Keyword
    'sub_reg' => "REGISTERED",
    'sub_unreg' => "UNREGISTERED",
    'sub_pending' => "PENDING CHARGE",
    'sub_not_confirmed' => "PENDING CONFIRMATION",

    # API URLs
    'ussd_url' => $_ENV['USSD_URL'] ? $_ENV['USSD_URL'] : 'http://127.0.0.1:7000/ussd/send',
    'sms_url' => $_ENV['SMS_URL'] ? $_ENV['SMS_URL'] : 'http://127.0.0.1:7000/sms/send',
    'sub_msg_url' => $_ENV['SUB_MSG_URL'] ? $_ENV['SUB_MSG_URL'] : 'http://127.0.0.1:7000/subscription/send',
    'sub_base_url' => $_ENV['SUB_BASE_URL'] ? $_ENV['SUB_BASE_URL'] : 'http://127.0.0.1:7000/subscription/query-base',
    'sub_status_url' => $_ENV['SUB_STATUS_URL'] ? $_ENV['SUB_STATUS_URL'] : 'http://127.0.0.1:7000/subscription/getStatus',

    # App Configurations
    'reg_action' => '1',
    'version' => '1.0',
    'app_id' => $_ENV['APP_ID'] ? $_ENV['APP_ID'] : 'APP_000001', 
    'password' => $_ENV['PASSWORD'] ? $_ENV['PASSWORD'] : 'password',
    'app_name' => $_ENV['APP_NAME'] ? $_ENV['APP_NAME'] : 'Telco',
    'keyword' => $_ENV['KEYWORD'] ? $_ENV['KEYWORD'] : 'tel', 
    'ussd' => $_ENV['USSD'] ? $_ENV['USSD'] : '*213*99#',
    'sms' => $_ENV['SMS'] ? $_ENV['SMS'] : '21213',
    'sms_reg' => $_ENV['PLATFORM'] === 'bdapps' ? 'START' : 'REG',
    'sms_unreg' => $_ENV['PLATFORM'] === 'mspace' ? 'DREG' : 'UNREG',

    # DB Configurations
    'db_url' => $_ENV['DB_URL'] ? $_ENV['DB_URL'] : 'localhost',
    'db_user' => $_ENV['DB_USER'] ? $_ENV['DB_USER'] : 'root', 
    'db_password' => $_ENV['DB_PASSWORD'] ? $_ENV['DB_PASSWORD'] : '',
    'db_name' => $_ENV['DB_NAME'] ? $_ENV['DB_NAME'] : 'telco', 
    'state_table' => $_ENV['MAIN_TABLE'] ? $_ENV['MAIN_TABLE'] : 'telco_state', 
    'user_table' => $_ENV['USER_TABLE'] ? $_ENV['USER_TABLE'] : 'telco_users',
    'dash_table' => $_ENV['DASH_TABLE'] ? $_ENV['DASH_TABLE'] : 'telco_dashboard',
    'search_table' => $_ENV['SEARCH_TABLE'] ? $_ENV['SEARCH_TABLE'] : 'telco_search',
);

# Case insesitive constants are deprecated notice
define("app", $app_arr);

// Create connection
$mysqli = new mysqli(app['db_url'], app['db_user'], app['db_password'], app['db_name']);

// Check connection
if ($mysqli->connect_errno) {
  dblog("Connection failed: " . $mysqli->connect_error());
}
dblog($mysqli->host_info);

function executeSQL($mysqli, $sql) {
    $isMulti = strpos($sql, ';', strpos($sql, ';')) !== false;
    if ($isMulti) {
        if ($mysqli->multi_query($sql)) {
            dblog("Success: " . $sql);
        } else {
            dblog("Error: " . $mysqli->error . "\n" . $sql);
        }
    } elseif ($mysqli->query($sql)) {
        dblog("Success: " . $sql);
    } else {
        dblog("Error: " . $mysqli->error . "\n" . $sql);
    }
}

function getSQLdata($mysqli, $sql) {
    $result = $mysqli->query($sql);

    if ($result->num_rows > 0) {
        // multiple rows
        if ($result->num_rows > 1) {
            $rows = array();
            // multi-dimensional arrays
            while($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            dblog("Success: ".$sql."\n".print_r($rows, true));
            $result->close();
            return $rows;
        } else {
            $row = $result->fetch_assoc();
            $result->close();
            dblog("Success: ".$sql."\n".print_r($row, true));
            return $row;
        }
        
    } else {
        dblog("Error: " . $mysqli->error . "\n" . $sql);
    }
    return false;
}

function updateUserDB($mysqli, $address, $data) {
    
    $sql = "UPDATE ". app['user_table'] ." SET ";

    foreach ($data as $key => $value) {
        $sql .= "$key = '$value', ";
    }

    $sql .= "WHERE address= '$address';";
    
    $sql = substr_replace($sql, '', strrpos($sql, ','), 1);
    executeSQL($mysqli, $sql);
}

function updateSearchDB($mysqli, $address, $data) {
    
    $sql = "UPDATE ". app['search_table'] ." SET ";

    foreach ($data as $key => $value) {
        $sql .= "$key = '$value', ";
    }

    $sql .= "WHERE address= '$address';";
    
    $sql = substr_replace($sql, '', strrpos($sql, ','), 1);
    executeSQL($mysqli, $sql);
}

function updateStateDB($mysqli, $address, $stage, $flow = false) {

    $sql = "UPDATE ". app['state_table'] ." SET stage = '$stage'";

    if ($flow) {
        $sql .= ", flow = '$flow'";
    }

    $sql .= " WHERE address= '$address';";
    
    $sql = substr_replace($sql, '', strrpos($sql, ','), 0);
    executeSQL($mysqli, $sql);
}


?>