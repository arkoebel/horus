<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('HorusTestCase.php');

class HorusHttpTest extends HorusTestCase
{

 
    function testForwardHttpQuery(): void
    {


        $headers = array('Content-type' => 'application/json', 'Accept' => 'application/json', 'Expect' => '', 'X-Business-Id' => 'testHorusHttp');
        $query = array('url' => 'https://www.google.com', 'method' => 'GET', 'headers' => $headers);
        $queries = array();
        $queries[] = $query;

        self::$curls[] = array( 'url'=>'https://www.google.com',
                                'options'=>array(
                                    CURLOPT_RETURNTRANSFER=>1,
                                    CURLOPT_HTTPHEADER=>array('Content-type: application/json', 'Accept: application/json', 'Expect:', 'X-Business-Id: testHorusHttp'),
                                    CURLOPT_SSL_VERIFYPEER=>False,
                                    CURLOPT_VERBOSE=>True,
                                    CURLOPT_HEADER=>True,
                                    CURLINFO_HEADER_OUT=>True),
                                'data'=>"HTTP/1.1 200 OK\nDate: Thu, 08 Aug 2019 20:22:04 GMT\nExpires: -1\nCache-Control: private, max-age=0\n" . 
                                        "Content-Type: text/html; charset=ISO-8859-1\nAccept-Ranges: none\nVary: Accept-Encoding\nTransfer-Encoding: chunked\n" . 
                                        "\n" . 
                                        "<html>Test</html>",
                                'returnHeaders'=>array(
                                    CURLINFO_HTTP_CODE=>200,
                                    CURLINFO_HEADER_SIZE=>212,

                                ),
                                'returnCode'=>200,
                                'errorMessage'=>'',
                                'returnBody'=>'<html>Test</html>');

        $result = $this->http->forwardHttpQueries($queries);
        $this::assertEquals($result[0]['response_code'], 200);
        $this::assertEquals($result[0]['response_data'],'<html>Test</html>');
    }

    function testSetReturnType(): void
    {
        $accept1 = 'plain/text, application/xml, application/json; encoding=utf-8';
        $accept2 = 'plain/text, application/json; encoding=utf-8';

        $this::assertEquals($this->http->setReturnType($accept1, 'application/json'), 'application/xml');
        $this::assertEquals($this->http->setReturnType($accept2, 'application/json'), 'application/json');
        $this::assertEquals($this->http->setReturnType(null, 'application/json'), 'application/json');
        $this::assertEquals($this->http->setReturnType('', 'application/json'), 'application/json');
    }

    function testReturnOutData(): void
    {
        $testnotjson = '<test>mytest</test>';
        $testjson = '{"json": { "test1": "value1", "test2":"value2" }, "test3": "value3"}';

        $this::assertEquals($this->http->convertOutData($testnotjson, 'application/xml', true), $testnotjson);
        $this::assertEquals($this->http->convertOutData($testnotjson, 'text/plain', false), $testnotjson);
        $this::assertEquals($this->http->convertOutData($testjson, 'application/json', true), $testjson);
        $this::assertNotEquals($this->http->convertOutData($testjson, 'application/json', false), $testjson);
        $decodedjson = json_decode($this->http->convertOutData($testjson, 'application/json', false), true);
        $this::assertNotNull($decodedjson['payload']);
    }

    function testSetHttpReturnCode(): void
    {
        $this->http->setHttpReturnCode(200);
        $this::assertEquals(self::$mockheaders, array(array("HTTP/1.1 200 OK", TRUE, 200)));
    }

    function testSetHttpReturnCode2(): void
    {
        $this->http->setHttpReturnCode(400);
        $this::assertEquals(self::$mockheaders, array(array("HTTP/1.1 400 MALFORMED URL", TRUE, 400)));
    }
}
