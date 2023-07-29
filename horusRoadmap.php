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

$input = file_get_contents('php://input');
$rootSpan = $tracer->getCurrentSpan();

$common = new HorusCommon($businessId, $loglocation, 'WHITE');

$common->mlog("===== BEGIN HORUS CALL =====", "INFO");
$common->mlog('Destination is : ' . HorusHttp::extractHeader(HorusCommon::DEST_HEADER), 'DEBUG');

$roadmaps = new HorusRoadmap($businessId, $loglocation, $colour, $tracer, null);
$roadmapId = $roadmaps->findRoadmap('A', $input, $rootSpan);
$common->mlog("Applying roadmap " . $roadmapId, 'DEBUG');
$roadmaps->generateParts($input, $roadmapId, $businessId, $rootSpan);