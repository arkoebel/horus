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
}
