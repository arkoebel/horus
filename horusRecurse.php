<?php

require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_business.php');
require_once('lib/horus_inject.php');
require_once('lib/horus_simplejson.php');
require_once('lib/horus_xml.php');
require_once('lib/horus_exception.php');
require_once('lib/horus_recurse.php');



$loglocation = '/var/log/horus/horus_http.log';

$business_id = HorusHttp::extractHeader('X-Business-Id');

if ($business_id === '') {
    $business_id = HorusCommon::getNewBusinessId();
}

$common = new HorusCommon($business_id, $loglocation, 'INDIGO');

$common->mlog('+++++ BEGIN HORUS RECURSE +++++', 'INFO');

$recurse  = new HorusRecurse($business_id, $loglocation);

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

try {
    $result = $recurse->doRecurse($data, $content_type, $proxy_mode, $params, $accept, $_GET);
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
$common->mlog('+++++ END HORUS RECURSE +++++', 'INFO');
