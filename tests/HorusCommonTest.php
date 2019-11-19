<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;

final class HorusCommonTest extends TestCase
{
    public function testEscapeJsonString(): void
    {
        $stringToTest = '{"test1":"123","value1":"456/789"}';
        $expectedString = '{\"test1\":\"123\",\"value1\":\"456\/789\"}';
        $result = HorusCommon::escapeJsonString($stringToTest);
        $this->assertEquals($result, $expectedString);
    }

    public function testGetTime(): void
    {
        $time1 = HorusCommon::utc_time();
        $time2 = HorusCommon::utc_time(3);
        //Times should be UTC
        $this->assertNotEquals(stripos('Z', $time1), -1);
        echo $time1 . "\n" . $time2 . "\n";
        $this->assertEquals(strlen($time2) - strlen($time1), 4);
        $this->assertNotEquals(stripos(',', $time2), -1);
        $this->assertEquals(stripos(',', $time1), false);
    }

    public function testFormatQueryString():void
    {
        $params = array("a"=>1,"b"=>2,"c"=>"A AA");
        $url1 = "http://localhost:9000/test";
        $url2 = $url1 . "?zz=1&qq=2";
        $this->assertEquals(HorusCommon::formatQueryString($url1,$params,FALSE),'?a=1&b=2&c=A+AA','Test without query string, just params');
        $this->assertEquals(HorusCommon::formatQueryString($url1,$params,TRUE),'http://localhost:9000/test?a=1&b=2&c=A+AA','Test without query string, whole url');
        $this->assertEquals(HorusCommon::formatQueryString($url2,$params,FALSE),'&a=1&b=2&c=A+AA','Test with query string, just params');
        $this->assertEquals(HorusCommon::formatQueryString($url2,$params,TRUE),'http://localhost:9000/test?zz=1&qq=2&a=1&b=2&c=A+AA','Test with query string, whole url');
        $this->assertEquals(HorusCommon::formatQueryString($url1,array(),FALSE),'','Test with empty params');
        $this->assertEquals(HorusCommon::formatQueryString($url1,array(),TRUE),$url1,'Test with empty params');
        $this->assertEquals(HorusCommon::formatQueryString($url2,array(),TRUE),$url2,'Test with empty params');
        $this->assertEquals(HorusCommon::formatQueryString($url1,null,FALSE),'','Test with empty params');
    }
}
