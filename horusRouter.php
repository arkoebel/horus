<?php

$params = json_decode(file_get_contents('horusRouting.json'),true);
//echo print_r($params,true);
$source = $_GET['source'];
$data = file_get_contents('php://input');
$content_type = $_SERVER['CONTENT_TYPE'];

function findSource($source,$params){
    //echo "findSource $source \n";
//    echo var_dump($params['RoutingTable'],true);
    foreach($params["RoutingTable"] as $route){
        //echo "Source = " . $route['source'] . "\n";
        if($route['source']===$source){
            return $route;
        }
    }
    return false;
}
//echo $source . "\n";

$route = findSource($source, $params);

//echo print_r($route,true);

if ($route===false){
    header("HTTP/1.1 400 MALFORMED URL",TRUE,400);
    header("Content-type: application/json");
    echo json_encode(array("error"=>"Route not found"));
    exit;
}

$parameters = array();

foreach($route['parameters'] as $parameter){
    $key = urlencode($parameter['key']);
    $value = urlencode($parameter['value']);
    $parameters[] = $key . "=" . $value;
}

$responses = array();

$ii=0;

foreach($route['destinations'] as $destination){
    $ii++;
    echo "Destination : $ii " . $destination['comment'] . "\n";
    $dest_parameters = $parameters;

    foreach($destination['destParameters'] as $dest_param){
        $key = urlencode($dest_param['key']);
        $value = urlencode($dest_param['value']);
        $dest_parameters[] = $key . "=" . $value;
    }

    $proxy_parameters = $parameters;

    foreach($destination['proxyParameters'] as $proxy_param){
        $key = urlencode($proxy_param['key']);
        $value = urlencode($proxy_param['value']);
        $proxy_parameters[] = $key . "=" . $value;
    }

    $dest_query = implode('&',$dest_parameters);
    $proxy_query = implode('&',$proxy_parameters);

    if ($dest_query !== ""){
        if(stripos($destination['destination'],'?')===FALSE){
            $dest_query = '?' . $dest_query;
        }else{
            $dest_query = '&' . $dest_query;
        }
    }

    if ($proxy_query !== ""){
        if(stripos($destination['proxy'],'?')===FALSE){
            $proxy_query = '?' . $proxy_query;
        }else{
            $proxy_query = '&' . $proxy_query;
        }
    }

    //echo "Send http request to " . $destination['proxy'] . $proxy_query . "\n";
    //echo "Final destination : " . $destination['destination'] . $dest_query . "\n";
    //echo "Content-type : " . $content_type . "\n";

    $headers = array('Content-type: ' . $content_type, 'Accept: application/json', 'Expect: ');

    if ($destination['proxy']==""){
        $dest_url = $destination['destination'] . $dest_query;
    }else{
        $dest_url = $destination['proxy'] . $proxy_query;
        $headers[] = 'X_DESTINATION_URL: ' . $destination['destination']. $dest_query;
    }
    //echo "DST = " . $dest_url . "\n";
    //echo print_r($headers);
    $handle = curl_init($dest_url);
    curl_setopt($handle, CURLOPT_URL,$dest_url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($handle, CURLOPT_POST, TRUE);
    curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($handle);
    $responses[] = $response;

    echo $response;

    if($destination['delayafter']!==""){
        sleep($destination['delayafter']);
    }
}

//die(print_r($responses,true));
