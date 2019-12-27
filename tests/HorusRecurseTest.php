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

    function testRecurseXml():void {

        $config = json_decode('{"section": "section1",
            "content-type":"application/xml",
            "comment":"Main PACS structure",
            "schema":"cristal.xsd",
            "baseNamespace": "",
            "baseNamespacePrefix": "u",
            "rootElement": "body",
            "parts":[
                {"order":"1",
                 "comment":"Header transformation",
                 "path":"/body/h:AppHdr",
                 "namespaces":[
                     {"prefix":"h","namespace":"urn:iso:std:iso:20022:tech:xsd:head.001.001.01"}
                 ],
                 "transformUrl":"http://horus/horus.php",
                 "targetPath":"/body/h:AppHdr"
                },{
                    "order":"2",
                    "comment":"PACS Document transformation",
                    "namespaces":[
                       {"prefix":"d","element":"Document"}
                    ],
                    "path":"/body/d:Document",
                    "transformUrl":"http://horus/horus.php",
                    "targetElement":"Document",
                    "targetElementOrder":"2"
                }
            ]}',true);
        
        $xml = simplexml_load_string('<body><AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><headerElement>AAA</headerElement></AppHdr><Document xmlns="urn:testns"><documentElement>BBB</documentElement></Document></body>');
        $recurse = new HorusRecurse('AAA',null);
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

        $result = $recurse->doRecurseXml($xml,$config);

        $this::assertEquals('<?xml version="1.0"?>' . "\n" . '<body><AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><headerReturnElement>ZZZ</headerReturnElement></AppHdr><Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>RE34567890</MsgId><CreDtTm>2019-10-20T11:56:36</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPP</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPP</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document></body>' . "\n",$result);
       
    }


}