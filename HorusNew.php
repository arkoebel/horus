<?php

require_once 'lib/horus_http.php';
require_once 'lib/horus_common.php';
require_once 'lib/horus_business.php';
require_once 'lib/horus_inject.php';
require_once 'lib/horus_simplejson.php';
require_once 'lib/horus_xml.php';
require_once 'lib/horus_exception.php';
require_once 'vendor/autoload.php';

use Jaeger\Config;

$loglocation = '/var/log/horus/horus_http.log';

$business_id = HorusHttp::extractHeader('X-Business-Id','X_BUSINESS_ID');

if ($business_id === '') {
    $business_id = HorusCommon::getNewBusinessId();
}

$request_type = array_key_exists('type', $_GET) ? $_GET["type"] : '';


$colour = ("inject" === $request_type) ? 'YELLOW' : 'GREEN';

$tracer = HorusCommon::getTracer(Config::getInstance(),$colour,HorusCommon::getPath($_SERVER));
$rootSpan = HorusCommon::getStartSpan($tracer,apache_request_headers(),'Start Green/Yellow');

$mmatches = json_decode(file_get_contents('conf/horusParams.json'), true);

$common = new HorusCommon($business_id, $loglocation, $colour);

$common->mlog("===== BEGIN HORUS CALL =====", "INFO");
$common->mlog('Destination is : ' . HorusHttp::extractHeader('x_destination_url'), 'DEBUG');

if (json_last_error() !== JSON_ERROR_NONE) {
    header(HorusCommon::HTTP_500_RETURN, true, 500);
    header(HorusCommon::TID_HEADER . ': ' . $business_id);
    $common->mlog("Error while decoding horusParams.json : " . json_last_error_msg() . "\n", "ERROR");
    echo "Error while decoding horusParams.json : " . json_last_error_msg() . "\n";
    exit;
}

$genericError = 'templates/' . $mmatches["errorTemplate"];
$errorFormat = $mmatches['errorFormat'];


$simpleJsonMatches = array_key_exists('simplejson', $mmatches) ? $mmatches['simplejson'] : null;
$matches = array_key_exists('pacs', $mmatches) ? $mmatches["pacs"] : null;

$reqbody = array();
$content_type = $_SERVER['CONTENT_TYPE'];
if(substr($content_type,0,9)==='multipart'){
    preg_match('/boundary=(.*)/',$content_type,$mm);
    $boundary = $mm[1];
    foreach ($_FILES as $file){
        $reqbody[] = base64_decode(file_get_contents($file['tmp_name']));
    }
    error_log(print_r($reqbody, true));
}else{
    $reqbody[] = file_get_contents('php://input');
}


$proxy_mode = HorusHttp::extractHeader('x_destination_url');
$accept = HorusHttp::extractHeader('Accept');

$rootSpan->setTag('destination',$proxy_mode);
$rootSpan->setTag('content-type',$content_type);
$rootSpan->setTag('accept',$accept);

