<?php
// config/config.php

// Application settings
define('APP_NAME', 'سیستم مدیریت بودجه');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Kabul');
define('APP_URL', 'http://localhost/accounting_software/');

// File upload settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'xls']);

// Session settings
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);
?>