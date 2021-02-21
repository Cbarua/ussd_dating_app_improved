<?php

function validateDate($date) {
    // Check date format & named capture each date part. Returns an empty array if no match.
    $regex_date_format = "/(?<Year>[0-9]{4})\-(?<Month>[0-9]{2})\-(?<Day>[0-9]{2})/";

    preg_match($regex_date_format, $date, $birthdate);

    if (!isset($birthdate[0])) return false;

    $birthyear = intval($birthdate["Year"]);
    $currentyear = intval(date('Y'));
    $minus_century = $currentyear - 100;

    $is_valid_date = checkdate($birthdate['Month'], $birthdate['Day'], $birthdate['Year']);

    $is_valid_birthyear = $birthyear > $minus_century && $birthyear <= $currentyear;

    if (!$is_valid_date) {   
        return false;
    } 
    elseif (!$is_valid_birthyear) {
        return false;
    }

    return $date;
}

function addUser($mysqli, $address, $sub_status) {

    $sql = "Select * from ". app['user_table'] ." WHERE address= '$address';";
    $user = getSQLdata($mysqli, $sql);

    if (!validateDate($user['birthdate'])) {
        $today = date("Y-m-d");

        updateStateDB($mysqli, $address, 'name', 'Register');
        if (!isset($user['address'])) {
            $sql = "INSERT INTO ". app['user_table'] ." (address, sub_status, reg_date) VALUES ('$address', '$sub_status', '$today');";
            executeSQL($mysqli, $sql);
        } else {
            updateUserDB($mysqli, $address, ['sub_status'=>$sub_status]);
        }

        $message = msg['reg_name'];
    
    } else {
        $message = msg['main_menu'];
        updateStateDB($mysqli, $address, 'main', 'Menu');
    }
    return $message;
}

function register($stage, $address, $content, $mysqli) {
    
    switch ($stage) {
        case 'name':
            // Check Name doesn't contain numbers also more than 2 and less than 11
            $is_letter = preg_match('/^[a-z]*$/i', $content);
            $is_valid_length = strlen($content) < 11 && strlen($content) > 2;

            if($is_letter && $is_valid_length) {
                $name = strtolower($content);
                $is_username_exist = getSQLdata($mysqli, "Select username from ". app['user_table'] ." WHERE username= '$name'")['username'] !== null;

                if ($is_username_exist) {
                    $similar_usernames = getSQLdata($mysqli, "SELECT username from ". app['user_table'] ." WHERE username LIKE '$name%'"); // Using wildcard character '%'
                    $username = $name . count($similar_usernames);
                } else {
                    $username = $name;
                }
                
                $name = ucfirst($name);
                updateUserDB($mysqli, $address, ['name'=> $name, 'username'=> $username]);
                updateStateDB($mysqli, $address, 'sex');

                $message = "Hello $name!\nUsername = $username\n" . msg['reg_sex']; 
            } else {
                $message = msg['reg_name_e'];
            }
            break;
        case 'sex':
            $sex = array(
                '1' => 'male',
                '2' => 'female'
            );
            if (array_key_exists($content, $sex)) {
                updateUserDB($mysqli, $address, ['sex'=> $sex[$content]]);
                updateStateDB($mysqli, $address, 'birthdate');                
                $message = msg['reg_birthdate'];          
            } else {
                $message = msg['sex_e'] . msg['reg_sex'];
            }
            break;
        case 'birthdate':
            $birthdate = validateDate($content);

            if ($birthdate) {
                updateUserDB($mysqli, $address, ['birthdate'=> $birthdate]);
                updateStateDB($mysqli, $address, 'exit');
                
                $message['ussd'] = msg['notify'];
                $message['sms'] = msg['help'];
            }
            else {
                $message = msg['reg_birthdate_e'];
            }
            break;
            case 'exit':
                if ($content === "0") {
                    updateStateDB($mysqli, $address, "main", "Menu");
                    $message = msg['main_menu'];
                } else {
                    $message = msg['nav_e'] . msg['notify'];
                }
                break;
        default:
            $message = "App error! Line: ". __LINE__;
            break;
    }
    return $message;
}

