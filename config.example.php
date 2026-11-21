<?php
$host = "localhost";
$db   = "sports_league_db";
$user = "YOUR_MYSQL_USERNAME";
$pass = "YOUR_MYSQL_PASSWORD";
$charset = "utf8mb4";
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
try { $pdo = new PDO($dsn, $user, $pass, $options); }
catch (PDOException $e) {
    http_response_code(500);
    die("Database connection failed. Import database.sql and check config.php.");
}
