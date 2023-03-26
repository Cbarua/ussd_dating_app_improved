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
    'ussd_url' => $_ENV['USSD_URL'] ?: 'http://127.0.0.1:7000/ussd/send',
    'sms_url' => $_ENV['SMS_URL'] ?: 'http://127.0.0.1:7000/sms/send',
    'sub_msg_url' => $_ENV['SUB_MSG_URL'] ?: 'http://127.0.0.1:7000/subscription/send',
    'sub_base_url' => $_ENV['SUB_BASE_URL'] ?: 'http://127.0.0.1:7000/subscription/query-base',
    'sub_status_url' => $_ENV['SUB_STATUS_URL'] ?: 'http://127.0.0.1:7000/subscription/getStatus',
    'otp_request_url' => $_ENV['OTP_REQUEST_URL'] ?: '',
    'otp_verify_url' => $_ENV['OTP_VERIFY_URL'] ?: '',

    # App Configurations
    'reg_action' => '1',
    'version' => '1.0',
    'app_id' => $_ENV['APP_ID'] ?: 'APP_000001', 
    'password' => $_ENV['PASSWORD'] ?: 'password',
    'app_name' => $_ENV['APP_NAME'] ?: 'Telco',
    'keyword' => $_ENV['KEYWORD'] ?: 'tel', 
    'ussd' => $_ENV['USSD'] ?: '*213*99#',
    'sms' => $_ENV['SMS'] ?: '21213',
    'sms_reg' => $_ENV['PLATFORM'] === 'bdapps' ? 'START' : 'REG',
    'sms_unreg' => $_ENV['PLATFORM'] === 'mspace' ? 'DREG' : 'UNREG',

    # DB Configurations
    'db_url' => $_ENV['DB_URL'] ?: 'localhost',
    'db_user' => $_ENV['DB_USER'] ?: 'root', 
    'db_password' => $_ENV['DB_PASSWORD'] ?: '',
    'db_name' => $_ENV['DB_NAME'] ?: 'telco', 
    'state_table' => $_ENV['MAIN_TABLE'] ?: 'telco_state', 
    'user_table' => $_ENV['USER_TABLE'] ?: 'telco_users',
    'dash_table' => $_ENV['DASH_TABLE'] ?: 'telco_dashboard',
    'search_table' => $_ENV['SEARCH_TABLE'] ?: 'telco_search',
    'otp_user_table' => $_ENV['OTP_USER_TABLE'] ?: 'telco_otp_users',
    'otp_dash_table' => $_ENV['OTP_DASH_TABLE'] ?: 'telco_otp_dashboard',
);

# Case insesitive constants are deprecated notice
define("app", $app_arr);

// Create connection
$mysqli = new mysqli(app['db_url'], app['db_user'], app['db_password'], app['db_name']);

// Check connection
if ($mysqli->connect_error) {
  dblog("Connection failed: " . $mysqli->connect_error);
}
dblog($mysqli->host_info);

// Not throwing an exception in exec and get sql creates wrong output
// but my old code use this as a feature
// I need to rewrite them
// Without try ... catch block, internal server error when an exception is thrown

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
            dblog("Success: ".$sql."\n".var_dump_ret($rows));
            $result->close();
            return $rows;
        } else {
            $row = $result->fetch_assoc();
            $result->close();
            dblog("Success: ".$sql."\n".var_dump_ret($row));
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
    
    // $sql = substr_replace($sql, '', strrpos($sql, ','), 0);
    executeSQL($mysqli, $sql);
}

function updateDashDB($mysqli, $date, $data) {

    $sql = "UPDATE ". app['dash_table'] ." SET ";

    foreach ($data as $key => $value) {
        $sql .= "$key = '$value', ";
    }

    $sql .= "WHERE date= '$date';";
    
    $sql = substr_replace($sql, '', strrpos($sql, ','), 1);
    executeSQL($mysqli, $sql);
}

function updateDB($db, $mysqli, $address, $data) {
    
    $sql = "UPDATE ". $db ." SET ";

    foreach ($data as $key => $value) {
        $sql .= "$key = '$value', ";
    }

    $sql .= "WHERE address= '$address';";
    
    $sql = substr_replace($sql, '', strrpos($sql, ','), 1);
    executeSQL($mysqli, $sql);
}

function curry($f, ...$argsCurried)
{
    return function (...$args) use ($f, $argsCurried) {
        $finalArgs = array_merge($argsCurried, $args);
        return call_user_func_array($f, $finalArgs);
    };
}

// Currying like this creates variable scope problem
$updateOTPUserDB = curry('updateDB', app['otp_user_table']);

function addOTPUsers($mysqli, $address, $sub_status) {
    $sql = "Select * from ". app['otp_user_table'] ." WHERE address = '$address';";
    $user = getSQLdata($mysqli, $sql);
    $date = date("Y-m-d");

    if(!$user) {
        $sql = "INSERT INTO ". app['otp_user_table'] ." (address, sub_status, sub_date) VALUES ('$address', '$sub_status', '$date');";
        executeSQL($mysqli, $sql);
    }

    # If the user has a complete profile but sub_status is different from current
    if (isset($user['sub_status']) && $user['sub_status'] !== $sub_status) {
        updateDB(app['otp_user_table'], $mysqli, $address, ['sub_status' => $sub_status, 'sub_date' => $date]);
    }

    // Without error handling, the output is wrong
    if ($mysqli->error) {
        return 'failed';
    }
    return 'success';
}

?>