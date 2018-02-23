<?php

$DB_URL = 'mysql:host=localhost;port=3306;dbname=sondage';

try{
    $pdo = new PDO($DB_URL,'root','');
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $exception){
    echoerror($exception);
}

function databaseQuery($pdo,$query,$params=array()){
    $response = array();
    try{
        $columnsStmt = $pdo->prepare($query);
        $columnsStmt->execute($params);
        while ($row = $columnsStmt->fetch(PDO::FETCH_ASSOC)){
            $response[] = $row;
        }
        if(count($response)==0){
            $a = new Exception("Database sent an empty response.");
            echoerror($a);
        }
        return $response;
    }
    catch( PDOException $exception) {
        echoerror($exception);
    }
}

function databaseValueQuery($pdo,$query,$params=array()){
    try{
        $columnsStmt = $pdo->prepare($query);
        $columnsStmt->execute($params);
        $row = $columnsStmt->fetch(PDO::FETCH_NUM);
        return $row[0];
     }
    catch( PDOException $exception) {
        echoerror($exception);
    }

}

