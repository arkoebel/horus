<?php

require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_business.php');
require_once('lib/horus_inject.php');
require_once('lib/horus_simplejson.php');
require_once('lib/horus_xml.php');
require_once('lib/horus_exception.php');



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

$route = $this->business->findSource($source, $params);

try{
    $responses = $this->business->performRouting($route, $content_type, $accept, $data);
    $this->http->setHttpReturnCode(200);
    echo json_encode(array('result'=>'OK','responses'=>$responses));
}catch(HorusException $e){
    if ($e->getCode !== 0)
        $this->http->setHttpReturnCode($e->getCode());
    else
        $this->http->setHttpReturnCode(500);
    echo json_encode(array('result'=>'KO','message'=>$e->getMessage()));
}

