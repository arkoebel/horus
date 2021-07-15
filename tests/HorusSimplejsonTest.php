<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_inject.php');
require_once('lib/horus_exception.php');
require_once('HorusTestCase.php');
//require_once('HorusHttpTest.php');

class HorusSimplejsonTest extends HorusTestCase
{

    function testSelectionEmptyBody(): void
    {
        $simplejson = new HorusSimpleJson("mybusinessid", null, null,self::$tracer);

        $json = '{';
        json_decode($json, true);
        try {
            $res = $simplejson->selection(null, '', '', self::$rootSpan);
        } catch (HorusException $e) {
            $this::assertEquals(self::$mockheaders[0], array("HTTP/1.1 400 MALFORMED URL", TRUE, 400), 'Null input should generate http/400');
            $outjson = json_decode($e->getMessage(), true);
            $this::assertNotNull($outjson, 'Should return a valid json');
            $this::assertTrue(array_key_exists('message', $outjson), 'Returned message should contain a "message" tag');
        }
    }

    function testSelectionNoMatch(): void
    {
        $matches = json_decode('[
            {"query": {"key": "key1", "value": "value1"}},  
            {"query": {"key": "key1", "value": "value1"}, "queryMatch": "match"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"}},
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"},           
            {"query": {"key": "zip"},"queryMatch": "match3"}, 
            {"query": {"key": "zip"}}
        ]', true);
        $simplejson = new HorusSimpleJson("mybusinessid", null, $matches,self::$tracer);
        $input = json_decode('{"nothing": "to decode"}', true);

        $res = '';
        try {
            $res = $simplejson->selection($input, '', 'application/json',self::$rootSpan);
        } catch (HorusException $e) {
            $this::assertEquals(self::$mockheaders[0], array("HTTP/1.1 400 MALFORMED URL", TRUE, 400), 'Unmatched input should generate http/400');
            $outjson = json_decode($e->getMessage(), true);
            $this::assertNotNull($outjson, 'Should return a valid json');
            $this::assertTrue(array_key_exists('message', $outjson), 'Returned message should contain a "message" tag');
            $this::assertEquals($outjson['message'], 'No match found', 'Error message should be No match found');
        }
    }

    function testSelectionForcedError(): void
    {
        $matches = json_decode('[
            {"query": {"key": "key1", "value": "value1"}},  
            {"query": {"key": "key1", "value": "value1"}, "queryMatch": "match", "errorTemplate":"generic_error.json","displayError":"On"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"}},
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"},           
            {"query": {"key": "zip"},"queryMatch": "match3"}, 
            {"query": {"key": "zip"}}
        ]', true);
        $simplejson = new HorusSimpleJson("mybusinessid", null, $matches,self::$tracer);
        $input = json_decode('{"key1": "value1","key2":"matched"}', true);

        $res = '';
        try {
            $res = $simplejson->selection($input, '', 'application/json',self::$rootSpan);
        } catch (HorusException $e) {
            $this::assertEquals(self::$mockheaders[0], array("HTTP/1.1 400 MALFORMED URL", TRUE, 400), 'Forced error output should generate http/400');
            $outjson = json_decode($e->getMessage(), true);
            $this::assertNotNull($outjson, 'Should return a valid json');
            $this::assertTrue(array_key_exists('message', $outjson), 'Returned message should contain a "message" tag');
            $this::assertEquals($outjson['message'], 'Requested error', 'Error message should be Requested error');
        }
    }

    function testSelectionOkSingle(): void
    {
        $matches = json_decode('[
            {"query": {"key": "key1", "value": "value1"}},  
            {"query": {"key": "key1", "value": "value1"}, "queryMatch": "match", 
                "responseTemplate":"position_msg_response.json",
                "responseFormat":"application/json", 
                "parameters": {"var1":"value1","var2":"value2"}}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"}},
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"},           
            {"query": {"key": "zip"},"queryMatch": "match3"}, 
            {"query": {"key": "zip"}}
        ]', true);

        $simplejson = new HorusSimpleJson("mybusinessid", null, $matches,self::$tracer);
        $input = json_decode('{"key1": "value1","key2":"matched", "value1": "returnvalue1","value2":"returnvalue2"}', true);
        $expected = array('templates' => array('position_msg_response.json'), 'formats' => array('application/json'), 'variables' => array('var1' => 'returnvalue1', 'var2' => 'returnvalue2'), 'multiple' => false);
        $res = $simplejson->selection($input, 'application/json', '', 'application/json',self::$rootSpan);
        $this::assertNotNull($res, 'Should return something');
        $this::assertTrue(is_array($res), 'Should return an array');
        $this::assertEquals($res, $expected, 'Compare to expected result');
    }

    function testSelectionOkMultiple(): void
    {
        $matches = json_decode('[
            {"query": {"key": "key1", "value": "value1"}},  
            {"query": {"key": "key1", "value": "value1"}, "queryMatch": "match", 
                "responseTemplate":["position_msg_response.json","position_msg_response_error.json"],
                "responseFormat":["application/json","application/json"], 
                "parameters": {"var1":"value1","var2":"value2"}}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"}},
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"},           
            {"query": {"key": "zip"},"queryMatch": "match3"}, 
            {"query": {"key": "zip"}}
        ]', true);

        $simplejson = new HorusSimpleJson("mybusinessid", null, $matches,self::$tracer);
        $input = json_decode('{"key1": "value1","key2":"matched", "value1": "returnvalue1","value2":"returnvalue2"}', true);
        $expected = array('templates' => array('position_msg_response.json', 'position_msg_response_error.json'), 'formats' => array('application/json', 'application/json'), 'variables' => array('var1' => 'returnvalue1', 'var2' => 'returnvalue2'), 'multiple' => true);
        $res = $simplejson->selection($input, 'application/json', '', 'application/json',self::$rootSpan);
        $this::assertNotNull($res, 'Should return something');
        $this::assertTrue(is_array($res), 'Should return an array');
        $this::assertEquals($res, $expected, 'Compare to expected result');
    }

    function testInjectError(): void
    {
        $matches = json_decode('[
            {"query": {"key": "key1", "value": "value1"}},  
            {"query": {"key": "key1", "value": "value1"}, "queryMatch": "match", "errorTemplate":"generic_error.json","displayError":"On"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"}},
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"},           
            {"query": {"key": "zip"},"queryMatch": "match3"}, 
            {"query": {"key": "zip"}}
        ]', true);
        $simplejson = new HorusSimpleJson("mybusinessid", null, $matches,self::$tracer);
        $input = '{"key1": "value1","key2":"matched"}';

        try {
            $res = $simplejson->doInject($input, '', 'application/json', array(),self::$rootSpan);
        } catch (HorusException $e) {
            $this::assertEquals(self::$mockheaders[0], array("HTTP/1.1 400 MALFORMED URL", TRUE, 400), 'Forced error output should generate http/400');
            $outjson = json_decode($e->getMessage(), true);
            $this::assertNotNull($outjson, 'Should return a valid json');
            $this::assertTrue(array_key_exists('message', $outjson), 'Returned message should contain a "message" tag');
            $this::assertEquals($outjson['message'], 'Requested error', 'Error message should be Requested error');
        }
    }

    function testInjectSingle(): void
    {
        $matches = json_decode('[
            {"query": {"key": "key1", "value": "value1"}},  
            {"query": {"key": "key1", "value": "value1"}, "queryMatch": "match", 
                "responseTemplate":"position_msg_response.json",
                "responseFormat":"application/json", 
                "parameters": {"ipsystem":"ipsystem","ipparticipant":"ipparticipant"}}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"}},
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"},           
            {"query": {"key": "zip"},"queryMatch": "match3"}, 
            {"query": {"key": "zip"}}
        ]', true);

        $simplejson = new HorusSimpleJson("mybusinessid", null, $matches,self::$tracer);
        $input = '{"key1":"value1", "key2":"matched", "ipsystem":"returnvalue1", "ipparticipant":"returnvalue2"}';
        $res = $simplejson->doInject($input, '', 'application/json', array('msgref' => 'returnvalue3', 'ipaccountid' => 'returnvalue4'),self::$rootSpan);
        $this::assertNotNull($res, 'Should return something');
        $outres = json_decode($res, true);
        $this::assertTrue(is_array($outres), 'Should return a valid json');
        $this::assertEquals($outres['msgRef'], 'returnvalue3', 'Compare to expected result');
        $this::assertEquals($outres['IPAccountId'], 'returnvalue4', 'Compare to expected result');
    }

    function testInjectMultiple(): void
    {
        $matches = json_decode('[
            {"query": {"key": "key1", "value": "value1"}},  
            {"query": {"key": "key1", "value": "value1"}, "queryMatch": "match", 
                "responseTemplate": ["position_msg_response.json","position_msg_response.json"],
                "responseFormat": ["application/json", "application/json"], 
                "parameters": {"ipsystem":"ipsystem","ipparticipant":"ipparticipant"}}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"}},
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"},           
            {"query": {"key": "zip"},"queryMatch": "match3"}, 
            {"query": {"key": "zip"}}
        ]', true);

        $simplejson = new HorusSimpleJson("mybusinessid", null, $matches,self::$tracer);
        $input = '{"key1":"value1", "key2":"matched", "ipsystem":"returnvalue1", "ipparticipant":"returnvalue2"}';
        $res = $simplejson->doInject($input, '', 'application/json', array('msgref' => 'returnvalue3', 'ipaccountid' => 'returnvalue4'),self::$rootSpan);
        $this::assertNotNull($res, 'Should return something');
        $this::assertFalse(strpos($res, '--') === FALSE, 'Should contain a multipart boundary');
        $boundary = explode("\r\n", $res);
        $boundary = $boundary[0];
        $responses = explode($boundary, $res);
        $this::assertEquals(sizeof($responses), 4, '2 Responses are expected (data+crlf)');
        $firstresponse = implode('', array_slice(explode("\n", $responses[1]), 4));
        $firstresponse = base64_decode($firstresponse);
        $outres = json_decode($firstresponse, true);
        $this::assertTrue(is_array($outres), 'Should return a valid json');
        $this::assertEquals($outres['msgRef'], 'returnvalue3', 'Compare to expected result');
        $this::assertEquals($outres['IPAccountId'], 'returnvalue4', 'Compare to expected result');
    }
}
