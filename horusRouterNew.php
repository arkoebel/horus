<?php

require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_business.php');
require_once('lib/horus_inject.php');
require_once('lib/horus_simplejson.php');
require_once('lib/horus_xml.php');
require_once('lib/horus_exception.php');

function findSource($source,$params){
    foreach($params["RoutingTable"] as $route){
        if($route['source']===$source){
            return $route;
        }
    }
    return false;
}

$loglocation = '/var/log/nginx/horus.log';

$business_id = HorusHttp::extractHeader('X-Business-Id');

if ($business_id === '')
    $business_id = HorusCommon::getNewBusinessId();

$common = new HorusCommon($business_id, $loglocation, 'ORANGE');


$params = json_decode(file_get_contents('conf/horusRouting.json'),true);

$source = array_key_exists('source', $_GET) ? $_GET['source'] : '';
$content_type = array_key_exists('CONTENT_TYPE',$_SERVER) ? $_SERVER['CONTENT_TYPE'] : 'application/json';
$accept = array_key_exists('HTTP_ACCEPT',$_SERVER) ? $_SERVER['HTTP_ACCEPT'] : "application/json";
$data = file_get_contents('php://input');

$route = findSource($source, $params);


if ($route===false){
    $common->mlog('No route found with source=' . $source,'WARNING');
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
    $common->mlog("Destination : $ii " . $destination['comment'] . "\n","INFO");
    
    $destinationUrl = HorusCommon::formatQueryString($destination['destination'], $destination['destParameters'],TRUE);
    $proxyUrl = ($destination['proxy']=="") ? '' : HorusCommon::formatQueryString($destination['proxy'], $destination['proxyParameters'],TRUE);
    
    $common->mlog("Send http request to " . $proxyUrl. "\n",'DEBUG');
    $common->mlog("Final destination : " . $destinationUrl . "\n",'DEBUG');
    $common->mlog("Content-type: " . $content_type . ", Accept: " . $accept,'DEBUG');

    $headers = array('Content-type: ' . $content_type, 'Accept: ' . $accept, 'Expect: ', 'X_BUSINESS_ID: ' . $business_id);

    if ($destination['proxy']==""){
        $dest_url = $destination['destination'] . $dest_query;
    }else{
        $dest_url = $destination['proxy'] . $proxy_query;
        $headers[] = 'X_DESTINATION_URL: ' . $destination['destination']. $dest_query;
    }

    try{
        $response = $http->forwardSingleHttpQuery($dest_url, $headers, $data, 'POST');
    }catch(HorusException $e){
        $response = json_encode(array("error"=>$e->getMessage()));
    }
    
    $responses[] = $response;

    echo $response;

    if(array_key_exists('delayafter',$destination)){
        $common->mlog('Waiting ' . $destination['delayafter'] . 'sec for next destination','INFO');
        sleep($destination['delayafter']);
    }
}

