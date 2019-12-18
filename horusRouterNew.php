<?php

require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_business.php');
require_once('lib/horus_inject.php');
require_once('lib/horus_simplejson.php');
require_once('lib/horus_xml.php');
require_once('lib/horus_exception.php');



$loglocation = '/var/log/horus/horus_http.log';

$business_id = HorusHttp::extractHeader('X-Business-Id');

if ($business_id === ''){
    $business_id = HorusCommon::getNewBusinessId();
}

$common = new HorusCommon($business_id, $loglocation, 'ORANGE');

$common->mlog('Headers : ' . print_r(apache_request_headers(),true),'DEBUG');

$common->mlog('Destination is : ' . HorusHttp::extractHeader('x_destination_url'),'DEBUG');

$params = json_decode(file_get_contents('conf/horusRouting.json'),true);

if (json_last_error() !== JSON_ERROR_NONE) {
    header("HTTP/1.1 500 SERVER ERROR", true, 500);
    header('X-Business-Id: ' . $business_id);
    $common->mlog("Error while decoding horusRouting.json : " . json_last_error_msg() . "\n", "ERROR");
    echo "Error while decoding horusRouting.json : " . json_last_error_msg() . "\n";
    exit;
}

$source = array_key_exists('source', $_GET) ? $_GET['source'] : '';
$content_type = array_key_exists('CONTENT_TYPE',$_SERVER) ? $_SERVER['CONTENT_TYPE'] : 'application/json';
$accept = array_key_exists('HTTP_ACCEPT',$_SERVER) ? $_SERVER['HTTP_ACCEPT'] : "application/json";
$data = file_get_contents('php://input');

$business  = new HorusBusiness($business_id,$loglocation,'ORANGE');

$route = $business->findSource($source, $params);

try{
    $responses = $business->performRouting($route, $content_type, $accept, $data, $_GET);
    $business->http->setHttpReturnCode(200);
    echo json_encode(array('result'=>'OK','responses'=>$responses));
}catch(HorusException $e){
    if ($e->getCode !== 0){
        $business->http->setHttpReturnCode($e->getCode());
     }else{
        $business->http->setHttpReturnCode(500);
    }
    echo json_encode(array('result'=>'KO','message'=>$e->getMessage()));
}

