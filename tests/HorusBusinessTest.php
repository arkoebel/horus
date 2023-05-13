<?php

declare (strict_types = 1);

require_once "lib/horus_utils.php";
require_once 'lib/horus_curlInterface.php';
require_once 'lib/horus_curl.php';
require_once 'lib/horus_http.php';
require_once 'lib/horus_common.php';

require_once 'lib/horus_business.php';
require_once 'lib/horus_common.php';
require_once 'lib/horus_exception.php';
require_once 'HorusTestCase.php';
require_once 'HorusCurlMock.php';

const TEST_OK = '{"test":"ok"}';
const STATUS_OK = '{"Status":"OK"}';
class HorusBusinessTest extends HorusTestCase
{

    public function testFindMatch(): void
    {
        $horus = new HorusBusiness('testFindMatch', null, 'XXXX', self::$tracer, new Horus_CurlMock());
        $params = json_decode('[{"level1":"value1",
                                 "level2":"value2",
                                 "level3":{
                                     "key1":"value1",
                                     "key2":"value2"}},
                                {"level1":"value10",
                                 "level2":"value20"},
                                {"key3":"value3"}]',
                                true
                            );
        $this::assertEquals($horus->findMatch($params, 0, 'level1'), 'value1');
        $this::assertEquals($horus->findMatch($params, 1, 'level2'), 'value20');
        $this::assertEquals($horus->findMatch($params, 0, 'level3'), array('key1' => 'value1', 'key2' => 'value2'));
        $this::assertEquals($horus->findMatch($params, 0, 'levelX'), '');
        $this::assertEquals($horus->findMatch($params, 5, 'level1'), '');
    }

    public function testLocate(): void
    {
        $horus = new HorusBusiness('testLocate', null, 'XXXX', self::$tracer, new Horus_CurlMock());
        $params = json_decode('[{"query":"value1","queryMatch":"value2","comment":"line1"},
                                {"query":"value10","queryMatch":"value20","comment":"line2"},
                                {"query":"value10","queryMatch":"value21","comment":"line3"},
                                {"query":"zip","comment":"line4"},
                                {"query":"zip","comment":"line5"}]',
                            true
                        );
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

        $horus = new HorusBusiness('testLocateJson', null, 'XXXX', self::$tracer, new Horus_CurlMock());
        $params = json_decode('[
            {"query": {"key": "key1", "value": "value1"}},
            {"query": {"key": "key1", "value": "value1"}, "queryMatch": "match"},
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"}},
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"},
            {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1", "queryValue": "qvalue1"},"queryMatch": "match"},
            {"query": {"key": "zip"},"queryMatch": "match3"},
            {"query": {"key": "zip"}}
        ]',
        true
    );
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

    public function testPerformRoutingError(): void
    {
        $horus = new HorusBusiness('testPerformRoutingError', null, 'OOOO', self::$tracer, new Horus_CurlMock());
        $this->expectException(HorusException::class);
        $horus->performRouting(null, HorusCommon::JS_CT, HorusCommon::JS_CT, TEST_OK, array(), self::$rootSpan);
    }

    public function testPerformRoutingStandard(): void
    {
        $rootSpan = self::$tracer->spanBuilder('Test Business')->setAttribute('OTEL_SERVICE_NAME', 'toto')->startSpan();
        $scope = $rootSpan->activate();
        $horus = new HorusBusiness('testPerformRouting', null, 'PPPP', self::$tracer, new Horus_CurlMock());
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
        }',
        true
    );

        self::$curls[] = array('url' => 'https://www.xxx.com',
            'options' => array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => array(
                    'Content-type: application/json',
                    'Accept: application/json',
                    'Expect:',
                    'X-Business-Id: testHorusHttp'),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_VERBOSE => true,
                CURLOPT_HEADER => true,
                CURLINFO_HEADER_OUT => true),
            'data' => "HTTP/1.1 200 OK\n" .
                        "Date: Thu, 08 Aug 2019 20:22:04 GMT\n" .
                        "Expires: -1\n" .
                        "Cache-Control: private, max-age=0\n" .
                        "Content-Type: text/html; charset=ISO-8859-1\n" .
                        "Accept-Ranges: none\n" .
                        "Vary: Accept-Encoding\n" .
                        "Transfer-Encoding: chunked\n" .
                        "\n" .
                        STATUS_OK,
            'returnHeaders' => array(
                CURLINFO_HTTP_CODE => 200,
                CURLINFO_HEADER_SIZE => 212,

            ),
            'returnCode' => 400,
            'errorMessage' => '',
            'returnBody' => STATUS_OK);
        self::$curls[] = array('url' => 'https://www.yyy.com',
            'options' => array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => array(
                    'Content-type: application/json',
                    'Accept: application/json',
                    'Expect:',
                    'X-Business-Id: testHorusHttp'
                ),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_VERBOSE => true,
                CURLOPT_HEADER => true,
                CURLINFO_HEADER_OUT => true),
            'data' => "HTTP/1.1 200 OK\n" .
                        "Date: Thu, 08 Aug 2019 20:22:04 GMT\n" .
                        "Expires: -1\n" .
                        "Cache-Control: private, max-age=0\n" .
                        "Content-Type: text/html; charset=ISO-8859-1\n" .
                        "Accept-Ranges: none\n" .
                        "Vary: Accept-Encoding\n" .
                        "Transfer-Encoding: chunked\n" .
                        "\n" .
                        STATUS_OK,
            'returnHeaders' => array(
                CURLINFO_HTTP_CODE => 200,
                CURLINFO_HEADER_SIZE => 212,

            ),
            'returnCode' => 400,
            'errorMessage' => '',
            'returnBody' => '{"Status":"OK"}');

        $res = $horus->performRouting(
            $route,
            HorusCommon::JS_CT,
            HorusCommon::JS_CT,
            TEST_OK,
            array(),
            $rootSpan
        );
        $rootSpan->end();
        $scope->detach();
        $this::assertEquals(2, sizeof($res), '2 queries should have responded');
    }

    public function testPerformRoutingStopError(): void
    {
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
        }',
        true
    );

        $mock = new Horus_CurlMock();
        $options = array(
            CURLOPT_URL => 'https://www.xxx.com',
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_VERBOSE => true,
            CURLOPT_HEADER => true);
        $mock->setResponse(
            "HTTP/1.1 400 OK\n" .
            "Date: Thu, 08 Aug 2019 20:22:04 GMT\n" .
            "Expires: -1\n" .
            "Cache-Control: private, max-age=0\n" .
            "Content-Type: text/html; charset=ISO-8859-1\n" .
            "Accept-Ranges: none\n" .
            "Vary: Accept-Encoding\n" .
            "Transfer-Encoding: chunked\n" .
            "\n" .
            "{\"Status\":\"KO\"}",
            $options
        );
        $mock->setInfo(array(
            CURLINFO_HTTP_CODE => 400,
            CURLINFO_HEADER_SIZE => 212,
            CURLINFO_HEADER_OUT => true,
        ), $options);

        $options2 = array(
            CURLOPT_URL => 'https://www.yyy.com',
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_VERBOSE => true,
            CURLOPT_HEADER => true);
        $mock->setResponse(
            "HTTP/1.1 400 OK\n" .
            "Date: Thu, 08 Aug 2019 20:22:04 GMT\n" .
            "Expires: -1\n" .
            "Cache-Control: private, max-age=0\n" .
            "Content-Type: text/html; charset=ISO-8859-1\n" .
            "Accept-Ranges: none\n" .
            "Vary: Accept-Encoding\n" .
            "Transfer-Encoding: chunked\n" .
            "\n" .
            "{\"Status\":\"KO\"}",
            $options2
        );
        $mock->setInfo(array(
            CURLINFO_HTTP_CODE => 400,
            CURLINFO_HEADER_SIZE => 212,
            CURLINFO_HEADER_OUT => true,
        ), $options2);

        $headerImpl = new Horus_HeaderMock();
        $this->http->setCurlImpl($mock);
        $this->http->setHeaderImpl($headerImpl);
        $horus = new HorusBusiness('testPerformRouting', null, 'QQQQ', self::$tracer, $mock);

        $res = $horus->performRouting(
            $route,
            HorusCommon::JS_CT,
            HorusCommon::JS_CT,
            TEST_OK,
            array(),
            self::$rootSpan
        );

        $this::assertEquals(2, sizeof($res), '1 query should have responded');
    }

    public function testParametersMerge(): void
    {
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
        }',
        true
    );

        $mock = new Horus_CurlMock();
        $options = array(
            CURLOPT_URL => 'http://proxy/horus/horus.php',
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_VERBOSE => true,
            CURLOPT_HEADER => true);
        $mock->setResponse(
            "HTTP/1.1 200 OK\n" .
            "Date: Thu, 08 Aug 2019 20:22:04 GMT\n" .
            "Expires: -1\n" .
            "Cache-Control: private, max-age=0\n" .
            "Content-Type: text/html; charset=ISO-8859-1\n" .
            "Accept-Ranges: none\n" .
            "Vary: Accept-Encoding\n" .
            "Transfer-Encoding: chunked\n" .
            "\n" .
            "{\"Status\":\"KO\"}",
            $options
        );
        $mock->setInfo(array(
            CURLINFO_HTTP_CODE => 200,
            CURLINFO_HEADER_SIZE => 212,
            CURLINFO_HEADER_OUT => true,
        ), $options);

        $horus = new HorusBusiness('testPerformRouting', null, 'QQQQ', self::$tracer, $mock);
        $horus->performRouting(
            $route,
            HorusCommon::JS_CT,
            HorusCommon::JS_CT,
            TEST_OK,
            array('repeat' => '3', 'extra' => 'true'),
            self::$rootSpan
        );

        $this::assertEquals(
            'http://proxy/horus/horus.php?bic1=BNPAFRPPXXX&extra=true&param1=single&repeat=5&sourcex=cristal',
            $mock->effectiveUrls[0],
            'Url params should be mixed'
        );

    }

    public function testGetTemplate(): void
    {
        $template1 = 'azer${test1}${test2}.ccc';
        $template2 = 'azer${test1}${test3}.ccc';
        $template3 = 'azer.ccc';
        $variables = array('test1' => '123', 'test2' => '456');
        $this::assertEquals('azer123456.ccc', HorusBusiness::getTemplateName($template1, $variables));
        $this::assertEquals('azer123.ccc', HorusBusiness::getTemplateName($template2, $variables));
        $this::assertEquals('azer.ccc', HorusBusiness::getTemplateName($template3, $variables));
        $this::assertEquals('azer.ccc', HorusBusiness::getTemplateName($template1, array()));
    }
}
