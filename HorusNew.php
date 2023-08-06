<?php

require_once 'lib/horus_http.php';
require_once 'lib/horus_common.php';
require_once 'lib/horus_business.php';
require_once 'lib/horus_inject.php';
require_once 'lib/horus_simplejson.php';
require_once 'lib/horus_xml.php';
require_once 'lib/horus_exception.php';
require_once 'lib/horus_utils.php';
require_once 'lib/horus_curlInterface.php';
require_once 'lib/horus_curl.php';
require_once 'vendor/autoload.php';

$loglocation = HorusCommon::getConfValue('logLocation', HorusCommon::DEFAULT_LOG_LOCATION);

$businessId = HorusHttp::extractHeader(HorusCommon::TID_HEADER, 'X_BUSINESS_ID');

$headerInt = new Horus_Header();

if ($businessId === '') {
    $businessId = HorusCommon::getNewBusinessId();
}

$requestType = array_key_exists('type', $_GET) ? $_GET["type"] : '';


$colour = ("inject" === $requestType) ? 'YELLOW' : 'GREEN';
 

$tracer = new HorusTracing(
    $colour,
    HorusCommon::getPath($_SERVER),
    'Start Green/Yellow',
    HorusCommon::getHttpHeaders()
    );
$rootSpan = $tracer->getCurrentSpan();

$mmatches = json_decode(file_get_contents('conf/horusParams.json'), true);

$common = new HorusCommon($businessId, $loglocation, $colour);

$common->mlog("===== BEGIN HORUS CALL =====", "INFO");
$common->mlog('Destination is : ' . HorusHttp::extractHeader(HorusCommon::DEST_HEADER), 'DEBUG');

if (json_last_error() !== JSON_ERROR_NONE) {
    $headerInt->sendHeader(HorusCommon::HTTP_500_RETURN, true, 500);
    $headerInt->sendHeader(HorusCommon::TID_HEADER . ': ' . $businessId);
    $common->mlog("Error while decoding horusParams.json : " . json_last_error_msg() . "\n", "ERROR");
    echo "Error while decoding horusParams.json : " . json_last_error_msg() . "\n";
    exit;
}

$genericError = 'templates/' . $mmatches["errorTemplate"];
$errorFormat = $mmatches['errorFormat'];


$simpleJsonMatches = array_key_exists('simplejson', $mmatches) ? $mmatches['simplejson'] : null;
$matches = array_key_exists('pacs', $mmatches) ? $mmatches["pacs"] : null;

$reqbody = array();
$contentType = $_SERVER['CONTENT_TYPE'];
if(substr($contentType,0,9)==='multipart'){
    preg_match('/boundary=(.*)/',$contentType,$mm);
    $boundary = $mm[1];
    foreach ($_FILES as $file){
        $reqbody[] = file_get_contents($file['tmp_name']);
    }
    error_log(print_r($reqbody, true));
}else{
    $reqbody[] = file_get_contents('php://input');
}


$proxyMode = HorusHttp::extractHeader('x_destination_url');
$accept = HorusHttp::extractHeader('Accept');

