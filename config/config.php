<?php
// Load secrets from .env (never commit .env/.env.local to GitHub)
// IMPORTANT: load from *project root* (C:\xampp\htdocs\coffee), not Apache document root.
$project_root = realpath(__DIR__ . '/..');
$env_file = $project_root . DIRECTORY_SEPARATOR . '.env';
$env_local_file = $project_root . DIRECTORY_SEPARATOR . '.env.local';
$env = [];
if (file_exists($env_file)) {
    $env = parse_ini_file($env_file);
} elseif (file_exists($env_local_file)) {
    $env = parse_ini_file($env_local_file);
}

// Normalize env values (trim whitespace)
if ($env) {
    foreach ($env as $k => $v) {
        if (is_string($v)) $env[$k] = trim($v);
    }
}

// Check if running on a local development environment
if ($_SERVER['HTTP_HOST'] === 'localhost') {
    // Local database settings (for XAMPP/MAMP) - can be overridden by .env
    $server_name = $env['DB_HOST'] ?? "localhost";
    $user_name = $env['DB_USER'] ?? "root";
    $password = $env['DB_PASS'] ?? "";
    $db_name = $env['DB_NAME'] ?? "ns_coffee";

    // Define the base URL for the local environment
    define("url", $env['APP_URL'] ?? "http://localhost/coffee");
    define("ADMINURL", $env['ADMIN_URL'] ?? "http://localhost/coffee/admin-panel");
} else {
    // Live environment MUST have .env
    if (!$env) {
        die("❌ Error: .env file is missing! Please upload it to your server.");
    }

    // Load database credentials from .env
    $server_name = $env['DB_HOST'] ?? '';
    $user_name = $env['DB_USER'] ?? '';
    $password = $env['DB_PASS'] ?? '';
    $db_name = $env['DB_NAME'] ?? '';

    // Define the base URL for the live environment
    define("url", $env['APP_URL'] ?? ("https://" . $_SERVER['HTTP_HOST']));
    define("ADMINURL", $env['ADMIN_URL'] ?? ("https://" . $_SERVER['HTTP_HOST'] . "/admin-panel"));
}

// Payments (Razorpay)
define("RAZORPAY_KEY_ID", $env['RAZORPAY_KEY_ID'] ?? '');
define("RAZORPAY_KEY_SECRET", $env['RAZORPAY_KEY_SECRET'] ?? '');

// Email (SMTP)
define("SMTP_HOST", $env['SMTP_HOST'] ?? 'smtp.gmail.com');
define("SMTP_PORT", (int)($env['SMTP_PORT'] ?? 587));
define("SMTP_USERNAME", $env['SMTP_USERNAME'] ?? '');
define("SMTP_PASSWORD", $env['SMTP_PASSWORD'] ?? '');
define("SMTP_FROM_EMAIL", $env['SMTP_FROM_EMAIL'] ?? '');
define("SMTP_FROM_NAME", $env['SMTP_FROM_NAME'] ?? 'NS Coffee');

// Admin bootstrap login (optional)
define("ADMIN_LOGIN_NAME", $env['ADMIN_LOGIN_NAME'] ?? 'Admin');
define("ADMIN_LOGIN_EMAIL", $env['ADMIN_LOGIN_EMAIL'] ?? '');
define("ADMIN_LOGIN_PASSWORD", $env['ADMIN_LOGIN_PASSWORD'] ?? '');

// Create a connection to the MySQL database
$conn = mysqli_connect($server_name, $user_name, $password, $db_name);

// Check if the connection was successful
if (!$conn) {
    die("❌ Connection failed: " . mysqli_connect_error());
}
