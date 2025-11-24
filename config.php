<?php
$host = 'localhost';
$dbname = 'spend_calculator';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");
    
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($sql);
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}
?>
