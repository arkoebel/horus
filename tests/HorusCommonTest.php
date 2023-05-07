<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HorusCommonTest extends TestCase
{
    public function testEscapeJsonString(): void
    {
        $stringToTest = '{"test1":"123","value1":"456/789"}';
        $expectedString = '{\"test1\":\"123\",\"value1\":\"456\/789\"}';
        $result = HorusCommon::escapeJsonString($stringToTest);
        $this->assertEquals($expectedString, $result);
    }

    public function testGetTime(): void
    {
        $time1 = HorusCommon::utcTime();
        $time2 = HorusCommon::utcTime(3);
        //Times should be UTC
        $this->assertNotEquals(-1, stripos('Z', $time1));
        echo $time1 . "\n" . $time2 . "\n";
        $this->assertEquals(4, strlen($time2) - strlen($time1));
        $this->assertNotEquals(-1, stripos(',', $time2));
        $this->assertEquals(false, stripos(',', $time1));
    }

    public function testFormatQueryString():void
    {
        $params = array(array('key'=>"a",'value'=>1),array('key'=>"b",'value'=>2),array('key'=>"c", 'value'=>"A AA"));
        $url1 = "http://localhost:9000/test";
        $url2 = $url1 . "?zz=1&qq=2";
        $this->assertEquals(
            '?a=1&b=2&c=A+AA',
            HorusCommon::formatQueryString($url1, $params, false),
            'Test without query string, just params'
        );
        $this->assertEquals(
            'http://localhost:9000/test?a=1&b=2&c=A+AA',
            HorusCommon::formatQueryString($url1, $params, true),
            'Test without query string, whole url'
        );
        $this->assertEquals(
            '&a=1&b=2&c=A+AA',
            HorusCommon::formatQueryString($url2, $params, false),
            'Test with query string, just params'
        );
        $this->assertEquals(
            'http://localhost:9000/test?zz=1&qq=2&a=1&b=2&c=A+AA',
            HorusCommon::formatQueryString($url2, $params, true),
            'Test with query string, whole url'
        );
        $this->assertEquals(
            '',
            HorusCommon::formatQueryString($url1, array(), false),
            'Test 1 with empty params'
        );
        $this->assertEquals(
            $url1,
            HorusCommon::formatQueryString($url1, array(), true),
            'Test 2 with empty params'
        );
        $this->assertEquals(
            HorusCommon::formatQueryString($url2, array(), true),
            $url2,
            'Test 3 with empty params'
        );
        $this->assertEquals(
            '',
            HorusCommon::formatQueryString($url1, null, false),
            'Test 4 with empty params'
        );
        $paramsUnsorted = array(
            array('key'=>'d','value'=>'1'),
            array('key'=>'b','value'=>'2'),
            array('key'=>'a','value'=>'3'),
            array('key'=>'c','value'=>'4')
        );
        $this->assertEquals(
            '?a=3&b=2&c=4&d=1',
            HorusCommon::formatQueryString('', $paramsUnsorted, false),
            'Test with parameters out of order'
        );
        $paramsDuplicates = array(
            array('key'=>'d','value'=>'1'),
            array('key'=>'b','value'=>'2'),
            array('key'=>'a','value'=>'3'),
            array('key'=>'c','value'=>'4'),
            array('key'=>'a','value'=>'1')
        );
        $this->assertEquals(
            '?a=1&b=2&c=4&d=1',
            HorusCommon::formatQueryString('', $paramsDuplicates, false),
            'Test with duplicate parameters'
        );
    }
}
