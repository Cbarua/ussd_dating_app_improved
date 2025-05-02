<?php

require_once __DIR__ . "/app/config.php";
require_once __DIR__ . "/app/telco.php";

$subscription  = new Subscription(app['sub_msg_url'], app['sub_status_url'], app['sub_base_url']);
$active = $subscription->getBaseSize(app['app_id'], app['password']);

function subtractStrictLowerTen($numStr) {
    // Convert string to integer
    $num = intval($numStr);
    
    // Find the nearest lower multiple of 10 (excluding itself)
    $nearestLowerTen = ($num % 10 == 0) ? ($num - 10) : (floor($num / 10) * 10);
    
    // Perform the subtraction
    return $num - $nearestLowerTen;
}

$active = subtractStrictLowerTen($active);

$today = date("Y-m-d");

# Dashboard
$sql = "Select * from ". app['dash_table'] ." WHERE date= '$today'";
$dashboard = getSQLdata($mysqli, $sql);

if (!isset($dashboard['date'])) {
    $sql = "INSERT INTO ". app['dash_table'] ."(date) VALUES ('$today')";
    executeSQL($mysqli, $sql);

    // Solution for
    // Trying to access array offset &
    // Undefined index problem
    $dashboard = ['reg' => 0, 'unreg' => 0, 'pending' => 0, 'active' => 0];
}

$sql = "SELECT COUNT(address) as total FROM ". app['user_table'] ." WHERE ";

$total_sql = $sql . "NOT sub_status = '". app['sub_unreg'] ."' AND NOT sub_status = '". app['sub_not_confirmed'] ."'";
$today_reg_sql = $sql . "sub_status = '". app['sub_reg'] ."' AND sub_date = '$today'";
$today_unreg_sql = $sql . "sub_status = '". app['sub_unreg'] ."' AND sub_date = '$today'";
$today_pending_sql = $sql . "NOT sub_status = '". app['sub_reg'] ."' AND NOT sub_status = '". app['sub_unreg'] ."' AND NOT sub_status = '". app['sub_not_confirmed'] ."' AND sub_date = '$today'";

$total = getSQLdata($mysqli, $total_sql)['total'];
$pending = intval($total) - intval($active);
$today_reg = getSQLdata($mysqli, $today_reg_sql)['total'];
$today_unreg = getSQLdata($mysqli, $today_unreg_sql)['total'];
$today_pending = getSQLdata($mysqli, $today_pending_sql)['total'];

$update_dashboard = [];

if ($dashboard['reg'] !== $today_reg) {
    $update_dashboard['reg'] = $today_reg;
}
if ($dashboard['unreg'] !== $today_unreg) {
    $update_dashboard['unreg'] = $today_unreg;
}
if ($dashboard['pending'] !== $today_pending) {
    $update_dashboard['pending'] = $today_pending;
}
if ($dashboard['active'] !== $active) {
    $update_dashboard['active'] = $active;
}

if (!empty($update_dashboard)) {
    updateDashDB($mysqli, $today, $update_dashboard);
}


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
        <th>Total Users</th>
        <th>Active Users</th>
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
        <td data-label="Total Users"><?php echo $total; ?></td>
        <td data-label="Active Users"><?php echo $active; ?></td>
        <td data-label="Pending Users"><?php echo $pending; ?></td>
        <td data-label="Today Reg Users"><?php echo $today_reg; ?></td>
        <td data-label="Today Unreg Users"><?php echo $today_unreg; ?></td>
        <td data-label="Today Pending Users"><?php echo $today_pending; ?></td>
    </tr>
    </tbody>
    </table>
</body>
</html>