function menu($stage, $address, $content, $mysqli) {

    $allowed_stages = ['username', 'help', 'about'];

    if (in_array($stage, $allowed_stages)) {
        if ($content === "0") {
            updateStateDB($mysqli, $address, "main");
            return msg['main_menu'];
        }
    }

    $allowed_stages[] = 'main';
    if (!in_array($stage, $allowed_stages)) {
        updateStateDB($mysqli, $address, "main");
        return msg['main_menu'];
    }
    
    switch ($stage) {
        case 'main': 
            switch ($content) {
                case '1':
                    executeSQL($mysqli, "INSERT INTO ". app['search_table'] ." (address) VALUES ('$address');");
                    updateStateDB($mysqli, $address, "sex", "Search");
                    $message = msg['search_sex'];
                    break;
                case '2':
                    $username = getSQLdata($mysqli, "Select username from ". app['user_table'] ." WHERE address= '$address';")['username'];
                    updateStateDB($mysqli, $address, "username",);
                    $message = "Username = $username\n0.back";
                    break;
                case '3':
                    updateStateDB($mysqli, $address, "help");
                    $message['sms'] = msg['help'];
                    $message['ussd'] = msg['notify'];
                    break;
                case '4':
                    $message = msg['about'];
                    updateStateDB($mysqli, $address, "about");
                    break;
                case '99':
                    $message = msg['exit'];
                    break;
                default:
                    $message = msg['nav_e'] . msg['main_menu'];
                    break;
            }
            break;
        case 'username':
            $message = msg['nav_e'] . "0.back";
            break;
        case 'help':
            $message = msg['nav_e'] . msg['notify'];
            break;
        case 'about':
            $message = msg['nav_e'] . msg['about'];
            break; 
        default:
            $message = "App error! Line: ". __LINE__;
            break;
    }
    return $message;
}

