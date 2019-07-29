<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_business.php');

class HorusBusinessTest extends TestCase {

    public function testFindMatch(): void{
        $horus = new HorusBusiness();
        $params = json_decode('[{"level1":"value1",
                                 "level2":"value2",
                                 "level3":{
                                     "key1":"value1",
                                     "key2":"value2"}},
                                {"level1":"value10",
                                 "level2":"value20"},
                                {"key3":"value3"}]',true);
        $this::assertEquals($horus->findMatch($params,0,"level1"),"value1");
        $this::assertEquals($horus->findMatch($params,1,"level2"),"value20");
        $this::assertEquals($horus->findMatch($params,0,"level3"),array("key1"=>"value1","key2"=>"value2"));
        $this::assertEquals($horus->findMatch($params,0,"levelX"),'');
        $this::assertEquals($horus->findMatch($params,5,"level1"),'');
    }

    public function testLocate(): void {
        $horus = new HorusBusiness();
        $params = json_decode('[{"query":"value1","queryMatch":"value2"},
                                {"query":"value10","queryMatch":"value20"},
                                {"query":"value10","queryMatch":"value21"},
                                {"query":"zip"},
                                {"query":"zip"}]',true);
        $this::assertEquals($horus->locate($params, "value1", "isthisokforvalue2or not?"),0);
        $this::assertEquals($horus->locate($params, "value10", "isthisokforvalue20or not?"),1);
        $this::assertEquals($horus->locate($params, "value10", "isthisokforvalue21or not?"),2);
        $this::assertEquals($horus->locate($params, "value10", "isthisokforvalueor not?"),-1);
        $this::assertEquals($horus->locate($params, "zip", "isthisokforvalue2or not?"),4);
        
        
    }


}