<?php

require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_business.php');
require_once('lib/horus_inject.php');
require_once('lib/horus_simplejson.php');
require_once('lib/horus_xml.php');
require_once('lib/horus_exception.php');



$business_id = HorusHttp::extractHeader('X-Business-Id');

if ($business_id === '')
    $business_id = HorusCommon::getNewBusinessId();

if(array_key_exists('type',$_GET))
    $request_type = $_GET["type"];
else
    $request_type = '';


$colour = ("inject" === $request_type) ? 'YELLOW':'GREEN';
$mmatches = json_decode(file_get_contents('conf/horusParams.json'),true);

if(json_last_error()!==JSON_ERROR_NONE){
    header("HTTP/1.1 500 SERVER ERROR",true,500);
    $common = new HorusCommon($business_id,'',$colour);
    $common->mlog("Error while decoding horusParams.json : " . json_last_error_msg() . "\n","ERROR");
    echo "Error while decoding horusParams.json : " . json_last_error_msg() . "\n";
    exit;
}

$genericError = 'templates/' . $mmatches["errorTemplate"];
$errorFormat = $mmatches['errorFormat'];


$simpleJsonMatches = $mmatches['simplejson'];
$matches = $mmatches["pacs"];

$reqbody = file_get_contents('php://input');
$content_type = $_SERVER['CONTENT_TYPE'];

$proxy_mode = HorusHttp::extractHeader('X_DESTINATION_URL');

if ("inject" === $request_type){
    $injector = new HorusInjector($business_id,'');
    $common->mlog("Request : " . print_r($_SERVER,true) . "\n",'DEBUG');
    $common->mlog("Received POST Data : '" . $reqbody . "'",'INFO','TXT',$colour);

    echo $injector->doInject($reqbody,$proxy_mode);

}else if (("simplejson" === $request_type)&&("application/json" === $content_type)){
    $injector = new HorusSimpleJson($business_id,'',$matches);

    $common->mlog("Request : " . print_r($_SERVER,true) . "\n",'DEBUG');
    $common->mlog("Received POST Data : '" . $reqbody . "'",'INFO','TXT',$colour);
    $preferredType = $injector->http->setReturnType($_SERVER['HTTP_ACCEPT'],$errorFormat);
    $common->mlog("Preferred mime type : " . $preferredType,'DEBUG','TXT',$colour);

    echo $injector->doInject($reqbody,$content_type,$proxy_mode,$preferredType,$_GET);

}else{
    $injector = new HorusXml($business_id,'');
    
    $common->mlog("Request : " . print_r($_SERVER,true) . "\n",'DEBUG');
    $common->mlog("Received POST Data : '" . $reqbody . "'",'INFO','TXT',$colour);
    $preferredType = $injector->http->setReturnType($_SERVER['HTTP_ACCEPT'],$errorFormat);
    $common->mlog("Preferred mime type : " . $preferredType,'DEBUG','TXT',$colour);

    echo $injector->doInject($reqbody,$content_type,$proxy_mode,$matches, $preferredType,$_GET,$genericError);

}