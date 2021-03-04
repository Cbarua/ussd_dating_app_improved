<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="https://fonts.googleapis.com/css?family=IBM+Plex+Mono&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 16px;
        }
    </style>
    <title>Test</title>
</head>
<body>

<?php
# Not suitable for production. Edit php.ini and set error_log = error_log
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once __DIR__ . "/logger.php";
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/telco.php";
// require_once __DIR__ . "/msg_sl.php";
// require_once __DIR__ . "/fake.php";
// require_once dirname(__DIR__) . "/vendor/autoload.php";

// $address = "tel:8801866742387";
// $content = '3';

weblog($_ENV['APP_NAME']);

// $sender = new SMSSender(app['sms_url'], app['app_id'], app['password']);
// $message = "Kellek ho kollek oba kamathi kenek hoyaganna danma " . app['ussd'] . " dial karanna.";

// try {
//     $sender->broadcast($message);
// } catch (SMSServiceException $e) {
//     smslog($receiver->getAddress() . "\nSMS ERROR: {$e->getErrorCode()} | {$e->getErrorMessage()}"); 
// }

?>

</body>
</html>