function search($stage, $address, $content, $mysqli) {

    function getUserList($mysqli, $address, $sex, $range, $offset = 0) {

        $range = explode("-", $range);
        $min_dob = date("Y-m-d", strtotime("$range[0] years ago"));
        $max_dob = date("Y-m-d", strtotime("$range[1] years ago"));
    
        $sql = "Select address, name, birthdate from ". app['user_table'] ." WHERE NOT address = '$address' AND sex ='$sex' AND
         birthdate BETWEEN '$max_dob' AND '$min_dob' LIMIT 5 OFFSET $offset";
        $users = getSQLdata($mysqli, $sql);
    
        $list = array();
        $tz  = new DateTimeZone("Asia/Colombo"); 
        // if only a user
        if (isset($users['address'])) {
            $age = DateTime::createFromFormat('Y-m-d', $users['birthdate'], $tz)           
            ->diff(new DateTime('now', $tz))
            ->y;
            $users['age'] = $age;
    
            $list[++$offset] = $users;
            return $list;
        }
        
        for ($i=0; $i < count($users); $i++) {
            $age = DateTime::createFromFormat('Y-m-d', $users[$i]['birthdate'], $tz)           
            ->diff(new DateTime('now', $tz))
            ->y;
            $users[$i]['age'] = $age;
    
            $offset++;
            $list[$offset] = $users[$i];
        }
    
        return $list;
    }
    
    function userListMessage($users) {
        $list_text = ""; 
    
        foreach ($users as $index => $user) {
            $list_text .= $index.". ".$user['name']." Age ".$user['age']."\n";
        }
    
        return $list_text;
    }
    
    function age_range($mysqli, $address, $content) {
        $input_list = [
            '1'=> "16-20",
            '2'=> "21-25",
            '3'=> "26-30",
            '4'=> "31-35",
            '5'=> "36-40",
            '6'=> "41-45",
            '7'=> "46-50",
            '8'=> "51-99"
        ];
    
        if ($content === "0") {
            updateStateDB($mysqli, $address, 'main', "Menu");
            $sql = "DELETE FROM ".app['search_table']." WHERE address = '$address'";
            executeSQL($mysqli, $sql);
            return msg['main_menu'];
        } elseif ($content === "93") {
            updateStateDB($mysqli, $address, 'sex');
            return msg['search_sex'];
        } elseif (!array_key_exists($content, $input_list)) {
            return msg['nav_e'] . msg['search_agelist'];
        }
    
        $sex = getSQLdata($mysqli, "Select sex from ". app['search_table'] ." WHERE address= '$address'")['sex'];
    
        $range = $input_list[$content];
        $range_arr = explode("-", $range);
        $min_dob = date("Y-m-d", strtotime("$range_arr[0] years ago"));
        $max_dob = date("Y-m-d", strtotime("$range_arr[1] years ago"));
    
        $total_users = getSQLdata($mysqli, "Select COUNT(name) as total from ". app['user_table'] ." WHERE NOT address = '$address' AND sex ='$sex' AND
        birthdate BETWEEN '$max_dob' AND '$min_dob'")['total'];
    
        if ($total_users == 0) {
            return "Sorry! No users. \n" . msg['search_agelist'];
        }
    
        updateSearchDB($mysqli, $address, ["age_range"=> $range, "total" => $total_users]);
        updateStateDB($mysqli, $address, 'user_list_nav');
    
        $users = getUserList($mysqli, $address, $sex, $range);
        $message = userListMessage($users);
        $total_users > 5 ? $message .= "92.more \n93.back \n0.main menu" : $message .= "93.back \n0.main menu";
    
        return $message;
    }

    function user_list_nav($mysqli, $address, $content) {
    
        if ($content === "0") {
            updateStateDB($mysqli, $address, 'main', "Menu");
            $sql = "DELETE FROM ".app['search_table']." WHERE address = $address'";
            executeSQL($mysqli, $sql);
            return msg['main_menu'];
        } 
    
        $search_data = getSQLdata($mysqli, "Select * from ". app['search_table'] ." WHERE address= '$address'");
    
        $sex = $search_data['sex'];
        $range = $search_data['age_range'];
        $total = intval($search_data['total']);
        $offset = intval($search_data['offset']);
        $users = getUserList($mysqli, $address, $sex, $range, $offset);
    
        if (array_key_exists($content, $users)) {
            $user = $users[$content];
            updateSearchDB($mysqli, $address, ['chosen_address' => $user['address']]);
            updateStateDB($mysqli, $address, 'result');
            $user_details = "Name: ".$user['name']."\nAge: ".$user['age'].".";
            $message['sms'] = $user_details;
            $message['ussd'] = $user_details."\n".msg['search_result'];
            return $message;
        }
    
        $msg_more = "92.More \n93.Back \n0.Main menu";
        $msg_back = "93.Back \n0.Main menu";
    
        // 92.more
        if ($content === "92") {
            $updated_offset = $offset + 5;
            $can_page = $total > $updated_offset;
            
            if ($can_page) {
                $users = getUserList($mysqli, $address, $sex, $range, $updated_offset);
                $message = userListMessage($users);
                
                $can_page_more = $total > $updated_offset + 5;
    
                $can_page_more ? $message .= $msg_more : $message .= $msg_back;
                
                updateSearchDB($mysqli, $address, ['offset'=> $updated_offset]);
            } else {
                $message = msg['nav_e'] . userListMessage($users);
                $message .= $msg_back;
            }
            // 93.back
        } elseif ($content === "93") {
            $updated_offset = $offset - 5;
            
            // Is offset negative. Back to agelist.
            if ($updated_offset < 0) {
                updateSearchDB($mysqli, $address, ['offset'=> 0]);
                updateStateDB($mysqli, $address, 'age_range');
                return msg['search_agelist'];
            }
    
            if ($updated_offset >= 0) {
                $users = getUserList($mysqli, $address, $sex, $range, $updated_offset);
                $message = userListMessage($users);
    
                $total <= $updated_offset + 5  ? $message .= $msg_back : $message .= $msg_more;
    
                updateSearchDB($mysqli, $address, ['offset'=> $updated_offset]);
            }
        } else {
            $message = msg['nav_e'] . userListMessage($users);
    
            $total <= $offset + 5  ? $message .= $msg_back : $message .= $msg_more;
        }
    
        return $message;
    }

    switch ($stage) {
        case 'sex':
            $sex = array(
                '1' => 'male',
                '2' => 'female'
            );
            if (array_key_exists($content, $sex)) {
                updateSearchDB($mysqli, $address, ['sex'=> $sex[$content]]);
                updateStateDB($mysqli, $address, 'age_range');                
                $message = msg['search_agelist'];          
            } else {
                $message = msg['sex_e'] . msg['search_sex'];
            }
            break;
        case 'age_range':
            $message = age_range($mysqli, $address, $content);
            break;
        case 'user_list_nav':
            $message = user_list_nav($mysqli, $address, $content);
            break;
        case 'result':
            if ($content === "1") {
                updateStateDB($mysqli, $address, 'end');
                $username = getSQLdata($mysqli, "Select username from ". app['user_table'] ." WHERE address= '$address'")['username'];
                $message['sms'] = $username ." ". msg['chat_request_sms'];
                $message['ussd'] = msg['chat_request_sent'];
            } elseif ($content === "0") {
                updateStateDB($mysqli, $address, 'main', "Menu");
                $message = msg['main_menu'];
            } else {
                $message = msg['nav_e'] . msg['search_result'];
            }
            break;
        case 'end':
            if ($content === "0") {
                updateStateDB($mysqli, $address, 'main', "Menu");
                $message = msg['main_menu'];
            } else {
                $message = msg['nav_e'] . msg['chat_request_sent'];
            }
            break;
        default:
            $message = "App error! Line: ". __LINE__;
            break;
    }
    return $message;
}

?>