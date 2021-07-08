<?php

# Sinhala Message Constants
$msg_arr = array(
        'register' =>   "Welcome to ". app['app_name'] . " app.".
                        "\n1.Register wenna".
                        "\n0.Exit",

        'exit' =>       app['app_name'] . 
                        " USSD Chat Sewawa Bavitha Kala Obata Isthuthi. ". 
                        app['app_name'] . " Wetha Nawathath Paminenna.",

        'main_menu' =>  "1.Obata Galapena Kenaa Dan Soyaganna." . 
                        "\n2.Obe Username Eka Nawatha Labaganimata.".
                        "\n3.Udaw" . 
                        "\n4.Apa Gana Thathu.".
                        "\n99.Exit",

        'about' =>      "Developer: cbarua19@gmail.com" . 
                        "\n0.back",

        'help' =>       "Welcome to ". app['app_name'] . " USSD Application.\n" . 
                        app['app_name'] . " Ha Ekathuwu Obata Isthuthi. \n" . 
                        "USSD Bhavitha Kirimata Upades.\n" . 
                        app['ussd'] . " Dial Kara Anaka 1 Oba, Galapena Kenek Soyaganna.\n" .
                        "Obe Yahaluwantath ". app['ussd'] . " Dial Kara Oba Samaga Chat Kirimata Puluwan.\n" . 
                        "***Chat Kirimata Upades***\n" . 
                        app['keyword'] . "<histhanak>Yaluwage Username<histhanak>Oyage Message Eka Sadahan Kara ". app['sms'] . "ta Yawanna. \n" . 
                        "Udaharana:-". app['keyword'] . " dinuka kohomada oyata? Sent " . app['sms'],

        'notify' =>    "Keti Paniwidayak Magin Labena Upades Pilipadinna.\n0.Main menu",

        # Register User Messages
        'reg_name' =>   "Welcome to ". app['app_name'] . " USSD Application." . 
                        "\nObata Galapenama Kenaa Dan Lesiyenma Soyaganna Puluwan.".
                        "\nObe Nama Sadahan Karanna.",

        'reg_name_int' =>       "Welcome to ". app['app_name'] . " USSD Application." . 
                                "\nObata Galapenama Kenaa Dan Lesiyenma Soyaganna Puluwan.
                                \nObe Nama Ho Ankayak Sadahan Karanna.",

        'reg_name_e' => "Oba Nama Weradiya. Karunakara Niweredi Nama Athulath Karanna.\nUdaharana: Dinuka, Sameera, Nuwan",

        'reg_name_int_e' => "Input Error!\nObe Nama Ho Ankayak Sadahan Karanna.",

        'reg_sex' =>    "Oba" . 
                        "\n1.Male(Pirimiyek)" . 
                        "\n2.Female(Gahaniyak)" . 
                        "\nAdala Ankaya Thoranna.",

        'sex_e' =>      "Karunakara Anka 1 ho 2 Thoraganna.\n",

        'reg_birthdate' => "Obe Upan Dinaya Sadahan Karanna.\n" . 
                            "(Udaharana:1995-01-01)",

        'reg_birthdate_e' => "Oba Athulath Kala Dinaya Weradiya.\n".
                             "Karunakara Obe Niweredi Upan Dinaya Athulath Karanna.\n".
                             "(Udaharana:1995-01-01)",


        # Search User Configurations
        'search_sex' =>         "Oba Soyanne\n1.Male(Purusha)\n2.Female(Isthri)",

        'search_agelist' =>     "1.Wayasa 16-20\n" .
                                "2.Wayasa 21-25\n" . 
                                "3.Wayasa 26-30\n" . 
                                "4.Wayasa 31-35\n" . 
                                "5.Wayasa 36-40\n" . 
                                "6.Wayasa 41-45\n" . 
                                "7.Wayasa 46-50\n" . 
                                "8.Wayasa 51-99\n" .
                                "93.Back\n" .
                                "0.Main menu",

        'search_result' =>      "Ketipaniwidayak Magin Thorathuru Labei\n" . 
                                "1.Chat Request Ekak Danna\n". 
                                "0.Main menu",

        # Chat 
        'chat_request_sent' =>  "Chat Request Ewa atha. ".
                                "Ohu ho Aeya Oba Samaga Chat Kirimata Kamathi Nm Obata Message Ekak Lebebi." . 
                                "\n0.Main menu",

        'chat_request_sms' =>   "Chat Kirimata Kamaththen Siti.",

        'chat_no_user' =>       "User Soyaganimata Nometha.".
                                " Karuanakara Username Eka Niweradidei Pariksha karanna.",

        # Others
        'nav_e' =>              "Input Error!\n",
        
        'username_e' =>         "Obata username ekak nomatha. \n" . app['ussd']. " Amathanna.",

        'pending_e' =>          "Mema Sebawa Pawicchi Kirimata Obe Sheshaya Pramanawath Natha. \n".
                                "Karunakara Pasuwa Uthsaha Karanna.",

        'subscribe_first' =>    "Karunakara register wenna.\n".
                                app['sms_reg'] ."  ". app['keyword'] ." ". app['sms']. " ta ewanna.",
        
        # Apache server error_log notices issue
        'not_confirmed_e' =>    "",
        
        'pending_confirm' =>    ""
);

# Case insesitive constants are deprecated notice
define('msg', $msg_arr);

?>
