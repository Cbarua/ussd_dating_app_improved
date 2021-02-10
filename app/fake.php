<?php

require_once __DIR__ . "/logger.php";
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/msg_sl.php";
require_once dirname(__DIR__) . "/vendor/autoload.php";

function generateRandomString($length = 10) {
    $x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle(str_repeat($x, ceil($length/strlen($x)) )),1,$length);
}

function makeRandomAddress($int, $is_str = false) {
    $addressArr = array();
    for($i = 0; $i < $int; $i++) {
        $addressArr[] = $is_str ? 'tel:'.generateRandomString() : 'tel:88018' . rand(0, 9999999);
    }
    return $addressArr;
}

function makeRandomBirthdate($int, $range = false) {
    $range = $range ? explode("-", $range) : [18, 25];
    $birthdates = array();
    for($i = 0; $i < $int; $i++) {
        $min = strtotime($range[1]." years ago");
        $max = strtotime($range[0]." years ago");
        $rand_time = mt_rand($min, $max);
        $birthdate = date("Y-m-d", $rand_time);
        $birthdates[] = $birthdate;
    }
    return $birthdates;
}

function getRandomSubStatus($int) {
    $subArr = array(app['sub_reg'], app['sub_unreg'], app['sub_pending']);
    $randomSubArr = array();
    for($i = 0; $i < $int; $i++) {
        $randomSubArr[] = $subArr[rand(0, 2)];
    }
    return $randomSubArr;
}

function addFakeUsers($mysqli, $int = 10, $range = false, $sex = 'female') {

    $faker = Faker\Factory::create();
    $addresses = makeRandomAddress($int);
    $birthdates = makeRandomBirthdate($int, $range);
    $random_sub_list = getRandomSubStatus($int);
    $username = strtolower($faker->firstName($sex));
    $date = date("Y-m-d");
    $sql = "";

    weblog('Fake users');

    for($i = 0; $i < $int; $i++) {
        // Generate fake name
        $name = $faker->firstName($sex);

        $sql .= "INSERT INTO ". app['user_table'] ." (address, name, username, birthdate, sex, sub_status, reg_date) VALUES ('$addresses[$i]', '$name', '$username$i', '$birthdates[$i]', '$sex', '$random_sub_list[$i]', '$date');";

        $log =  "address = $addresses[$i] <br>".
                "name = $name <br>".
                "username = $username$i <br>".
                "birthdate = $birthdates[$i] <br>". 
                "subStatus = $random_sub_list[$i] <br>";
        weblog($log);
    }

    executeSQL($mysqli, $sql);
}

// weblog(generateRandomString());
// weblog(makeRandomAddress(10, true));
// weblog(makeRandomBirthdate(10));
// weblog(getRandomSubStatus(10));
addFakeUsers($mysqli, 4, "25-30");
// weblog(__FILE__);

?>