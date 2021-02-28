<?php

require_once __DIR__ . "/app/config.php";
// require_once __DIR__ . "/app/telco.php";

// $subscription  = new Subscription(app['sub_msg_url'], app['sub_status_url'], app['sub_base_url']);
// $base_size = $subscription->getBaseSize(app['app_id'], app['password']);

$today = date("Y-m-d");

// $total_users = getSQLdata($mysqli, "SELECT COUNT(address) as total FROM ". app['user_table'])['total'];
// $female = getSQLdata($mysqli, "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sex = 'female'")['total'];
// $male = getSQLdata($mysqli, "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sex = 'male'")['total'];
$reg_users = getSQLdata($mysqli, "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status = '".app['sub_reg']."'")['total'];
$unreg_users = getSQLdata($mysqli, "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status = '".app['sub_unreg']."'")['total'];
$pending_users = getSQLdata($mysqli, "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status LIKE '%PENDING%' AND NOT sub_status = '".app['sub_not_confirmed']."'")['total'];
$today_reg = getSQLdata($mysqli, "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status = '".app['sub_reg']."' AND sub_date = '$today'")['total'];
$today_unreg = getSQLdata($mysqli, "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status = '".app['sub_unreg']."' AND sub_date = '$today'")['total'];
$today_pending = getSQLdata($mysqli, "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE sub_status LIKE '%PENDING%' AND sub_date = '$today' AND NOT sub_status = '".app['sub_not_confirmed']."'")['total'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: monospace;
            line-height: 1.25;
        }

        table {
            border: 1px solid #ccc;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
            width: 100%;
            table-layout: fixed;
        }

        table caption {
            font-size: 1.5rem;
            margin: .5rem 0 .75rem;
        }

        table tr {
            background-color: #f8f8f8;
            border: 1px solid #ddd;
            padding: .35rem;
        }

        table th,
        table td {
            padding: .625rem;
            text-align: center;
        }

        table th {
            font-size: .85rem;
            letter-spacing: .1rem;
            text-transform: uppercase;
        }

        @media screen and (max-width: 1300px) {
            table {
                border: 0;
            }

            table caption {
                font-size: 1.3rem;
            }
            
            table thead {
                /* border: none;
                clip: rect(0 0 0 0);
                height: 1px;
                margin: -1px;
                overflow: hidden;
                padding: 0;
                position: absolute;
                width: 1px; */
                display: none;
            }
            
            table tr {
                border-bottom: 3px solid #ddd;
                display: block;
                margin-bottom: .625rem;
            }
            
            table td {
                border-bottom: 1px solid #ddd;
                display: block;
                font-size: .8rem;
                text-align: right;
            }
            
            table td::before {
                /*
                * aria-label has no advantage, it won't be read inside a table
                content: attr(aria-label);
                */
                content: attr(data-label);
                float: left;
                font-weight: bold;
                text-transform: uppercase;
            }
            
            table td:last-child {
                border-bottom: 0;
            }
        }
    </style>
    <title><?php echo app['app_name'] ?> Dashboard</title>
</head>
<body>
    <table>
    <caption><?php echo ucfirst(app['app_name']) ?> Dashboard</caption>
    <thead>
    <tr>
        <th>Application Name</th>
        <th>Date</th>
        <!-- <th>Base Size</th> -->
        <!-- <th>Total Users</th> -->
        <!-- <th>Female</th>
        <th>Boys</th> -->
        <th>Registered Users</th>
        <th>Unregistered Users</th>
        <th>Pending Users</th>
        <th>Today Reg Users</th>
        <th>Today Unreg Users</th>
        <th>Today Pending Users</th>
    </tr>
    </thead>
    <tbody>
        <tr>
        <td data-label="Application Name"><?php echo app['app_name']; ?></td>
        <td data-label="Date"><?php echo $today; ?></td>
        <!-- <td data-label="Base Size"><?php #echo $base_size; ?></td> -->
        <!-- <td data-label="Total Users"><?php #echo $total_users; ?></td> -->
        <!-- <td data-label="Female"><?php #echo $female; ?></td>
        <td data-label="Male"><?php #echo $male; ?></td> -->
        <td data-label="Registered Users"><?php echo $reg_users; ?></td>
        <td data-label="Unregistered Users"><?php echo $unreg_users; ?></td>
        <td data-label="Pending Users"><?php echo $pending_users; ?></td>
        <td data-label="Today Reg Users"><?php echo $today_reg; ?></td>
        <td data-label="Today Unreg Users"><?php echo $today_unreg; ?></td>
        <td data-label="Today Pending Users"><?php echo $today_pending; ?></td>
    </tr>
    </tbody>
    </table>
</body>
</html>