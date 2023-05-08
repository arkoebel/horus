<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('HorusTestCase.php');
require_once('lib/horus_exception.php');
require_once('HorusCurlMock.php');

class HorusHttpTest extends HorusTestCase
{

 
    public function testForwardHttpQuery(): void
    {

        $mock = new Horus_CurlMock();
        $options = array(
            CURLOPT_URL=>'https://www.google.com',
            CURLOPT_RETURNTRANSFER=>1,
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
                CURLINFO_HEADER_SIZE=>196,
                CURLINFO_HEADER_OUT=>true
            ),
            $options
        );

        $headerImpl = new Horus_HeaderMock();
        $this->http->setCurlImpl($mock);
        $this->http->setHeaderImpl($headerImpl);

        $headers = array(
            'Content-type' => HorusCommon::JS_CT,
            'Accept' => HorusCommon::JS_CT,
            'Expect' => '',
            HorusCommon::TID_HEADER => 'testHorusHttp'
        );
        $query = array('url' => 'https://www.google.com', 'method' => 'GET', 'headers' => $headers);
        $queries = array();
        $queries[] = $query;

        $mock->curl_init();
        
        $result = $this->http->forwardHttpQueries($queries, self::$rootSpan, 'rfh-', 'mqmd-');

        $this::assertEquals(200, $result[0]['response_code']);
        $this::assertEquals('<html>Test</html>', $result[0]['response_data']);
    }

    public function testSetReturnType(): void
    {
        $accept1 = 'plain/text, application/xml, application/json; encoding=utf-8';
        $accept2 = 'plain/text, application/json; encoding=utf-8';

        $this::assertEquals(HorusCommon::XML_CT, $this->http->setReturnType($accept1, HorusCommon::XML_CT));
        $this::assertEquals(HorusCommon::JS_CT, $this->http->setReturnType($accept2, HorusCommon::JS_CT));
        $this::assertEquals(HorusCommon::JS_CT, $this->http->setReturnType(null, HorusCommon::JS_CT));
        $this::assertEquals(HorusCommon::JS_CT, $this->http->setReturnType('', HorusCommon::JS_CT));
    }

    public function testReturnOutData(): void
    {
        $testnotjson = '<test>mytest</test>';
        $testjson = '{"json": { "test1": "value1", "test2":"value2" }, "test3": "value3"}';

        $this::assertEquals($testnotjson, $this->http->convertOutData($testnotjson, HorusCommon::XML_CT, true));
        $this::assertEquals($testnotjson, $this->http->convertOutData($testnotjson, 'text/plain', false));
        $this::assertEquals($testjson, $this->http->convertOutData($testjson, HorusCommon::JS_CT, true));
        $this::assertNotEquals($testjson, $this->http->convertOutData($testjson, HorusCommon::JS_CT, false));
        $decodedjson = json_decode($this->http->convertOutData($testjson, HorusCommon::JS_CT, false), true);
        $this::assertNotNull($decodedjson['payload']);
    }

    public function testSetHttpReturnCode(): void
    {
        $this->http->setHttpReturnCode(200);
        $this::assertEquals(array(array(HorusCommon::HTTP_200_RETURN, true, 200)), self::$mockheaders);
    }

    public function testSetHttpReturnCode2(): void
    {
        $this->http->setHttpReturnCode(400);
        $this::assertEquals(self::$mockheaders, array(array("HTTP/1.1 400 MALFORMED URL", true, 400)));
    }

    public function testForwardSingleHttpQuery(): void
    {

        $mock = new Horus_CurlMock();
        $options = array(
            CURLOPT_URL=>'https://www.google.com',
            CURLOPT_RETURNTRANSFER=>1,
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
                CURLINFO_HTTP_CODE=>400,
                CURLINFO_HEADER_SIZE=>196,
                CURLINFO_HEADER_OUT=>true
            ),
            $options
        );

        $this->http->setCurlImpl($mock);

        $this->expectException(HorusException::class);
        $this->http->forwardSingleHttpQuery(
            'https://www.google.com',
            array(
                'Content-type: application/json',
                'Accept: application/json',
                'Expect:',
                'X-Business-Id: testHorusHttp'
                ),
            null,
            'POST',
            self::$rootSpan
        );
   


    }

    public function testFormatOutHeaders():void
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

        $this::assertEquals(count($output), 8);
        $this::assertEquals($output, $expected);

    
    }


    public function testFilterMQHeaders(): void
    {
        $this->http->common->cnf = array("rfh2Prefix"=>"rfh-", "mqmdPrefix"=>"mqmd-");
        $input =  array(
            'rfh-key'=>'value',
            'rfh-key2'=>'XX: value2',
            'rfh-key3' => 'XX2: YY: value3',
            'other' => 'http://test'
        );
        $output1 = $this->http->filterMQHeaders($input, 'UNPACK');
        $expectedOutput1 = array('rfh-key'=>'value','XX'=>'value2','XX2' => 'YY: value3');
        $this::assertEquals($expectedOutput1, $output1);
    }
}
