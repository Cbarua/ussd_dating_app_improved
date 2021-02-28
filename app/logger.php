<?php

date_default_timezone_set('Asia/Colombo');

function dblog($data, $isDate = true){
    # dirname(__DIR__) returns parent directory of current directory
    $logfile = dirname(__DIR__) . '/log/db.log';
    $date = $isDate ? "[".date('D M j G:i:s T Y')."]\n" : "";
    $method = 'a';

    # Start from new after each connection
    # Godaddy server
    $host_info = ['localhost' => 'localhost via TCP/IP', 'godaddy' => 'Localhost via UNIX socket'];
    in_array($data, $host_info) ? $method = 'w': $method = 'a';

    $data = var_export($data, true);
    
    $file = fopen($logfile, $method);
    fwrite($file, $date . $data . "\n\n");
    fclose($file);
}

function ussdlog($data, $isDate = true){
    # dirname(__DIR__) returns parent directory of current directory
    $logfile = dirname(__DIR__) . '/log/ussd.log';
    $date = $isDate ? "[".date('D M j G:i:s T Y')."]\n" : "";
    $method = 'a';

    $data = var_export($data, true);

    # Start from new after each connection. Array returns true. That's why after var_export.
    strpos($data, 'mo-init') !== false ? $method = 'w': $method = 'a';
    
    $file = fopen($logfile, $method);
    fwrite($file, $date . $data . "\n\n");
    fclose($file);
}

function smslog($data, $isDate = true){
    # dirname(__DIR__) returns parent directory of current directory
    $logfile = dirname(__DIR__) . '/log/sms.log';
    $date = $isDate ? "[".date('D M j G:i:s T Y')."]\n" : "";
    $method = 'a';

    $data = var_export($data, true);

    strpos($data, 'source_address') !== false ? $method = 'w': $method = 'a';
    
    $file = fopen($logfile, $method);
    fwrite($file, $date . $data . "\n\n");
    fclose($file);
}

function weblog($data) {
    $data = nl2br($data);
    $data = var_export($data, true);
    echo $data."<br><br>";
}