<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_xml.php');
require_once('lib/horus_recurse.php');
require_once('HorusTestCase.php');
require_once('lib/horus_exception.php');

class HorusRecurseTest extends HorusTestCase
{

    function testRecurseXml(): void
    {

        $config = json_decode('{
            "section": "section1",
            "content-type": "application/xml",
            "comment": "Main PACS structure",
            "schema": "cristal.xsd",
            "namespaces": [{
                    "prefix": "h",
                    "namespace": "urn:iso:std:iso:20022:tech:xsd:head.001.001.01"
                }, {
                    "prefix": "u",
                    "element": "Document"
                }, {
                    "prefix": "d", 
                    "namespace": "urn:xxx"
                }
            ],
            "rootElement": "/d:PDU",
            "parts": [{
                    "order": "1",
                    "comment": "Header transformation",
                    "path": "/body/h:AppHdr",
                    "transformUrl": "http://horus/horus.php",
                    "targetPath": "/d:PDU/d:Body/h:AppHdr"
                }, {
                    "order": "2",
                    "comment": "PACS Document transformation",
                    "variables": {
                            "var1":"/body/h:AppHdr/h:headerElement",             
                            "var2":"/body/u:Document/u:documentElement"
                    },
                    "path": "/body/u:Document",
                    "transformUrl": "http://horus/horus.php",
                    "targetPath": "/d:PDU/d:Body/u:Document"
                }
            ]
        }', true);

        $xml = '<body><AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><headerElement>AAA</headerElement></AppHdr><Document xmlns="urn:testns"><documentElement>BBB</documentElement></Document></body>';
        $recurse = new HorusRecurse('AAA', null);
        self::$curlCounter = 0;
        self::$curls[] = array(
            'url' => 'http://localhost',
            'options' => array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => array('Content-Type: application/xml', 'Accept: application/xml', 'Expect:', 'X-Business-Id: PPPP'),
                CURLOPT_SSL_VERIFYPEER => False,
                CURLOPT_VERBOSE => True,
                CURLOPT_HEADER => True,
                CURLINFO_HEADER_OUT => True
            ),
            'data' => "HTTP/1.1 200 OK\nDate: Sun, 20 Oct 2019 11:22:04 GMT\nExpires: -1\nCache-Control: private, max-age=0\n" .
                "Content-Type: application/xml; charset=ISO-8859-1\nAccept-Ranges: none\nVary: Accept-Encoding\nTransfer-Encoding: chunked\n" .
                "\n" .
                '<?xml version="1.0"?>' . "\n" . '<AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><headerReturnElement>ZZZ</headerReturnElement></AppHdr>' . "\n",
            'returnHeaders' => array(
                CURLINFO_HTTP_CODE => 200,
                CURLINFO_HEADER_SIZE => 218
            ),
            'returnCode' => 200,
            'errorMessage' => '',
            'returnBody' => '<AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><headerReturnElement>ZZZ</headerReturnElement></AppHdr>'
        );
        self::$curls[] = array(
            'url' => 'http://localhost',
            'options' => array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => array('Content-Type: application/xml', 'Accept: application/xml', 'Expect:', 'X-Business-Id: PPPP'),
                CURLOPT_SSL_VERIFYPEER => False,
                CURLOPT_VERBOSE => True,
                CURLOPT_HEADER => True,
                CURLINFO_HEADER_OUT => True
            ),
            'data' => "HTTP/1.1 200 OK\nDate: Sun, 20 Oct 2019 11:22:04 GMT\nExpires: -1\nCache-Control: private, max-age=0\n" .
                "Content-Type: application/xml; charset=ISO-8859-1\nAccept-Ranges: none\nVary: Accept-Encoding\nTransfer-Encoding: chunked\n" .
                "\n" .
                '<?xml version="1.0"?>' . "\n" . '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>RE34567890</MsgId><CreDtTm>2019-10-20T11:56:36</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPP</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPP</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>' . "\n",
            'returnHeaders' => array(
                CURLINFO_HTTP_CODE => 200,
                CURLINFO_HEADER_SIZE => 218
            ),
            'returnCode' => 200,
            'errorMessage' => '',
            'returnBody' => '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>RE34567890</MsgId><CreDtTm>2019-10-20T11:56:36</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPP</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPP</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>'
        );

        $result = $recurse->doRecurseXml($xml, $config);

        var_dump($result);

        $this::assertEquals('<?xml version="1.0"?>' . "\n" . '<PDU xmlns="urn:xxx"><Body><AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><headerReturnElement>ZZZ</headerReturnElement></AppHdr><Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>RE34567890</MsgId><CreDtTm>2019-10-20T11:56:36</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPP</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPP</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document></Body></PDU>' . "\n", $result);
    }

    function testGetSection(): void
    {
        $config = json_decode('[{
            "section": "section1",
            "content-type": "application/xml",
            "comment": "Main PACS structure",
            "schema": "cristal.xsd",
            "namespaces": [{
                    "prefix": "h",
                    "namespace": "urn:iso:std:iso:20022:tech:xsd:head.001.001.01"
                }, {
                    "prefix": "u",
                    "element": "Document"
                }, { "prefix": "d", "namespace": "xxx"}
            ],
            "rootElement": "/d:PDU/d:Body",
            "parts": [{
                    "order": "1",
                    "comment": "Header transformation",
                    "path": "/body/h:AppHdr",
                    "transformUrl": "http://horus/horus.php",
                    "targetPath": "/d:PDU/d:Body/h:AppHdr"
                }, {
                    "order": "2",
                    "comment": "PACS Document transformation",
                    "variables": {
                            "var1":"/body/h:AppHdr/h:headerElement",             
                            "var2":"/body/u:Document/u:documentElement"
                    },
                    "path": "/body/u:Document",
                    "transformUrl": "http://horus/horus.php",
                    "targetPath": "/d:PDU/d:Body/u:Document"
                }
            ]
        }]', true);

        $recurse = new HorusRecurse('AAA', null);
        $this::assertNull($recurse->findSection('azerty', $config));
        $this::assertNotNull($recurse->findSection('section1', $config));
    }

    function testGetNSUriFromPrefix(): void
    {

        // getNSUriFromPrefix($prefix,$namespaces){

        $this::assertEquals('', HorusRecurse::getNSUriFromPrefix(null, array()));
        $this::assertEquals('', HorusRecurse::getNSUriFromPrefix('', array()));
        $this::assertEquals('', HorusRecurse::getNSUriFromPrefix('abc', array()));
        $this::assertEquals('', HorusRecurse::getNSUriFromPrefix('abc', null));

        $namespaces = json_decode('[{"prefix":"a","namespace":"auri"},{"prefix":"b","namespace":"buri"},{"prefix":"c","namespace":"curi"}]', true);


        $this::assertEquals('curi', HorusRecurse::getNSUriFromPrefix('c', $namespaces));
        $this::assertEquals('buri', HorusRecurse::getNSUriFromPrefix('b', $namespaces));
        $this::assertEquals('auri', HorusRecurse::getNSUriFromPrefix('a', $namespaces));
    }

    function testAddPath(): void
    {
        $doc = new DomDocument();
        $root = new DomElement('root', '', 'rootns');
        $doc->appendChild($root);

        $namespaces = json_decode('[{"prefix":"r","namespace":"rootns"},{"prefix":"b","namespace":"buri"},{"prefix":"c","namespace":"curi"}]', true);
        $recurse = new HorusRecurse('ABCD', null);
        $result = $recurse->addPath($root, '/r:root/c:test/b:test2/b:othertest', $namespaces);

        $output = '<?xml version="1.0"?>' . "\n" . '<root xmlns="rootns"><test xmlns="curi"><test2 xmlns="buri"/></test></root>' . "\n";
        $this::assertEquals($output, $result->ownerDocument->saveXml());
    }

    function testAddPath2(): void
    {
        $doc = new DomDocument();
        $root = new DomElement('root');
        $doc->appendChild($root);

        $namespaces = json_decode('[{"prefix":"b","namespace":"buri"},{"prefix":"c","namespace":"curi"}]', true);
        $recurse = new HorusRecurse('DCBA', null);
        $result = $recurse->addPath($root, '/root/c:test/b:test2/b:othertest', $namespaces);
        $result = $recurse->addPath($root, '/root/c:test/b:test2/b:someothertest', $namespaces);

        $output = '<?xml version="1.0"?>' . "\n" . '<root><test xmlns="curi"><test2 xmlns="buri"/></test></root>' . "\n";
        $this::assertEquals($output, $result->ownerDocument->saveXml());
        $this::assertEquals('test2', $result->localName);
        $this::assertEquals('buri', $result->namespaceURI);
    }

    function testAddPath3(): void
    {
        $doc = new DomDocument();
        $root = new DomElement('root');
        $doc->appendChild($root);

        $namespaces = json_decode('[{"prefix":"b","namespace":"buri"},{"prefix":"c","namespace":"curi"}]', true);
        $recurse = new HorusRecurse('ZYWX', null);
        $result = $recurse->addPath($root, '/root/b:othertest', $namespaces);
        $result = $recurse->addPath($root, '/root/b:someothertest', $namespaces);

        $output = '<?xml version="1.0"?>' . "\n" . '<root/>' . "\n";
        $this::assertEquals($output, $result->ownerDocument->saveXml());
        $this::assertEquals('root', $result->localName);
        $this::assertNull($result->namespaceURI);
    }

    function testConstants(): void
    {

        $config = json_decode('{
        "section": "section1",
        "content-type": "application/xml",
        "comment": "Main PACS structure",
        "schema": "cristal.xsd",
        "namespaces": [{
                "prefix": "h",
                "namespace": "urn:iso:std:iso:20022:tech:xsd:head.001.001.01"
            }, {
                "prefix": "u",
                "element": "Document"
            }, {
                "prefix": "d", 
                "namespace": "urn:xxx"
            }
        ],
        "rootElement": "/d:PDU",
        "parts": [{
                "order": "1",
                "comment": "Header transformation",
                "constant": {
                    "namespace": "urn:xxx",
                    "elementName": "constantElt",
                    "variableName": "testVar"
                },
                "variables": {
                    "testVar":"/body/h:AppHdr/h:headerElement"             
            },
                "targetPath": "/d:PDU/d:Body/d:constantElt"
            }
        ]
    }', true);

        $xml = '<body><AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><headerElement>AAA</headerElement></AppHdr><Document xmlns="urn:testns"><documentElement>BBB</documentElement></Document></body>';
        $recurse = new HorusRecurse('AAA', null);

        $result = $recurse->doRecurseXml($xml, $config);

        var_dump($result);
    }

    function testReal():void {

        $input = '<DataPDU xmlns="urn:swift:saa:xsd:saa.2.0"><Revision>2.0.2</Revision><Header><Message><SenderReference>AX20190780000010</SenderReference><MessageIdentifier>camt.005.001.03</MessageIdentifier><Format>AnyXML</Format><Sender><DN>ou=bpce,ou=target2,o=bpcefrpp,o=swift</DN></Sender><Receiver><DN>ou=iwsiapilot,ou=euro1,ou=swiftnet,o=swhqbebb,o=swift</DN></Receiver><InterfaceInfo><UserReference>AX20190780000010</UserReference><ValidationLevel>Minimum</ValidationLevel><MessageNature>Financial</MessageNature><ProductInfo><Product><VendorName>Diamis</VendorName><ProductName>CT2C</ProductName><ProductVersion>2.0</ProductVersion></Product></ProductInfo></InterfaceInfo><NetworkInfo><Service>swift.euro1.iws!p</Service></NetworkInfo><SecurityInfo><SWIFTNetSecurityInfo><IsNRRequested>true</IsNRRequested></SWIFTNetSecurityInfo></SecurityInfo></Message></Header><Body><AppHdr xmlns="urn:swift:xsd:iws.ApplicationHeader$ahV10"><From><Type>XML</Type><Id>BPCEFRPPXXX</Id></From><MsgRef>AX20190780000010</MsgRef><CrDate>2019-03-19T07:15:44</CrDate></AppHdr><Document xmlns="urn:swift:xsd:iws.GetTransaction$camt.005.001.03"><camt.005.001.03><MsgId><Id>AX20190780000010</Id></MsgId><TxQryDef><TxCrit><NewCrit><SchCrit><PmtTo><SysId>ERP</SysId></PmtTo><PmtFr><SysId>ERP</SysId></PmtFr><PmtSch><InstrSts><PmtInstrSts><PdgSts>STLE</PdgSts></PmtInstrSts><PmtInstrStsDtTm><DtTmRg><FrDtTm>2019-03-18T12:12:06</FrDtTm><ToDtTm>2019-03-19T07:15:44</ToDtTm></DtTmRg></PmtInstrStsDtTm></InstrSts><IntrBkValDt>2019-03-19</IntrBkValDt></PmtSch></SchCrit></NewCrit></TxCrit></TxQryDef></camt.005.001.03></Document></Body></DataPDU>';

        $config = json_decode('{
            "section": "section1",
            "content-type": "application/xml",
            "comment": "Main PACS structure",
            "schema": "cristal.xsd",
            "namespaces": [{
                    "prefix": "h",
                    "namespace": "urn:iso:std:iso:20022:tech:xsd:head.001.001.01"
                }, {
                    "prefix": "u",
                    "element": "Document"
                }, {
                    "prefix": "saa", 
                    "namespace": "urn:swift:saa:xsd:saa.2.0"
                }
            ],
            "rootElement": "/saa:DataPDU",
            "parts": [{
                    "order": "1",
                    "comment": "Header transformation",
                    "path": "/saa:DataPDU/saa:Header",
                    "transformUrl": "http://localhost",
                    "targetPath": "/saa:DataPDU/saa:Header"
                }
            ]
        }', true);

        self::$curlCounter = 0;
        self::$curls[] = array(
            'url' => 'http://localhost',
            'options' => array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => array('Content-Type: application/xml', 'Accept: application/xml', 'Expect:', 'X-Business-Id: PPPP'),
                CURLOPT_SSL_VERIFYPEER => False,
                CURLOPT_VERBOSE => True,
                CURLOPT_HEADER => True,
                CURLINFO_HEADER_OUT => True
            ),
            'data' => "HTTP/1.1 200 OK\nDate: Sun, 20 Oct 2019 11:22:04 GMT\nExpires: -1\nCache-Control: private, max-age=0\n" .
                "Content-Type: application/xml; charset=ISO-8859-1\nAccept-Ranges: none\nVary: Accept-Encoding\nTransfer-Encoding: chunked\n" .
                "\n" .
                '<?xml version="1.0"?>' . "\n" . '<Header xmlns="urn:swift:saa:xsd:saa.2.0"><Message><SenderReference>AX20190780000010</SenderReference><MessageIdentifier>camt.005.001.03</MessageIdentifier><Format>AnyXML</Format><Sender><DN>ou=bpce,ou=target2,o=bpcefrpp,o=swift</DN></Sender><Receiver><DN>ou=iwsiapilot,ou=euro1,ou=swiftnet,o=swhqbebb,o=swift</DN></Receiver><InterfaceInfo><UserReference>AX20190780000010</UserReference><ValidationLevel>Minimum</ValidationLevel><MessageNature>Financial</MessageNature><ProductInfo><Product><VendorName>Diamis</VendorName><ProductName>CT2C</ProductName><ProductVersion>2.0</ProductVersion></Product></ProductInfo></InterfaceInfo><NetworkInfo><Service>swift.euro1.iws!p</Service></NetworkInfo><SecurityInfo><SWIFTNetSecurityInfo><IsNRRequested>true</IsNRRequested></SWIFTNetSecurityInfo></SecurityInfo></Message></Header>' . "\n",
            'returnHeaders' => array(
                CURLINFO_HTTP_CODE => 200,
                CURLINFO_HEADER_SIZE => 218
            ),
            'returnCode' => 200,
            'errorMessage' => '',
            'returnBody' => '<Header xmlns="urn:swift:saa:xsd:saa.2.0"><Message><SenderReference>AX20190780000010</SenderReference><MessageIdentifier>camt.005.001.03</MessageIdentifier><Format>AnyXML</Format><Sender><DN>ou=bpce,ou=target2,o=bpcefrpp,o=swift</DN></Sender><Receiver><DN>ou=iwsiapilot,ou=euro1,ou=swiftnet,o=swhqbebb,o=swift</DN></Receiver><InterfaceInfo><UserReference>AX20190780000010</UserReference><ValidationLevel>Minimum</ValidationLevel><MessageNature>Financial</MessageNature><ProductInfo><Product><VendorName>Diamis</VendorName><ProductName>CT2C</ProductName><ProductVersion>2.0</ProductVersion></Product></ProductInfo></InterfaceInfo><NetworkInfo><Service>swift.euro1.iws!p</Service></NetworkInfo><SecurityInfo><SWIFTNetSecurityInfo><IsNRRequested>true</IsNRRequested></SWIFTNetSecurityInfo></SecurityInfo></Message></Header>'
        );

        $recurse = new HorusRecurse('FFF', null);
        $result = $recurse->doRecurseXml($input, $config);

        var_dump($result);
    }
}
