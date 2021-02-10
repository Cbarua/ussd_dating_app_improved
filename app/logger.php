<?php

date_default_timezone_set('Asia/Colombo');

function dblog($data, $isDate = true){
    // dirname(__DIR__) returns parent directory of current directory
    $logfile = dirname(__DIR__) . '/log/db.log';
    $date = $isDate ? "[".date('D M j G:i:s T Y')."]\n" : "";
    $method = 'a';

    // Start from new after each connection
    $data === 'localhost via TCP/IP' ? $method = 'w': $method = 'a';

    is_array($data) ? $data = print_r($data, true): $data;
    
    $file = fopen($logfile, $method);
    fwrite($file, $date . $data . "\n\n");
    fclose($file);
}

function ussdlog($data, $isDate = true){
    // dirname(__DIR__) returns parent directory of current directory
    $logfile = dirname(__DIR__) . '/log/ussd.log';
    $date = $isDate ? "[".date('D M j G:i:s T Y')."]\n" : "";
    $method = 'a';

    // Start from new after each connection
    strpos($data, 'mo-init') !== false ? $method = 'w': $method = 'a';

    is_bool($data) ? $data = var_export($data, true) : $data;
    is_array($data) ? $data = print_r($data, true): $data;
    
    $file = fopen($logfile, $method);
    fwrite($file, $date . $data . "\n\n");
    fclose($file);
}

function smslog($data, $isDate = true){
    // dirname(__DIR__) returns parent directory of current directory
    $logfile = dirname(__DIR__) . '/log/sms.log';
    $date = $isDate ? "[".date('D M j G:i:s T Y')."]\n" : "";
    $method = 'a';

    is_bool($data) ? $data = var_export($data, true) : $data;
    is_array($data) ? $data = print_r($data, true): $data;
    
    $file = fopen($logfile, $method);
    fwrite($file, $date . $data . "\n\n");
    fclose($file);
}

function weblog($data) {
    // boolean to string. Otherwise false is not printed.
    is_bool($data) ? $data = var_export($data, true) : $data;
    // print_r($data, true) to get array display data
    is_array($data) ? $data = print_r($data, true): $data = nl2br($data);
    echo $data."<br><br>";
}