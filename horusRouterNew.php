<?php

require_once 'lib/horus_http.php';
require_once 'lib/horus_common.php';
require_once 'lib/horus_business.php';
require_once 'lib/horus_inject.php';
require_once 'lib/horus_simplejson.php';
require_once 'lib/horus_xml.php';
require_once 'lib/horus_exception.php';
require_once 'lib/horus_curlInterface.php';
require_once 'lib/horus_curl.php';

require_once 'vendor/autoload.php';

$tracer = HorusCommon::getTracer(null, 'ORANGE', HorusCommon::getPath($_SERVER));
$rootSpan = HorusCommon::getStartSpan($tracer, apache_request_headers(), 'Start Orange');

$rootSpan->addEvent('Start Router', array('path' => HorusCommon::getPath($_SERVER), 'BOX' => 'ORANGE'));

$headerInt = new Horus_Header();

$loglocation = '/var/log/horus/horus_http.log';

$businessId = HorusHttp::extractHeader('X-Business-Id', 'X_BUSINESS_ID');

if ($businessId === '') {
    $businessId = HorusCommon::getNewBusinessId();
}

$common = new HorusCommon($businessId, $loglocation, 'ORANGE');

if (function_exists('apache_request_headers')) {
    $common->mlog('Headers : ' . print_r(apache_request_headers(), true), 'DEBUG');
}

$common->mlog('Destination is : ' . HorusHttp::extractHeader(HorusCommon::DEST_HEADER), 'DEBUG');

$params = json_decode(file_get_contents('conf/horusRouting.json'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $headerInt->sendHeader(HorusCommon::HTTP_500_RETURN, true, 500);
    $headerInt->sendHeader(HorusCommon::TID_HEADER . ': ' . $businessId);
    $common->mlog("Error while decoding horusRouting.json : " . json_last_error_msg() . "\n", "ERROR");
    echo "Error while decoding horusRouting.json : " . json_last_error_msg() . "\n";
    exit;
}

$source = array_key_exists('source', $_GET) ? $_GET['source'] : '';
$content_type = array_key_exists('CONTENT_TYPE', $_SERVER) ? $_SERVER['CONTENT_TYPE'] : HorusCommon::JS_CT;
$accept = array_key_exists('HTTP_ACCEPT', $_SERVER) ? $_SERVER['HTTP_ACCEPT'] : HorusCommon::JS_CT;
if (substr($content_type, 0, 9) === 'multipart') {
    preg_match('/boundary=(.*)/', $content_type, $mm);
    $boundary = $mm[1];
    $data = HorusHttp::rebuildMultipart($_FILES, $boundary, HorusHttp::EOL);
} else {
    $data = file_get_contents('php://input');
}

$common->mlog('Data is ' . $data, 'INFO');
$business = new HorusBusiness($businessId, $loglocation, 'ORANGE', $tracer);

$route = $business->findSource($source, $params);

try {
    $responses = $business->performRouting(
        $route,
        $content_type,
        $accept,
        $data,
        HorusHttp::cleanVariables(array('source'), $_GET),
        $rootSpan
    );
    $business->http->setHttpReturnCode(200);
    $headerInt->sendHeader('Content-type: ' . HorusCommon::JS_CT);
    echo json_encode(array('result' => 'OK', 'responses' => $responses));
} catch (HorusException $e) {
    if ($e->getCode() !== 0) {
        $business->http->setHttpReturnCode($e->getCode());
    } else {
        $business->http->setHttpReturnCode(500);
    }
    echo json_encode(array('result' => 'KO', 'message' => $e->getMessage()));
} finally {
    $rootSpan->end();
    $tracer->flush();
}