if ("inject" === $requestType) {
    $tracer->logSpan($rootSpan, 'message', array('Starting Injector mode', 'path' => $path, 'BOX' => $colour));
    $common->mlog('+++++ BEGIN INJECTOR MODE +++++', 'INFO');
    $injector = new HorusInjector($businessId, $loglocation, $tracer);
    $common->mlog("Request : " . print_r($_SERVER, true) . "\n", 'DEBUG');
    $common->mlog("Received POST Data : '" . $reqbody[0] . "'", 'INFO', 'TXT', $colour);

    try {
        $res = $injector->doInject($reqbody[0], $proxyMode, $rootSpan);
        $headerInt->sendHeader(HorusCommon::HTTP_200_RETURN, true, 200);
        $headerInt->sendHeader(HorusCommon::TID_HEADER . ': ' . $businessId);
        echo $res;
    } catch (Exception $e) {
        $headerInt->sendHeader(HorusCommon::HTTP_200_RETURN, true, 200);
        $headerInt->sendHeader(HorusCommon::TID_HEADER . ': ' . $businessId);
        echo $e->getMessage();
    }
    $common->mlog('+++++ END INJECTOR MODE +++++', 'INFO');
} elseif (("simplejson" === $requestType) && ("application/json" === substr($contentType, 0, 16))) {
    $tracer->logSpan($rootSpan, 'Starting Json mode', array('path' => $path, 'BOX' => $colour));
    $common->mlog('+++++ BEGIN SIMPLEJSON MODE +++++', 'INFO');
    $injector = new HorusSimpleJson($businessId, $loglocation, $simpleJsonMatches, $tracer);

    $common->mlog("Request : " . print_r($_SERVER, true) . "\n", 'DEBUG');
    $common->mlog("Received POST Data : '" . $reqbody[0] . "'", 'INFO', 'TXT', $colour);
    $preferredType = $injector->http->setReturnType($accept, $errorFormat);
    $common->mlog("Preferred mime type : " . $preferredType, 'DEBUG', 'TXT', $colour);

    try {
        $res =  $injector->doInject($reqbody[0], $proxyMode, $preferredType, $_GET, $rootSpan);
        $headerInt->sendHeader(HorusCommon::HTTP_200_RETURN, true, 200);
        $headerInt->sendHeader("X-Business-Id: $businessId");
        if (is_array($res)) {
            echo implode("\n", $res);
        } else {
            echo $res;
        }
    } catch (Exception $e) {
        $headerInt->sendHeader(HorusCommon::HTTP_200_RETURN, true, 200);
        $headerInt->sendHeader("X-Business-Id: $businessId");
        echo $e->getMessage();
    }
    $common->mlog('+++++ END SIMPLEJSON MODE +++++', 'INFO');
} else {
    $common->mlog('+++++ BEGIN XML MODE +++++', 'INFO');
    $tracer->logSpan($rootSpan, 'Starting XML mode', array('path' => HorusCommon::getPath($_SERVER), 'BOX' => $colour));
    $injector = new HorusXml($businessId, $loglocation, 'GREEN', $tracer);

    $common->mlog("Request : " . print_r($_SERVER, true) . "\n", 'DEBUG');
    $common->mlog("Received POST Data : '" . print_r($reqbody, true) . "'", 'INFO', 'TXT', $colour);

    $defaultOutContentType = array_key_exists('pacsDefaultOutputContentType', $mmatches)
        ? $mmatches['pacsDefaultOutputContentType']
        : HorusCommon::XML_CT;
    $preferredType = $injector->http->setReturnType($defaultOutContentType, $errorFormat);
    $common->mlog("Generated documents will be converted to : " . $preferredType, 'DEBUG', 'TXT', $colour);

    if (count($reqbody)>1) {
        $mimeBoundary = md5(time());
    } else {
        $mimeBoundary = 'single';
    }

    try {
        $output = array();
        foreach ($reqbody as $ii => $singlebody) {
   
            $zz = $injector->doInject(
                $singlebody,
                $contentType,
                $proxyMode,
                $matches,
                $preferredType,
                $_GET,
                $genericError,
                $mimeBoundary,
                '',
                $rootSpan,
                $ii
            );
            if (is_array($zz)) {
                $output = array_merge($output,$zz);
            } else {
                $output[] = $zz;
            }
        }


        if ($mimeBoundary !== 'single') {
            $output[] = "--" . $mimeBoundary . "--\r\n\r\n";
        }

        $headerInt->sendHeader(HorusCommon::HTTP_200_RETURN, true, 200);
        $headerInt->sendHeader(HorusCommon::TID_HEADER . ': ' . $businessId);
        if (count($output)>1) {
            echo implode("\n", $output);
        } else {
            echo $output[0];
        }
    } catch (Exception $e) {
        $headerInt->sendHeader(HorusCommon::HTTP_200_RETURN, true, 200);
        $headerInt->sendHeader(HorusCommon::TID_HEADER . ': ' . $businessId);
        echo $e->getMessage();
    }
    $common->mlog('+++++ END XML MODE +++++', 'INFO');
}
$tracer->closeSpan($rootSpan);
$tracer->finishAll();

$common->mlog("===== END HORUS CALL =====", "INFO");
