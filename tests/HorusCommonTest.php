<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;

final class HorusCommonTest extends TestCase
{
    public function testEscapeJsonString(): void 
    {
        $stringToTest='{"test1":"123","value1":"456/789"}';
        $expectedString = '{\"test1\":\"123\",\"value1\":\"456/789\"}';
        $result = HorusCommon::escapeJsonString($stringToTest);
        $this->assertEquals($result,$expectedString);
    }

    //public function testGetTime(): void


}