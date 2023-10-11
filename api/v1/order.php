<?php

if(!$_GET["auth_key"]){
    echo 'empty auth_key';
    http_response_code(401);
    die();
}else{
    
    require_once __DIR__ . "/db/database.php";
    $database = new DataBase();

    if(!$database->checkApiKey($_GET["auth_key"]))
    {
        echo 'false auth_key';
        http_response_code(401);
        die();
    }
    
}

// action=first&auth_key
switch ($_GET["action"]) {

    case "first" :

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        $response = json_encode([
            'category' => $database->getServiceCategory(),
        ]);
        echo $response;
        break;
    

    case "second" :
        
        // action=second&auth_key&service_id
        if(($service_id = $_GET['service_id'])){
    
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
    
            $orderInfo = $database->getOrderInfo($service_id);
            
            //кэш айдишника сервиса
            $storage_service = json_decode(file_get_contents('./storage/service_id.json'), true);
            $storage_service[$_GET["auth_key"]] = $service_id;
            file_put_contents('./storage/service_id.json' , json_encode($storage_service));
            
            echo (json_encode($orderInfo) ?? 'json encode error');
        } 
        else {
            http_response_code(401);
            echo 'empty service_id query parameter';
            die();
        }
        break;


    case "third":
        var_dump($_SERVER);
        // action=third&auth_key&master_id&date
        // date = {year}.{month}.{day} ex. 2023.08.10
        if(($date = $_GET['date']) && ($master_id = $_GET['master_id'])){
            //need to check a correct date (rexp)
            $data = $database->getScheduleForOneDay($master_id, $date);
            echo (json_encode($data) ?? 'json encode error');     
        } 
        break;

    case "four":
        //action=four&auth_key&time 
        //client_id, master_id, master_schedule_id, date, start_time
        //end_time, status, salon_service_id, salon_id
        if($time = $_GET['time']){
            
        }
        // save order
        break;        

    default:
        echo "action is not right";  
        http_response_code(404);
        die();
}
