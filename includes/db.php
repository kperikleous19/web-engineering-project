<?php

$host = "127.0.0.1";
$dbname = "tepak_ee";
$username = "root";
$password = "MySQLuserBRO!1!";

try {

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8",$username,$password);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch(PDOException $e){

    die("Database connection failed.");

}
