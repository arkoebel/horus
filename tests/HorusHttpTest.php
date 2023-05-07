<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('HorusTestCase.php');
require_once('lib/horus_exception.php');
require_once('HorusCurlMock.php');

class HorusHttpTest extends HorusTestCase
{

 
    function testForwardHttpQuery(): void
    {

        $mock = new Horus_CurlMock();
        $options = array(
            CURLOPT_URL=>'https://www.google.com',
            CURLOPT_RETURNTRANSFER=>1,
            //CURLOPT_HTTPHEADER=>array('Content-type: application/json', 'Accept: application/json', 'Expect:', 'X-Business-Id: testHorusHttp'),
            CURLOPT_SSL_VERIFYPEER=>False,
            CURLOPT_VERBOSE=>True,
            CURLOPT_HEADER=>True);
        $mock->setResponse("Date: Thu, 08 Aug 2019 20:22:04 GMT\nExpires: -1\nCache-Control: private, max-age=0\nContent-Type: text/html; charset=ISO-8859-1\nAccept-Ranges: none\nVary: Accept-Encoding\nTransfer-Encoding: chunked\n" . 
                            "\n" . 
                            '<html>Test</html>', $options);
        $mock->setInfo(array(
            CURLINFO_HTTP_CODE=>200,
            CURLINFO_HEADER_SIZE=>196,
            CURLINFO_HEADER_OUT=>True
        ),$options);

        $headerImpl = new Horus_HeaderMock();
        $this->http->setCurlImpl($mock);
        $this->http->setHeaderImpl($headerImpl);

        $headers = array('Content-type' => 'application/json', 'Accept' => 'application/json', 'Expect' => '', 'X-Business-Id' => 'testHorusHttp');
        $query = array('url' => 'https://www.google.com', 'method' => 'GET', 'headers' => $headers);
        $queries = array();
        $queries[] = $query;

        $aa = $mock->curl_init();
        
        $result = $this->http->forwardHttpQueries($queries,self::$rootSpan,'rfh-','mqmd-');

        $this::assertEquals(200,$result[0]['response_code']);
        $this::assertEquals('<html>Test</html>',$result[0]['response_data']);
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

    function testForwardSingleHttpQuery(): void 
    {

        $mock = new Horus_CurlMock();
        $options = array(
            CURLOPT_URL=>'https://www.google.com',
            CURLOPT_RETURNTRANSFER=>1,
            //CURLOPT_HTTPHEADER=>array('Content-type: application/json', 'Accept: application/json', 'Expect:', 'X-Business-Id: testHorusHttp'),
            CURLOPT_SSL_VERIFYPEER=>False,
            CURLOPT_VERBOSE=>True,
            CURLOPT_HEADER=>True);
        $mock->setResponse("Date: Thu, 08 Aug 2019 20:22:04 GMT\nExpires: -1\nCache-Control: private, max-age=0\n" . 
                            "Content-Type: text/html; charset=ISO-8859-1\nAccept-Ranges: none\nVary: Accept-Encoding\nTransfer-Encoding: chunked\n" . 
                            "\n" . 
                            '<html>Test</html>', $options);
        $mock->setInfo(array(
            CURLINFO_HTTP_CODE=>400,
            CURLINFO_HEADER_SIZE=>196,
            CURLINFO_HEADER_OUT=>True
        ),$options);

        $this->http->setCurlImpl($mock);

        $this->expectException(HorusException::class);
        $this->http->forwardSingleHttpQuery('https://www.google.com', array('Content-type: application/json', 'Accept: application/json', 'Expect:', 'X-Business-Id: testHorusHttp') , null, 'POST',self::$rootSpan);
   


    }

    function testFormatOutHeaders():void
    {
        $input =    array('Content-type: application/xml',
                            'Accept: text/plain',
                            'Expect:',
                            'X-Business-Id: 6b15cb4e-fb64-409d-b228-ed069fe6369e',
                            'x-b3-traceid' => 'a050f67be54f66ac',
                            'x-b3-parentspanid' => '16907023254a39c4',
                            'x-b3-spanid' => '169070232774471d',
                            'x-b3-sampled' => 1);
        $output = HorusHttp::formatOutHeaders($input);

        $expected =    array('content-type: application/xml',
                            'accept: text/plain',
                            'expect: ',
                            'x-business-id: 6b15cb4e-fb64-409d-b228-ed069fe6369e',
                            'x-b3-traceid: a050f67be54f66ac',
                            'x-b3-parentspanid: 16907023254a39c4',
                            'x-b3-spanid: 169070232774471d',
                            'x-b3-sampled: 1');

        $this::assertEquals(count($output),8);
        $this::assertEquals($output,$expected);

    
    }


    function testFilterMQHeaders(): void{
        $this->http->common->cnf = array("rfh2Prefix"=>"rfh-","mqmdPrefix"=>"mqmd-");
        $input =  array('rfh-key'=>'value','rfh-key2'=>'XX: value2','rfh-key3' => 'XX2: YY: value3', 'other' => 'http://test');
        $output1 = $this->http->filterMQHeaders($input,'UNPACK');
        $expectedOutput1 = array('rfh-key'=>'value','XX'=>'value2','XX2' => 'YY: value3');
        $this::assertEquals($expectedOutput1, $output1);
    }
}
