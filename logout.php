<?php

require_once __DIR__ . "/app/auth.php";

logout();
header("Location: login.php?logged_out=1");
exit();
