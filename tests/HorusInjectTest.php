<?php

declare(strict_types=1);


use Jaeger\Config;
use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_inject.php');
require_once('HorusTestCase.php');
//require_once('HorusHttpTest.php');

class HorusInjectTest extends HorusTestCase {

    function testInjectXml(): void {
        $injector = new HorusInjector('injectorbusinessid',null,self::$tracer);
        $json = '{"template": "broadcast_camt053.xml","repeat":"1","sourcetype":"application/xml","destinationcontent":"application/xml"}';
        $ret = $injector->doInject($json,'',self::$rootSpan);
        $this::assertNotNull($ret,'doInject returns something (xml).');
        $xml = simplexml_load_string($ret);
        $this::assertNotNull($xml,'doInject returns actual XML.');
        $xml->registerXPathNamespace('a','urn:isis:generic');
        $xpath=$xml->xpath("/a:Document/a:QueryParams/a:Param[@name='bic']");
        $this::assertEquals((string) $xpath[0],'BNPAFRPPXXX','doInject returns the right data at XPath.');
    }

    function testInjectJson(): void {
        $injector = new HorusInjector('injectorbusinessid',null,self::$tracer);
        $json = '{"template": "position_msg_response.json","repeat":"1","sourcetype":"application/json","destinationcontent":"application/json","attr":{"msgref":"123456","ipsystem":"AAA","ipparticipant":"BBB","ipaccountid":"CCC"}}';
        $ret = $injector->doInject($json,'',self::$rootSpan);
        $this::assertNotNull($ret, 'doInject returns something (json).');
        $this::assertTrue(in_array('Content-type: application/json',self::$mockheaders[1]),'doInject returns json content-type.');
        $outjson = json_decode($ret,true);
        $this::assertNotNull($outjson, 'doInject returns actual json.');
        $this::assertEquals($outjson['originalMsgRef'],'123456', 'doInject returns the right json data.');
    }

    function testConvert(): void {
        $injector = new HorusInjector('injectorbusinessid',null,self::$tracer);
        $json = '{"template": "broadcast_camt053.xml","repeat":"1","sourcetype":"application/xml","destinationcontent":"application/json"}';
        $ret = $injector->doInject($json,'',self::$rootSpan);
        $this::assertTrue(in_array('Content-type: application/xml',self::$mockheaders[1]), 'doInject returns xml content-type');
        $this::assertNotNull($ret, 'doInject returns something (json container for xml)');
        $xml = json_decode($ret,true);
        $this::assertNotNull($xml, 'doInject returns actual json');
        $this::assertNotNull($xml['payload'], 'doInject returns xml under the payload json element.');
    }
}