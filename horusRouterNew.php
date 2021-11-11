<?php

require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_business.php');
require_once('lib/horus_inject.php');
require_once('lib/horus_simplejson.php');
require_once('lib/horus_xml.php');
require_once('lib/horus_exception.php');

require_once('vendor/autoload.php');

$tracer = HorusCommon::getTracer($config,'ORANGE',HorusCommon::getPath($_SERVER));
$rootSpan = HorusCommon::getStartSpan($tracer,apache_request_headers(),'Start Orange');

$rootSpan->log(['message'=>'Start Router','path'=>HorusCommon::getPath($_SERVER), 'BOX'=>'ORANGE']);



$loglocation = '/var/log/horus/horus_http.log';

$business_id = HorusHttp::extractHeader('X-Business-Id','X_BUSINESS_ID');

if ($business_id === ''){
    $business_id = HorusCommon::getNewBusinessId();
}

$common = new HorusCommon($business_id, $loglocation, 'ORANGE');

if (function_exists('apache_request_headers')) {
    $common->mlog('Headers : ' . print_r(apache_request_headers(),true),'DEBUG');
}

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
if(substr($content_type,0,9)==='multipart'){
    $boundary = md5(time());
    $data = HorusHttp::rebuildMultipart($_FILES,$boundary,HorusHttp::EOL);
}else
    $data = file_get_contents('php://input');

$common->mlog('Data is ' . $data,'INFO');
$business  = new HorusBusiness($business_id,$loglocation,'ORANGE',$tracer);

$route = $business->findSource($source, $params);

try{
    $responses = $business->performRouting($route, $content_type, $accept, $data, HorusHttp::cleanVariables(array('source'),$_GET),$rootSpan);
    $business->http->setHttpReturnCode(200);
    header('Content-type: application/json');
    echo json_encode(array('result'=>'OK','responses'=>$responses));
}catch(HorusException $e){
    if ($e->getCode !== 0){
        $business->http->setHttpReturnCode($e->getCode());
     }else{
        $business->http->setHttpReturnCode(500);
    }
    echo json_encode(array('result'=>'KO','message'=>$e->getMessage()));
}finally{
    $rootSpan->finish();
    $tracer->flush();
    Jaeger\Config::getInstance()->flush();
}

