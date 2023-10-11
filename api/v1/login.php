<?php

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if(empty($data["password"]) && empty($data["login"])) {
    http_response_code(400);
    echo "empty request parameters";
    die();
}

require_once __DIR__ . "/db/database.php";
require_once __DIR__ . "/function.php";

$password = htmlspecialchars($data["password"]);
$login = htmlspecialchars($data["login"]);

$database = new DataBase();
$data =  $database->getUserInfo($login, $password);

if($data != "password invalid" || $data != "user invalid"){
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);  
}else{
    http_response_code(400); 
}

echo $data;
