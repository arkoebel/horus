<?php

require_once('../lib/horus_curlInterface.php');
require_once('HorusCurlMock.php');

$mock = new Horus_CurlMock();

$options = array(
    CURLOPT_URL=>'https://www.google.com',
    CURLOPT_RETURNTRANSFER=>1,
    CURLOPT_HTTPHEADER=>array('Content-type: application/json', 'Accept: application/json', 'Expect:', 'X-Business-Id: testHorusHttp'),
    CURLOPT_SSL_VERIFYPEER=>False,
    CURLOPT_VERBOSE=>True,
    CURLOPT_HEADER=>True);
$mock->setResponse("Date: Thu, 08 Aug 2019 20:22:04 GMT\nExpires: -1\nCache-Control: private, max-age=0\n" . 
                    "Content-Type: text/html; charset=ISO-8859-1\nAccept-Ranges: none\nVary: Accept-Encoding\nTransfer-Encoding: chunked\n" . 
                    "\n" . 
                    '<html>Test</html>', $options);
$mock->setInfo(array(
    CURLINFO_HTTP_CODE=>200,
    CURLINFO_HEADER_SIZE=>212,
    CURLINFO_HEADER_OUT=>True
),$options);
$mock->setInfo(array(
    CURLINFO_HTTP_CODE=>200,
    CURLINFO_HEADER_SIZE=>212,
    CURLINFO_HEADER_OUT=>True
));

$id = $mock->curl_init('newurl');
$mock->curl_setopt($id,CURLOPT_PROXY,'toto');
var_dump($mock->curl_exec($id));
var_dump($mock->curl_getinfo($id));

var_dump($mock->curl_getinfo($id,CURLINFO_HEADER_SIZE));

echo "==== Test 2\n";

$curl = new Horus_CurlMock();
$curl->setResponse('custom');
$curl->setErrorCode(CURLE_COULDNT_RESOLVE_HOST);
$curl->setInfo(array(CURLINFO_CONTENT_TYPE=>'application/xml',CURLINFO_CONTENT_LENGTH_DOWNLOAD=>223, CURLINFO_HEADER_SIZE=>110));
$ch = $curl->curl_init('toto');
ob_start();
$curl->curl_exec($ch);
$actualResponse = ob_get_clean();

var_dump($actualResponse);

echo $curl->curl_errno($ch) . "\n";
echo $curl->curl_error($ch) . "\n";
var_dump($curl->curl_getinfo($ch, CURLINFO_HEADER_SIZE));
