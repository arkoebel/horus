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

$request_type = array_key_exists('type', $_GET) ? $_GET["type"] : '';


$colour = ("inject" === $request_type) ? 'YELLOW' : 'GREEN';
$mmatches = json_decode(file_get_contents('conf/horusParams.json'), true);

$common = new HorusCommon($business_id, $loglocation, $colour);
$common->mlog("===== BEGIN HORUS CALL =====","INFO");

if (json_last_error() !== JSON_ERROR_NONE) {
    header("HTTP/1.1 500 SERVER ERROR", true, 500);
    header('X-Business-Id: ' . $business_id);
    $common->mlog("Error while decoding horusParams.json : " . json_last_error_msg() . "\n", "ERROR");
    echo "Error while decoding horusParams.json : " . json_last_error_msg() . "\n";
    exit;
}

$genericError = 'templates/' . $mmatches["errorTemplate"];
$errorFormat = $mmatches['errorFormat'];


$simpleJsonMatches = array_key_exists('simplejson',$mmatches) ? $mmatches['simplejson'] : null;
$matches = array_key_exists('pacs',$mmatches) ? $mmatches["pacs"] : null;

$reqbody = file_get_contents('php://input');
$content_type = $_SERVER['CONTENT_TYPE'];

$proxy_mode = HorusHttp::extractHeader('X_DESTINATION_URL');

if ("inject" === $request_type) {
    $common->mlog('+++++ BEGIN INJECTOR MODE +++++','INFO');
    $injector = new HorusInjector($business_id, $loglocation);
    $common->mlog("Request : " . print_r($_SERVER, true) . "\n", 'DEBUG');
    $common->mlog("Received POST Data : '" . $reqbody . "'", 'INFO', 'TXT', $colour);

    try {
        $res = $injector->doInject($reqbody, $proxy_mode);
        header("HTTP/1.1 200 OK", true, 200);
        header("X-Business-Id: $business_id");
        echo $res;
    } catch (Exception $e) {
        header("HTTP/1.1 200 OK", true, 200);
        header("X-Business-Id: $business_id");
        echo $e->getMessage();
    }
    $common->mlog('+++++ END INJECTOR MODE +++++','INFO');
} else if (("simplejson" === $request_type) && ("application/json" === $content_type)) {
    $common->mlog('+++++ BEGIN SIMPLEJSON MODE +++++','INFO');
    $injector = new HorusSimpleJson($business_id, $loglocation, $simpleJsonMatches);

    $common->mlog("Request : " . print_r($_SERVER, true) . "\n", 'DEBUG');
    $common->mlog("Received POST Data : '" . $reqbody . "'", 'INFO', 'TXT', $colour);
    $preferredType = $injector->http->setReturnType($_SERVER['HTTP_ACCEPT'], $errorFormat);
    $common->mlog("Preferred mime type : " . $preferredType, 'DEBUG', 'TXT', $colour);

    try {
        $res =  $injector->doInject($reqbody, $proxy_mode, $preferredType, $_GET);
        header("HTTP/1.1 200 OK", true, 200);
        header("X-Business-Id: $business_id");
        echo implode("\n", $res);
    } catch (Exception $e) {
        header("HTTP/1.1 200 OK", true, 200);
        header("X-Business-Id: $business_id");
        echo $e->getMessage();
    }
    $common->mlog('+++++ END SIMPLEJSON MODE +++++','INFO');
} else {
    $common->mlog('+++++ BEGIN XML MODE +++++','INFO');
    $injector = new HorusXml($business_id, $loglocation);

    $common->mlog("Request : " . print_r($_SERVER, true) . "\n", 'DEBUG');
    $common->mlog("Received POST Data : '" . $reqbody . "'", 'INFO', 'TXT', $colour);
    $preferredType = $injector->http->setReturnType($_SERVER['HTTP_ACCEPT'], $errorFormat);
    $common->mlog("Preferred mime type : " . $preferredType, 'DEBUG', 'TXT', $colour);

    try {
        $res = $injector->doInject($reqbody, $content_type, $proxy_mode, $matches, $preferredType, $_GET, $genericError);

        header("HTTP/1.1 200 OK", true, 200);
        header("X-Business-Id: $business_id");
        if (is_array($res)){
            echo implode("\n", $res);
        }else{
            echo $res;
        }
    } catch (Exception $e) {
        header("HTTP/1.1 200 OK", true, 200);
        header("X-Business-Id: $business_id");
        echo $e->getMessage();
    }
    $common->mlog('+++++ END XML MODE +++++','INFO');
}

$common->mlog("===== END HORUS CALL =====","INFO");
