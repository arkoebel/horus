<?php

require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_business.php');
require_once('lib/horus_inject.php');
require_once('lib/horus_simplejson.php');
require_once('lib/horus_xml.php');
require_once('lib/horus_exception.php');

function getParams($conf,$format){
    foreach ($conf['horusFormats'] as $i => $section){
        if ($section['formatName']===$format)
            return $section;
    }
    return null;
}

function getQParams($parms,$prefix){
    $ret = array();
    foreach($parms as $key => $value){
        if(0===strpos($key,$prefix,0)){
            $ret[substr($key,strlen($prefix))] = $value;
        }
    }
    return $ret;
}

$loglocation = '/var/log/horus/horus_http.log';

$business_id = HorusHttp::extractHeader('X-Business-Id');

if ($business_id === ''){
    $business_id = HorusCommon::getNewBusinessId();
}

$common = new HorusCommon($business_id, $loglocation, 'BLACK');
$http = new HorusHttp($business_id,$loglocation,'BLACK');


if (function_exists('apache_request_headers')) {
    $common->mlog('Headers : ' . print_r(apache_request_headers(),true),'DEBUG');
}

$destination = HorusHttp::extractHeader('x_destination_url');
$common->mlog('Destination is : ' . $destination ,'DEBUG');

$params = json_decode(file_get_contents('conf/horusFormating.json'),true);

if (json_last_error() !== JSON_ERROR_NONE) {
    header("HTTP/1.1 500 SERVER ERROR", true, 500);
    header('X-Business-Id: ' . $business_id);
    $common->mlog("Error while decoding horusFormating.json : " . json_last_error_msg() . "\n", "ERROR");
    echo "Error while decoding horusFormating.json : " . json_last_error_msg() . "\n";
    exit;
}

$format = array_key_exists('format', $_GET) ? $_GET['format'] : '';
if ((''=== $format) || (getParams($params,$format) === null)){
    header("HTTP/1.1 400 MALFORMATTED URL", true, 400);
    header('X-Business-Id: ' . $business_id);
    $common->mlog("Missing or unknown format : " . $format . "\n", "ERROR");
    echo "Missing or unknown format : " . $format . "\n";
    exit;
}

$content_type = array_key_exists('CONTENT_TYPE',$_SERVER) ? $_SERVER['CONTENT_TYPE'] : 'application/json';
//$accept = array_key_exists('HTTP_ACCEPT',$_SERVER) ? $_SERVER['HTTP_ACCEPT'] : "application/json";
$accept = 'application/json';
$data = file_get_contents('php://input');
$section = getParams($params,$format);
$business  = new HorusBusiness($business_id,$loglocation,'BLACK');

$common->mlog('Incoming data : ' . $data . "\n","DEBUG");

$returnContent = '';
$returnData = $data;
$queryParams = array();
if(array_key_exists('stripSection',$section)){
    $common->mlog('Stripping section ' . $section['stripSection'],'INFO');
    $i1 = strpos($returnData,'<' . $section['stripSection'] . '>');
    $i2 = strpos($returnData,'</' . $section['stripSection'] . '>',$i1) + strlen($section['stripSection'])+3;
    $tostrip = substr($returnData,$i1,$i2-$i1);
    //$xml = simplexml_load_string($tostrip);
    $xsi = new SimpleXMLIterator($tostrip);
    for($xsi->rewind(); $xsi->valid(); $xsi->next()){
        if(!array_key_exists($xsi->key(),$queryParams))
            $queryParams[$xsi->key()] = strval($xsi->current());
    }
    $returnData = substr($returnData,$i2);
    $returnContent = 'application/xml';
    $common->mlog('Found Header parameters ' . print_r($queryParams,true),'DEBUG');
}

if (array_key_exists('addSection',$section)){
    $common->mlog('Adding section ' . $section['addSection'],'INFO');
    $xml = simplexml_load_string('<' . $section['addSection'] . '/>');
    $qparams = getQParams($_GET,$section['httpQueryPrefix']);
    foreach ($qparams as $key=>$value){
        $xml->addChild($key,$value);
    }
    $dom = dom_import_simplexml($xml);
    $fragment = $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
    $common->mlog('Inserting value : ' . $fragment . "\n",'DEBUG');
    $returnData = $fragment . "\n" . $returnData;
    $returnContent = 'text/plain';
}

if(''===$destination){
    $common->mlog('Return content is : ' . $returnData . "\n","INFO");
    $http->setHttpReturnCode(200);
    header('Content-type: ' . $returnContent);
    echo $returnData;
    exit;
}else{
    $common->mlog('Forwarding content to ' . $destination . "\n","INFO");
    $query = '';
    foreach ($queryParams as $id => $value){
        $query .= '&' . $section['httpQueryPrefix'].urlencode($id) . '=' . urlencode($value);
    }
    $common->mlog('Query Params : ' . $query,'INFO');
    $common->mlog('Body : ' . $returnData . "\n",'INFO');
    if(strpos($destination,'?',0)>0)
        $ddest = $destination . $query;
    else
        $ddest = $destination . '?' . substr($query,1);
    $rr =  $http->forwardSingleHttpQuery($ddest,array('Content-type: ' . $returnContent,'Accept: ' . $accept,'X-Business-Id: ' . $business_id),$returnData,'POST');
    foreach ($rr['headers'] as $header=>$value)
        if(strpos($header,'x-horus-')>=0)
            header($header . ': ' . $value);
    echo $rr['body'];
    exit;
}
