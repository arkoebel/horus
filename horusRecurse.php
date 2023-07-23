<?php

require_once 'lib/horus_http.php';
require_once 'lib/horus_common.php';
require_once 'lib/horus_business.php';
require_once 'lib/horus_inject.php';
require_once 'lib/horus_simplejson.php';
require_once 'lib/horus_xml.php';
require_once 'lib/horus_exception.php';
require_once 'lib/horus_recurse.php';
require_once 'lib/horus_curlInterface.php';
require_once 'lib/horus_curl.php';
require_once 'lib/horus_utils.php';
require_once 'vendor/autoload.php';

$tracer = new HorusTracing('INDIGO', HorusCommon::getPath($_SERVER), 'Start Indigo', HorusCommon::getHttpHeaders());
$rootSpan = $tracer->getCurrentSpan();

$tracer->logSpan($rootSpan, 'Start Indigo', array('path'=>HorusCommon::getPath($_SERVER), 'BOX'=>'INDIGO'));

$headerInt = new Horus_Header();

$loglocation = HorusCommon::getConfValue('logLocation', HorusCommon::DEFAULT_LOG_LOCATION);

$businessId = HorusHttp::extractHeader('X-Business-Id', 'X_BUSINESS_ID');

if ($businessId === '') {
    $businessId = HorusCommon::getNewBusinessId();
}

$common = new HorusCommon($businessId, $loglocation, 'INDIGO');
$common->mlog('+++++ BEGIN HORUS RECURSE +++++', 'INFO');

$recurse  = new HorusRecurse($businessId, $loglocation, $tracer);

$params = json_decode(file_get_contents('conf/horusRecurse.json'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $headerInt->sendHeader(HorusCommon::HTTP_500_RETURN, true, 500);
    $headerInt->sendHeader(HorusCommon::TID_HEADER . ': ' . $businessId);
    $common->mlog("Error while decoding horusRecurse.json : " . json_last_error_msg() . "\n", "ERROR");
    echo "Error while decoding horusRecurse.json : " . json_last_error_msg() . "\n";
    exit;
}

$contentType = array_key_exists('CONTENT_TYPE', $_SERVER) ? $_SERVER['CONTENT_TYPE'] : HorusCommon::JS_CT;
$proxyMode = HorusHttp::extractHeader(HorusCommon::DEST_HEADER);
$accept = HorusHttp::extractHeader('Accept');
$data = file_get_contents('php://input');
try {
    $result = $recurse->doRecurse($data, $contentType, $proxyMode, $params, $_GET, $rootSpan);
    $headerInt->sendHeader(HorusCommon::HTTP_200_RETURN, true, 200);
    $headerInt->sendHeader(HorusCommon::TID_HEADER . ': ' . $businessId);
    if (is_array($result)) {
        echo implode("\n", $result);
    } else {
        echo $result;
    }
} catch (Exception $e) {
    $headerInt->sendHeader(HorusCommon::HTTP_200_RETURN, true, 200);
    $headerInt->sendHeader(HorusCommon::TID_HEADER . ': ' . $businessId);
    echo $e->getMessage();
}

$tracer->closeSpan($rootSpan);
$tracer->finishAll();

$common->mlog('+++++ END HORUS RECURSE +++++', 'INFO');