if ("inject" === $request_type) {
    $rootSpan->log(['message' => 'Starting Injector mode', 'path' => $path, 'BOX' => $colour]);
    $common->mlog('+++++ BEGIN INJECTOR MODE +++++', 'INFO');
    $injector = new HorusInjector($business_id, $loglocation, $tracer);
    $common->mlog("Request : " . print_r($_SERVER, true) . "\n", 'DEBUG');
    $common->mlog("Received POST Data : '" . $reqbody[0] . "'", 'INFO', 'TXT', $colour);

    try {
        $res = $injector->doInject($reqbody[0], $proxy_mode, $rootSpan);
        header(HorusCommon::HTTP_200_RETURN, true, 200);
        header(HorusCommon::TID_HEADER . ': ' . $business_id);
        echo $res;
    } catch (Exception $e) {
        header(HorusCommon::HTTP_200_RETURN, true, 200);
        header(HorusCommon::TID_HEADER . ': ' . $business_id);
        echo $e->getMessage();
    }
    $common->mlog('+++++ END INJECTOR MODE +++++', 'INFO');
} elseif (("simplejson" === $request_type) && ("application/json" === substr($content_type,0,16))) {
    $rootSpan->log(['message' => 'Starting Json mode', 'path' => $path, 'BOX' => $colour]);
    $common->mlog('+++++ BEGIN SIMPLEJSON MODE +++++', 'INFO');
    $injector = new HorusSimpleJson($business_id, $loglocation, $simpleJsonMatches, $tracer);

    $common->mlog("Request : " . print_r($_SERVER, true) . "\n", 'DEBUG');
    $common->mlog("Received POST Data : '" . $reqbody[0] . "'", 'INFO', 'TXT', $colour);
    $preferredType = $injector->http->setReturnType($accept, $errorFormat);
    $common->mlog("Preferred mime type : " . $preferredType, 'DEBUG', 'TXT', $colour);

    try {
        $res =  $injector->doInject($reqbody[0], $proxy_mode, $preferredType, $_GET, $rootSpan);
        header(HorusCommon::HTTP_200_RETURN, true, 200);
        header(HorusCommon::TID_HEADER . ': ' . $business_id);
        if (is_array($res)) {
            echo implode("\n", $res);
        } else {
            echo $res;
        }
    } catch (Exception $e) {
        header(HorusCommon::HTTP_200_RETURN, true, 200);
        header(HorusCommon::TID_HEADER . ': ' . $business_id);
        echo $e->getMessage();
    }
    $common->mlog('+++++ END SIMPLEJSON MODE +++++', 'INFO');
} else {
    $common->mlog('+++++ BEGIN XML MODE +++++', 'INFO');
    $rootSpan->log(['message' => 'Starting XML mode', 'path' => HorusCommon::getPath($_SERVER), 'BOX' => $colour]);
    $injector = new HorusXml($business_id, $loglocation, 'GREEN', $tracer);

    $common->mlog("Request : " . print_r($_SERVER, true) . "\n", 'DEBUG');
    $common->mlog("Received POST Data : '" . print_r($reqbody, true) . "'", 'INFO', 'TXT', $colour);

    $defaultOutContentType = array_key_exists('pacsDefaultOutputContentType', $mmatches)
        ? $mmatches['pacsDefaultOutputContentType']
        : 'application/xml';
    $preferredType = $injector->http->setReturnType($defaultOutContentType, $errorFormat);
    $common->mlog("Generated documents will be converted to : " . $preferredType, 'DEBUG', 'TXT', $colour);

    if (count($reqbody)>1) {
        $mime_boundary = md5(time());
    } else {
        $mime_boundary = 'single';
    }

    try {
        $output = array();
        foreach ($reqbody as $ii => $singlebody) {
           
            $zz = $injector->doInject(
                $singlebody,
                $content_type,
                $proxy_mode,
                $matches,
                $preferredType,
                $_GET,
                $genericError,
                $mime_boundary,
                '',
                $rootSpan,
                $ii
            );

            error_log(print_r($zz,true));

            if (is_array($zz)) {
                $output = array_merge($output,$zz);
            } else {
                $output[] = $zz;
            }
        }

        if ($mime_boundary !== 'single') {
          $output[] = "--" . $mime_boundary . "--\r\n\r\n";
        }

        header(HorusCommon::HTTP_200_RETURN, true, 200);
        header(HorusCommon::TID_HEADER . ': ' . $business_id);

        if (count($output)>1) {
            echo implode("\n", $output);
        } else {
            echo $output[0];
        }
    } catch (Exception $e) {
        header(HorusCommon::HTTP_200_RETURN, true, 200);
        header(HorusCommon::TID_HEADER . ': ' . $business_id);
        echo $e->getMessage();
    }
    $common->mlog('+++++ END XML MODE +++++', 'INFO');
}
$rootSpan->finish();
Config::getInstance()->flush();

$common->mlog("===== END HORUS CALL =====", "INFO");
