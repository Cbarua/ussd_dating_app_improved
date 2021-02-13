<?php

require_once __DIR__ . "/app/config.php";

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
        <th>Female</th>
        <th>Boys</th>
        <th>Subscribed Users</th>
        <th>Unsubscribed Users</th>
        <th>Pending Users</th>
        <th>Today Reg Users</th>
        <th>Today Unreg Users</th>
        <th>Today Pending Users</th>
    </tr>
    </thead>
    <tbody>
        <tr>
        <td data-label="Application Name"><?php echo app['app_name']; ?></td>
        <td data-label="Date"><?php echo date("Y-m-d"); ?></td>
        <td data-label="Total Users"><?php echo 0; ?></td>
        <td data-label="Female"><?php echo 0; ?></td>
        <td data-label="Male"><?php echo 0; ?></td>
        <td data-label="Subscribed Users"><?php echo 0; ?></td>
        <td data-label="Unsubscribed Users"><?php echo 0; ?></td>
        <td data-label="Pending Users"><?php echo 0; ?></td>
        <td data-label="Today Reg Users"><?php echo 0; ?></td>
        <td data-label="Today Unreg Users"><?php echo 0; ?></td>
        <td data-label="Today Pending Users"><?php echo 0; ?></td>
    </tr>
    </tbody>
    </table>
</body>
</html>