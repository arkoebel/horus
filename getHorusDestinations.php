<?php
    header("Content-type: application/json");
    echo file_get_contents('injectorParams.json');
    /*$json = json_decode(file_get_contents('horusParams.json'),true);
    $response = array();

    foreach($json['pacs'] as $parm){
        $params = array();
        foreach($parm['parameters'] as $key => $value)
            $params[] = $key;
        $response[$parm['responseTemplate']] = $params;
    } 
    foreach($json['simplejson'] as $parm){
        $params = array();
        foreach($parm['parameters'] as $key => $value)
            $params[] = $key;
        $response[$parm['responseTemplate']] = $params;
    }


    echo json_encode($response);*/
