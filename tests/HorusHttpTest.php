<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_http.php');
require_once('lib/horus_common.php');

class HorusHttpTest extends TestCase
{

    public static $mockheaders;
    public static $curls;
    protected $http;

    /**
     * Use runkit to create a new header function.
     */
    public static function setUpBeforeClass()
    {
        if (!extension_loaded('runkit7')) {
            error_log("No Extension");
            return;
        }

        // First backup the real header function so we can restore it.
        runkit_function_rename('header', 'header_old');

        // Now, create a new header function that makes things testable.
        runkit_function_add(
            'header',
            '$string,$replace=true,$http_response_code=null',
            'HorusHttpTest::$mockheaders[] = array($string,$replace,$http_response_code);'
        );

        runkit_function_rename('curl_multi_init', 'curl_multi_init_old');
        runkit_function_rename('curl_init', 'curl_init_old');
        runkit_function_rename('curl_setopt', 'curl_setopt_old');
        runkit_function_rename('curl_multi_add_handle', 'curl_multi_add_handle_old');
        runkit_function_rename('curl_multi_exec', 'curl_multi_exec_old');
        runkit_function_rename('curl_multi_select', 'curl_multi_select_old');
        runkit_function_rename('curl_error', 'curl_error_old');
        runkit_function_rename('curl_getinfo', 'curl_getinfo_old');
        runkit_function_rename('curl_multi_getcontent', 'curl_multi_getcontent_old');
        runkit_function_rename('curl_multi_remove_handle', 'curl_multi_remove_handle_old');
        runkit_function_rename('curl_close', 'curl_close_old');
        runkit_function_rename('curl_multi_close', 'curl_multi_close_old');
        runkit_function_add(
            'curl_multi_init',
            '',
            'return HorusHttpTest::$curls;'
        );
        runkit_function_add(
            'curl_init',
            '$string = null',
            'return array_keys(HorusHttpTest::$curls)[count(HorusHttpTest::$curls)-1];'
        );

        runkit_function_add(
            'curl_setopt',
            '$ch , $option , $value',
            'HorusHttpTest::$curls[$ch][\'options\'][$option] = $value; return true;'
        );
        
        runkit_function_add(
            'curl_multi_add_handle',
            '$mh , $ch',
            'return 0;'
        );

        runkit_function_add(
            'curl_multi_exec',
            '$mh , &$still_running',
            'return 0;'
        );

        runkit_function_add(
            'curl_multi_select',
            '$mh, $timeout = 1.0 ',
            'sleep(1); return 0;'
        );

        runkit_function_add(
            'curl_error',
            '$ch',
            'return HorusHttpTest::$curls[$ch][\'errorMessage\'];'
        );

        runkit_function_add(
            'curl_getinfo',
            '$ch, $opt',
            'if (isset($opt))
                return HorusHttpTest::$curls[$ch][\'returnHeaders\'][$opt];
            else
                return HorusHttpTest::$curls[$ch][\'returnHeaders\'];'
        );
        
        runkit_function_add(
            'curl_multi_getcontent',
            '$ch',
            'return HorusHttpTest::$curls[$ch][\'data\'];'
        );
        
        runkit_function_add(
            'curl_multi_remove_handle',
            '$mh,$ch',
            'return 0;' 
        );
        
        runkit_function_add(
            'curl_close',
            '$ch',
            'return 0;'
        );

        runkit_function_add(
            'curl_multi_close',
            '$mh',
            'return;'
        );
        
    }

    /**
     * After we're done testing, restore the header function.
     */
    public static function tearDownAfterClass()
    {
        if (!extension_loaded('runkit7')) {
            return;
        }
        // Get rid of our new header function.
        runkit_function_remove('header');
        runkit_function_remove('curl_multi_init');
        runkit_function_remove('curl_init');
        runkit_function_remove('curl_setopt');
        runkit_function_remove('curl_multi_add_handle');
        runkit_function_remove('curl_multi_exec');
        runkit_function_remove('curl_multi_select');
        runkit_function_remove('curl_error');
        runkit_function_remove('curl_getinfo');
        runkit_function_remove('curl_multi_getcontent');
        runkit_function_remove('curl_multi_remove_handle');
        runkit_function_remove('curl_close');
        runkit_function_remove('curl_multi_close');


        // Move our backup to restore header to its original glory.
        runkit_function_rename('header_old', 'header');
        runkit_function_rename('curl_multi_init_old', 'curl_multi_init');
        runkit_function_rename('curl_init_old', 'curl_init');
        runkit_function_rename('curl_setopt_old', 'curl_setopt');
        runkit_function_rename('curl_multi_add_handle_old', 'curl_multi_add_handle');
        runkit_function_rename('curl_multi_exec_old', 'curl_multi_exec');
        runkit_function_rename('curl_multi_select_old', 'curl_multi_select');
        runkit_function_rename('curl_error_old', 'curl_error');
        runkit_function_rename('curl_getinfo_old', 'curl_getinfo');
        runkit_function_rename('curl_multi_getcontent_old', 'curl_multi_getcontent');
        runkit_function_rename('curl_multi_remove_handle_old', 'curl_multi_remove_handle');
        runkit_function_rename('curl_close_old', 'curl_close');
        runkit_function_rename('curl_multi_close_old', 'curl_multi_close');
    }

    /**
     * Set up our subject under test and global header state.
     */
    protected function setUp()
    {
        $this->http = new HorusHttp('testHorusHttp', null, 'GREEN');
        self::$mockheaders = array();
        self::$curls = array();
    }


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
