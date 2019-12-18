<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_business.php');
require_once('lib/horus_common.php');
require_once('lib/horus_exception.php');
require_once('HorusTestCase.php');

class HorusBusinessTest extends HorusTestCase
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

    function testPerformRoutingError(): void
    {
        $horus = new HorusBusiness('testPerformRoutingError', null, 'OOOO');
        $this->expectException(HorusException::class);
        $horus->performRouting(null, 'application/json', 'application/json', '{"test":"ok"}');
    }

    function testPerformRoutingStandard(): void
    {
        $horus = new HorusBusiness('testPerformRouting', null, 'PPPP');
        $route = json_decode('{
			"source": "singlesource",
			"parameters": [{
					"key": "param1",
					"value": "single"
				}, {
					"key": "sourcex",
					"value": "cristal"
				}
			],
			"destinations": [{
                    "comment": "Destination 1, via proxy",
					"proxy": "http://proxy/horus/horus.php",
					"destination": "http://destination/horus/horusRouter.php",
					"proxyParameters": [{
							"key": "repeat",
							"value": "5"
						}, {
							"key": "bic1",
							"value": "BNPAFRPPXXX"
						}
					],
                    "destParameters": [{
                            "key": "source",
                            "value": "router1"
                    }],
					"delayafter": "2"
				}, {
                    "comment": "Destination 2, direct",
					"destination": "http://direct/horustojms"
				}
			]
        }', TRUE);
        

        self::$curls[] = array( 'url'=>'https://www.xxx.com',
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
                                        "{\"Status\":\"OK\"}",
                                'returnHeaders'=>array(
                                    CURLINFO_HTTP_CODE=>200,
                                    CURLINFO_HEADER_SIZE=>212,

                                ),
                                'returnCode'=>400,
                                'errorMessage'=>'',
                                'returnBody'=>'{"Status":"OK"}');
        self::$curls[] = array( 'url'=>'https://www.yyy.com',
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
                                        "{\"Status\":\"OK\"}",
                                'returnHeaders'=>array(
                                    CURLINFO_HTTP_CODE=>200,
                                    CURLINFO_HEADER_SIZE=>212,

                                ),
                                'returnCode'=>400,
                                'errorMessage'=>'',
                                'returnBody'=>'{"Status":"OK"}');

        $res = $horus->performRouting($route, 'application/json', 'application/json', '{"test":"ok"}');
        $this::assertEquals(2,sizeof($res),'2 queries should have responded');
    }

    function testPerformRoutingStopError(): void
    {
        $horus = new HorusBusiness('testPerformRouting', null, 'QQQQ');
        $route = json_decode('{
            "source": "singlesource",
            "followOnError": "false",
			"parameters": [{
					"key": "param1",
					"value": "single"
				}, {
					"key": "sourcex",
					"value": "cristal"
				}
			],
			"destinations": [{
                    "comment": "Destination 1, via proxy",
					"proxy": "http://proxy/horus/horus.php",
					"destination": "http://destination/horus/horusRouter.php",
					"proxyParameters": [{
							"key": "repeat",
							"value": "5"
						}, {
							"key": "bic1",
							"value": "BNPAFRPPXXX"
						}
					],
                    "destParameters": [{
                            "key": "source",
                            "value": "router1"
                    }],
					"delayafter": "2"
				}, {
                    "comment": "Destination 2, direct",
					"destination": "http://direct/horustojms"
				}
			]
        }', TRUE);
        

        self::$curls[] = array( 'url'=>'https://www.xxx.com',
                                'options'=>array(
                                    CURLOPT_RETURNTRANSFER=>1,
                                    CURLOPT_HTTPHEADER=>array('Content-type: application/json', 'Accept: application/json', 'Expect:', 'X-Business-Id: testHorusHttp'),
                                    CURLOPT_SSL_VERIFYPEER=>False,
                                    CURLOPT_VERBOSE=>True,
                                    CURLOPT_HEADER=>True,
                                    CURLINFO_HEADER_OUT=>True),
                                'data'=>"HTTP/1.1 400 OK\nDate: Thu, 08 Aug 2019 20:22:04 GMT\nExpires: -1\nCache-Control: private, max-age=0\n" . 
                                        "Content-Type: text/html; charset=ISO-8859-1\nAccept-Ranges: none\nVary: Accept-Encoding\nTransfer-Encoding: chunked\n" . 
                                        "\n" . 
                                        "{\"Status\":\"KO\"}",
                                'returnHeaders'=>array(
                                    CURLINFO_HTTP_CODE=>400,
                                    CURLINFO_HEADER_SIZE=>212,

                                ),
                                'returnCode'=>400,
                                'errorMessage'=>'',
                                'returnBody'=>'{"Status":"OK"}');
        self::$curls[] = array( 'url'=>'https://www.yyy.com',
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
                                        "{\"Status\":\"OK\"}",
                                'returnHeaders'=>array(
                                    CURLINFO_HTTP_CODE=>400,
                                    CURLINFO_HEADER_SIZE=>212,

                                ),
                                'returnCode'=>400,
                                'errorMessage'=>'',
                                'returnBody'=>'{"Status":"OK"}');

        $res = $horus->performRouting($route, 'application/json', 'application/json', '{"test":"ok"}');
        
        $this::assertEquals(2,sizeof($res),'1 query should have responded');
    }

    function testParametersMerge(): void {
        $horus = new HorusBusiness('testPerformRouting', null, 'QQQQ');
        $route = json_decode('{
            "source": "singlesource",
            "followOnError": "false",
			"parameters": [{
					"key": "param1",
					"value": "single"
				}, {
					"key": "sourcex",
					"value": "cristal"
				}
			],
			"destinations": [{
                    "comment": "Destination 1, via proxy",
					"proxy": "http://proxy/horus/horus.php",
					"destination": "http://destination/horus/horusRouter.php",
					"proxyParameters": [{
							"key": "repeat",
							"value": "5"
						}, {
							"key": "bic1",
							"value": "BNPAFRPPXXX"
						}
					],
                    "destParameters": [{
                            "key": "source",
                            "value": "router1"
                    }],
					"delayafter": "2"
				}
			]
        }', TRUE);

        self::$curls[] = array( 'url'=>'https://www.xxx.com',
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
                                        "{\"Status\":\"KO\"}",
                                'returnHeaders'=>array(
                                    CURLINFO_HTTP_CODE=>200,
                                    CURLINFO_HEADER_SIZE=>212,

                                ),
                                'returnCode'=>200,
                                'errorMessage'=>'',
                                'returnBody'=>'{"Status":"OK"}');

        $res = $horus->performRouting($route, 'application/json', 'application/json', '{"test":"ok"}',array('repeat'=>'3','extra'=>'true'));

        $this::assertEquals('http://proxy/horus/horus.php?bic1=BNPAFRPPXXX&extra=true&param1=single&repeat=5&sourcex=cristal',self::$curls[0]['url'], 'Url params should be mixed');

    }
}
