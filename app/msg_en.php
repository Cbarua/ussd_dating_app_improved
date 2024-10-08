<?php

# Example names
$example_names = $_ENV['PLATFORM'] === 'bdapps' ? array('radhika', 'chinmoy', 'oyshi') : array('dinuka', 'sameera', 'nuwan');


# English Message Constants
$msg_arr = array(
        'register' =>   "Welcome to ". app['app_name'] .
                        "\nRegister to find your love" .
                        "\n1.Register".
                        "\n0.Exit",

        'exit' =>       "Thank you for using ".app['app_name']." USSD service. ".
                        "Please come again to ".app['app_name'].".",

        'main_menu' =>  "1.Find a new friend".
                        "\n2.Get your profile".
                        "\n3.Help".
                        "\n4.About us".
                        "\n99.Exit",

        'about' =>      "Developer: cbarua19@gmail.com" . 
                        "\n0.Back",

        'help' =>       "Welcome to ". app['app_name'] . " USSD Application.\n" . 
                        "Thank you for joining " . app['app_name']. ".\n" . 
                        "How to use USSD application...\n" . 
                        "Dial " .app['ussd']. " and press 1 to find a new friend.\n" . 
                        "Your friends can also chat with you by dialing ".app['ussd'].".\n".
                        "***How to chat***\n" . 
                        app['keyword'] . "<space>friend's username<space>type your message and then send to ". app['sms'] . ".\n" .  
                        "Example:-". app['keyword'] . " $example_names[2] how are you? Send to " . app['sms'],

        'help_chat' =>  "***How to chat***\n" . 
                        app['keyword'] . "<space>friend's username<space>type your message and then send to ". app['sms'] . ".\n" .  
                        "Example:-". app['keyword'] . " $example_names[2] how are you? Send to " . app['sms'],

        'notify' =>     "Follow the instructions you got from the SMS.\n0.Main menu",

        # Register User Messages
        'reg_name' =>   "Welcome to ". app['app_name'] . " USSD Application." . 
                        "\nNow you can find your love with ease.".
                        "\nPlease enter your name.",

        'reg_name_int' =>       "Welcome to ". app['app_name'] . " USSD Application." . 
                                "\nNow you can find your love with ease.".
                                "\nPlease enter your name or a number.",

        'reg_name_e' => "Your name is not valid. Please enter your correct name.\nExample: $example_names[0], $example_names[1], $example_names[2]",

        'reg_name_int_e' => "Input Error!\nPlease enter your name or a number.",

        'reg_sex' =>    "Are you\n" .
                        "1. Male\n" .
                        "2. Female\n" .
                        "Please choose the suitable number.",

        'sex_e' =>      "Please choose 1 or 2.\n",

        'reg_birthdate' => "Please enter your birthdate.\n" . 
                           "(Example:1995-01-01)",

        'reg_birthdate_e' =>    "The date you entered is wrong.\n".
                                "Please enter your correct birthdate.\n".
                                "(Example:1995-01-01)",


        # Search User Configurations
        'search_sex' =>         "Are you looking for \n1.Male\n2.Female\n0.Main menu",

        'search_agelist' =>     "1.Age 16-20\n" .
                                "2.Age 21-25\n" . 
                                "3.Age 26-30\n" . 
                                "4.Age 31-35\n" . 
                                "5.Age 36-40\n" . 
                                "6.Age 41-45\n" . 
                                "7.Age 46-50\n" . 
                                "8.Age 51-99\n" .
                                "93.Back\n" .
                                "0.Main menu",

        'search_result' =>      "You will get notify via SMS shortly.".
                                "\n1.Request to chat".
                                "\n0.Main menu",

        # Chat 
        'chat_request_sent' =>  "Chat Request is sent. If he or she is willing to chat with you, you will get notify via SMS.\n0.Main menu",

        'chat_request_sms' =>   "wants to chat with you.",

        'chat_no_user' =>       "User not found. Please check the username.",
        
        # Others
        'nav_e' =>              "Invalid input!\n",

        'username_e' =>         "You don't have a username. Please dial ". app['ussd'],

        'username_e_no_ussd' => "You don't have a username. \nPlease enter your name. \n".
                                app['keyword'] . "<space>setname<space><Your name> and then send to ". app['sms'] . "\n" .
                                "Example:-". app['keyword'] . " setname $example_names[1] \n and then send to " . app['sms'],

        'username_info' =>      "Your username is ",
                                
        'pending_e' =>          "You don't have sufficient balance.\n".
                                "Please top-up your balance.",

        'subscribe_first' =>    "Please subscribe first to use ". app['app_name']. 
                                ".\n" . app['sms_reg'] ."  ". app['keyword'] ." send to ". app['sms'],
        
        'not_confirmed_e' =>    "Please confirm your subscription.\n".
                                app['sms_reg'] ." ". app['keyword'] ." send to ". app['sms'],
        
        'pending_confirm' =>    "Confirmation needed. Please press 1 in the next USSD pop-up"
);

# Case insesitive constants are deprecated notice
define('msg', $msg_arr);

?>