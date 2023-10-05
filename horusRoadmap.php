<?php


require_once 'lib/horus_http.php';
require_once 'lib/horus_common.php';
require_once 'lib/horus_business.php';
require_once 'lib/horus_inject.php';
require_once 'lib/horus_simplejson.php';
require_once 'lib/horus_xml.php';
require_once 'lib/horus_exception.php';
require_once 'lib/horus_roadmap.php';
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

$colour = 'WHITE';

$tracer = new HorusTracing(
    $colour,
    HorusCommon::getPath($_SERVER),
    'Start White',
    HorusCommon::getHttpHeaders()
    );

$rootSpan = $tracer->getCurrentSpan();
$tracer->logSpan($rootSpan, 'Start Roadmap', array('path' => HorusCommon::getPath($_SERVER), 'BOX' => 'WHITE'));

$input = file_get_contents('php://input');

$whiteSpan = $rootSpan;

$common = new HorusCommon($businessId, $loglocation, 'WHITE');

$common->mlog("===== BEGIN HORUS CALL =====", "INFO");
$common->mlog('Destination is : ' . HorusHttp::extractHeader(HorusCommon::DEST_HEADER), 'DEBUG');
$source = array_key_exists('source', $_GET) ? $_GET['source'] : '';

$roadmaps = new HorusRoadmap($businessId, $loglocation, $colour, $tracer, null);
$tracer->logSpan($whiteSpan, "Looking for Roadmap");
$roadmapId = $roadmaps->findRoadmap($source, $input, $whiteSpan);
$tracer->logSpan($whiteSpan, "Roadmap Id is " . $roadmapId);
if(is_null($roadmapId)){
    echo json_encode(array('result' => 'KO', 'message' => 'Unable to find appropriate roadmap'));
    exit;
}
$common->mlog("Applying roadmap " . $roadmapId, 'DEBUG');
$tracer->logSpan($whiteSpan, "Applying roadmap " . $roadmapId);
try{
    $nMess = $roadmaps->generateParts($source, $input, $roadmapId, $businessId, $whiteSpan);
    $tracer->logSpan($whiteSpan, "Generated " . $nMess . ' messages');
}catch(Exception $e){
    echo json_encode(array('result' => 'KO', 'message' => $e->getMessage()));
    exit;
}finally{
    $tracer->closeSpan($whiteSpan);
    $tracer->finishAll();

}

echo json_encode(array('result' => 'OK', 'message' => 'Generated ' . $nMess . ' messages'));