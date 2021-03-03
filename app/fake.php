<?php

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

        $sql .= "INSERT INTO ". app['user_table'] ." (address, name, username, birthdate, sex, sub_status, sub_date) VALUES ('$addresses[$i]', '$name', '$username$i', '$birthdates[$i]', '$sex', '$random_sub_list[$i]', '$date');";

        $log =  "address = $addresses[$i] <br>".
                "name = $name <br>".
                "username = $username$i <br>".
                "birthdate = $birthdates[$i] <br>". 
                "subStatus = $random_sub_list[$i] <br>";
        weblog($log);
    }

    executeSQL($mysqli, $sql);
}

$fake_girls = array(
    'bdapps' => array(
        array(
            'names' => array('Barsha', 'Naila', 'Rebeka', 'Faria', 'Nusrat'),
            'range'  => "16-20"
        ),
        array(
            'names' => array('Fathima', 'Rumi', 'Tahiaya'),
            'range'  => "21-25"
        ),
        array(
            'names' => array('Sonia', 'Anandhi', 'Mehejabin', 'Rubina'),
            'range'  => "26-30"
        ),
        array(
            'names' => array('Dalia', 'Munni'),
            'range'  => "31-35"
        )
    ),
    'mspace' => array(
        array(
            'names' => array('Achini', 'Vihangi', 'Monali', 'Shashi', 'Fathima'),
            'range'  => "16-20"
        ),
        array(
            'names' => array('Amalie', 'Nipuni', 'Shiromi'),
            'range'  => "21-25"
        ),
        array(
            'names' => array('Menaka', 'Kavindi', 'Pamudi', 'Gayani'),
            'range'  => "26-30"
        ),
        array(
            'names' => array('Roshini', 'Ruwani'),
            'range'  => "31-35"
        )
    )
);

function generateFakeUserSQL($data) {

    $sql = "";
    $ages = array();
    $count = count($data);
    for ($i=0; $i < $count; $i++) { 
        $names = $data[$i]['names'];

        $int = count($names);
        $birthdates = makeRandomBirthdate($int, $data[$i]['range']);

        for ($j=0; $j < $int; $j++) { 
            $address = 'tel:dummy' . generateRandomString(2);
            $name = $names[$j];
            $username = strtolower($name);
            $birthdate = $birthdates[$j];

            $tz  = new DateTimeZone("Asia/Colombo");  
            $age = DateTime::createFromFormat('Y-m-d', $birthdate, $tz)
            ->diff(new DateTime('now', $tz))
            ->y;
            $ages[] = ["name" => $name, "age" => $age];
            
            $sql .= "INSERT INTO ". app['user_table'] ."(address, name, username, birthdate, sex) VALUES ('$address', '$name', '$username', '$birthdate', 'female');\n";
        }
    }
    return [$sql, $ages];
}

?>