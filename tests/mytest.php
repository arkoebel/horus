<?php

require_once('lib/horus_curlInterface.php');
require_once('tests/HorusCurlMock.php');
require_once('lib/horus_tracing.php');

$mockTracing = new HorusTracingMock('BOX', 'SERIAL', 'START', array());

$mock = new Horus_CurlMock();

$options = array(
    CURLOPT_URL=>'https://www.google.com',
    CURLOPT_RETURNTRANSFER=>1,
    CURLOPT_HTTPHEADER=>array(
        'Content-type: application/json',
        'Accept: application/json',
        'Expect:','X-Business-Id: testHorusHttp'
    ),
    CURLOPT_SSL_VERIFYPEER=>false,
    CURLOPT_VERBOSE=>true,
    CURLOPT_HEADER=>true);
$mock->setResponse(
    "Date: Thu, 08 Aug 2019 20:22:04 GMT\n" .
    "Expires: -1\n" .
    "Cache-Control: private, max-age=0\n" .
    "Content-Type: text/html; charset=ISO-8859-1\n" .
    "Accept-Ranges: none\n" .
    "Vary: Accept-Encoding\n" .
    "Transfer-Encoding: chunked\n" .
    "\n" .
    '<html>Test</html>',
    $options
);
$mock->setInfo(
    array(
        CURLINFO_HTTP_CODE=>200,
        CURLINFO_HEADER_SIZE=>212,
        CURLINFO_HEADER_OUT=>true
    ),
    $options
);
$mock->setInfo(
    array(
        CURLINFO_HTTP_CODE=>200,
        CURLINFO_HEADER_SIZE=>212,
        CURLINFO_HEADER_OUT=>true
    )
);


$mockTracing->newSpan('newurl');
$mockTracing->newSpan('node1');
$mockTracing->newSpan('node2');
$mockTracing->finishSpan();
$mockTracing->newSpan('node3');

$id = $mock->curl_init('newurl');
$mock->curl_setopt($id, CURLOPT_PROXY, 'toto');
var_dump($mock->curl_exec($id));
var_dump($mock->curl_getinfo($id));

var_dump($mock->curl_getinfo($id, CURLINFO_HEADER_SIZE));

echo "==== Test 2\n";

$mockTracing->newSpan('test2');
$curl = new Horus_CurlMock();
$curl->setResponse('custom');
$curl->setErrorCode(CURLE_COULDNT_RESOLVE_HOST);
$curl->setInfo(
    array(
        CURLINFO_CONTENT_TYPE=>'application/xml',
        CURLINFO_CONTENT_LENGTH_DOWNLOAD=>223,
        CURLINFO_HEADER_SIZE=>110
    )
);
$ch = $curl->curl_init('toto');
ob_start();
$curl->curl_exec($ch);
$actualResponse = ob_get_clean();

$mockTracing->finishSpan();
var_dump($actualResponse);

echo $curl->curl_errno($ch) . "\n";
echo $curl->curl_error($ch) . "\n";
var_dump($curl->curl_getinfo($ch, CURLINFO_HEADER_SIZE));
$mockTracing->finishSpan();
$mockTracing->finishAll();
