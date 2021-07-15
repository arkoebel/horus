<?php

require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_business.php');
require_once('lib/horus_inject.php');
require_once('lib/horus_simplejson.php');
require_once('lib/horus_xml.php');
require_once('lib/horus_exception.php');
require_once('lib/horus_recurse.php');
require_once('vendor/autoload.php');

use Jaeger\Config;

$tracer = HorusCommon::getTracer(Config::getInstance(),'INDIGO',HorusCommon::getPath($_SERVER));
$rootSpan = HorusCommon::getStartSpan($tracer,apache_request_headers(),'Start Indigo');

$rootSpan->log(['message'=>'Start Indigo','path'=>HorusCommon::getPath($_SERVER), 'BOX'=>'INDIGO']);


$loglocation = '/var/log/horus/horus_http.log';

$business_id = HorusHttp::extractHeader('X-Business-Id');

if ($business_id === '') {
    $business_id = HorusCommon::getNewBusinessId();
}

$common = new HorusCommon($business_id, $loglocation, 'INDIGO');

$common->mlog('EEEE ' . print_r($rootSpan,true),'DEBUG');

$common->mlog('+++++ BEGIN HORUS RECURSE +++++', 'INFO');

$recurse  = new HorusRecurse($business_id, $loglocation,$tracer);

$params = json_decode(file_get_contents('conf/horusRecurse.json'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    header("HTTP/1.1 500 SERVER ERROR", true, 500);
    header('X-Business-Id: ' . $business_id);
    $common->mlog("Error while decoding horusRecurse.json : " . json_last_error_msg() . "\n", "ERROR");
    echo "Error while decoding horusRecurse.json : " . json_last_error_msg() . "\n";
    exit;
}

$content_type = array_key_exists('CONTENT_TYPE', $_SERVER) ? $_SERVER['CONTENT_TYPE'] : 'application/json';
$proxy_mode = HorusHttp::extractHeader('x_destination_url');
$accept = HorusHttp::extractHeader('Accept');
$data = file_get_contents('php://input');
$common->mlog('AAAAA Recurse dest=' . $proxy_mode . ' / queryparams=' . implode(',',$_GET),'DEBUG');
try {
    $result = $recurse->doRecurse($data, $content_type, $proxy_mode, $params, $accept, $_GET,$rootSpan);
    header("HTTP/1.1 200 OK", true, 200);
    header("X-Business-Id: $business_id");
    if (is_array($result)) {
        echo implode("\n", $result);
    } else {
        echo $result;
    }
} catch (Exception $e) {
    header("HTTP/1.1 200 OK", true, 200);
    header("X-Business-Id: $business_id");
    echo $e->getMessage();
}

$rootSpan->finish();
$tracer->flush();
Config::getInstance()->flush();
$common->mlog('+++++ END HORUS RECURSE +++++', 'INFO');


