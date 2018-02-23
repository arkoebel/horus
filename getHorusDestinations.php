<?php

    $json = json_decode(file_get_contents('horusParams.json'),true);
    $response = array();

    foreach($json['pacs'] as $parm){
        $params = array();
        foreach($parm['parameters'] as $key => $value)
            $params[] = $key;
        $response[$parm['responseTemplate']] = $params;
    } 

    echo json_encode($response);
