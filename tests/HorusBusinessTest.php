<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_business.php');
require_once('lib/horus_common.php');

class HorusBusinessTest extends TestCase
{

    public function testFindMatch(): void
    {
        $horus = new HorusBusiness('testFindMatch', null, 'XXXX');
        $params = json_decode('[{"level1":"value1",
                                 "level2":"value2",
                                 "level3":{
                                     "key1":"value1",
                                     "key2":"value2"}},
                                {"level1":"value10",
                                 "level2":"value20"},
                                {"key3":"value3"}]', true);
        $this::assertEquals($horus->findMatch($params, 0, 'level1'), 'value1');
        $this::assertEquals($horus->findMatch($params, 1, 'level2'), 'value20');
        $this::assertEquals($horus->findMatch($params, 0, 'level3'), array('key1' => 'value1', 'key2' => 'value2'));
        $this::assertEquals($horus->findMatch($params, 0, 'levelX'), '');
        $this::assertEquals($horus->findMatch($params, 5, 'level1'), '');
    }

    public function testLocate(): void
    {
        $horus = new HorusBusiness('testLocate', null, 'XXXX');
        $params = json_decode('[{"query":"value1","queryMatch":"value2"},
                                {"query":"value10","queryMatch":"value20"},
                                {"query":"value10","queryMatch":"value21"},
                                {"query":"zip"},
                                {"query":"zip"}]', true);
        $this::assertEquals($horus->locate($params, 'value1', 'isthisokforvalue2or not?'), 0);
        $this::assertEquals($horus->locate($params, 'value10', 'isthisokforvalue20or not?'), 1);
        $this::assertEquals($horus->locate($params, 'value10', 'isthisokforvalue21or not?'), 2);
        $this::assertEquals($horus->locate($params, 'value10', 'isthisokforvalueor not?'), -1);
        $this::assertEquals($horus->locate($params, 'zip', 'isthisokforvalue2or not?'), 4);
        $this::assertEquals($horus->locate(null, 'AAA', 'BBB'), -1);
        $this::assertEquals($horus->locate(array(), 'AAA', 'BBB'), -1);
        $this::assertEquals($horus->locate('', 'AAA', 'BBB'), -1);
        $this::assertEquals($horus->locate($params, null, 'BBB'), -1);
        $this::assertEquals($horus->locate($params, 'zip', null), -1);
    }

    public function testLocateJson(): void
    {

        $horus = new HorusBusiness('testLocateJson', null, 'XXXX');
        $params = json_decode('[
            {"query": {"key": "key1", "value": "value1"}},  
            {"query": {"key": "key1", "value": "value1"}, "queryMatch": "match"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"}},
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"}, 
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"},           
            {"query": {"key": "zip"},"queryMatch": "match3"}, 
            {"query": {"key": "zip"}}
        ]', true);
        $input1 = array('key1' => 'value1', 'someotherkey' => 'XXX', 'parttomatch' => 'true');
        $input2 = array('key1' => 'value1', 'someotherkey' => 'XXX', 'part' => 'true');
        $qparams1 = array('qkey1' => 'nope');
        $qparams2 = array('qkey1' => 'qvalue1');

        $this::assertEquals($horus->locateJson($params, $input1, null), 1);
        $this::assertEquals($horus->locateJson($params, $input1, $qparams1), 1);
        $this::assertEquals($horus->locateJson($params, $input1, $qparams2), 4);
        $this::assertEquals($horus->locateJson($params, $input2, null), 0);
        $this::assertEquals($horus->locateJson($params, $input2, $qparams1), 0);
        $this::assertEquals($horus->locateJson($params, $input2, $qparams2), 2);
        $this::assertEquals($horus->locateJson($params, array(), null), -1);
        $this::assertEquals($horus->locateJson($params, null, null), -1);
        $this::assertEquals($horus->locateJson(null, array(), null), -1);
    }
}
