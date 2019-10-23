<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_xml.php');
require_once('HorusTestCase.php');
require_once('lib/horus_exception.php');

class HorusXmlTest extends HorusTestCase
{

    function testNotXmlInput(): void
    {
        $xmlinject = new HorusXml('1234', null);
        $input = 'not xml!';
        try {
            //$this->expectException(HorusException::class);
            $res = $xmlinject->doInject($input, 'application/xml', '', array(), 'application/xml', array(), 'templates/genericError.xml');
        } catch (HorusException $e) {
            $xml = simplexml_load_string($e->getMessage());

            $this::assertNotFalse($xml, 'Output should be XML');
            $namespaces = $xml->getDocNamespaces();
            $this::assertEquals($namespaces, array('' => 'urn:iso:std:iso:20022:tech:xsd:DRAFT2admi.007.001.01'), 'Return should be in the admin namespace');
        }
    }

    function testNoResultFound(): void
    {
        $xmlinject = new HorusXml('1234', null);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:DRAFT2admi.007.001.01"><RctAck><MsgId><MsgId>342678185325910</MsgId></MsgId><Rpt><RltdRef><Ref>UNKNOWN</Ref></RltdRef><ReqHdlg><StsCd>T098</StsCd><Desc>Input XML not properly formatted.</Desc></ReqHdlg></Rpt></RctAck></Document>';
        $matches = json_decode('[{
            "query": "pacs.008.001.02.xsd",
            "comment": "Cas pour acquisition fixe pacs.008 : pacs.008",
            "responseFormat": "pacs.008.001.02.xsd",
            "responseTemplate": "pacs.008_1.xml",
            "errorTemplate": "errorTemplate.xml"
          },
          {
            "query": "pacs.008.001.02.xsd",
            "comment": "Cas pour reception fixe pacs.008",
            "responseFormat": "pacs.008.001.02.xsd",
            "responseTemplate": "Rpacs008IP-01-RT1.xml",
            "errorTemplate": "errorTemplate.xml"
          }]', true);

        try {
            //$this->expectException(HorusException::class);
            $res = $xmlinject->doInject($input, 'application/xml', '', $matches, 'application/xml', array(), 'templates/genericError.xml');
        } catch (HorusException $e) {

            $xml = simplexml_load_string($e->getMessage());

            $this::assertNotFalse($xml, 'Output should be XML');
            $namespaces = $xml->getDocNamespaces();
            $this::assertEquals($namespaces, array('' => 'urn:iso:std:iso:20022:tech:xsd:DRAFT2admi.007.001.01'), 'Return should be in the admin namespace');
            $xml->registerXPathNamespace('a', 'urn:iso:std:iso:20022:tech:xsd:DRAFT2admi.007.001.01');
            $node = $xml->xpath('/a:Document/a:RctAck/a:Rpt/a:ReqHdlg/a:Desc');

            $this::assertEquals((string) $node[0], "Unable to find appropriate response.\n", 'Should return Unable to find appropriate response');
        }
    }

    function testFindSchemaFound(): void
    {
        $xmlinject = new HorusXml('XXXX', null);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $xsd = $xmlinject->findSchema(simplexml_load_string($input));
        $this::assertEquals($xsd, 'pacs.002.001.03.xsd');
    }

    function testFindSchemaNotFound(): void
    {
        $xmlinject = new HorusXml('XXXX', null);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.XXX.YYY.ZZ"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $xsd = $xmlinject->findSchema(simplexml_load_string($input));
        $this::assertEquals($xsd, '');
    }

    function testFindSchemaNotValidated(): void
    {
        $xmlinject = new HorusXml('XXXX', null);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03IPS"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $xsd = $xmlinject->findSchema(simplexml_load_string($input));
        $this::assertEquals($xsd, '');
    }

    function testGetVariables(): void
    {
        $xmlinject = new HorusXml('ZZZZ', null);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $matches = '[{
            "query": "pacs.002.001.03.xsd",
            "comment": "Cas pour acquisition fixe pacs.008 : pacs.008",
            "responseFormat": "pacs.008.001.02.xsd",
            "responseTemplate": "pacs.008_1.xml",
            "errorTemplate": "errorTemplate.xml",
            "parameters" : {    "msgid":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId", 
                                "bic":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:InstgAgt/u:FinInstnId/u:BIC", 
                                "test":"/u:Document/u:FIToFIPmtStsRpt/u:XXX",
                                "fragment":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:InstgAgt"}
          }]';
        $xml = simplexml_load_string($input);
        $namespaces = $xml->getDocNamespaces();
        $xml->registerXPathNamespace('u', 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03');
        $vars = $xmlinject->getVariables($xml, json_decode($matches, true), 0);
        $expectedvars = array('msgid' => '1234567890', 'bic' => 'BNPAFRPPXXX', 'test' => '', 'fragment' => '<InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt>');
        $this::assertEquals($expectedvars, $vars);
    }

    function testGetResponseError(): void
    {
        $xmlinject = new HorusXml('TTTT', null);
        $vars = array('id' => 'id12345', 'txid' => 'txid12345', 'endtoendid' => 'endtoendid', 'txdt' => '2019-10-17T22:00:00.000Z', 'frombic' => 'BNPAFRPPXXX', 'tobic' => 'BNPAFRPPXXX21111111111111111111111111');
        $templates = array('pacs.002_ACCP.xml', 'pacs.002_RJCT.xml');
        $formats = array('pacs.002.001.03.xsd', 'pacs.002.001.03.xsd');
        $preferredType = 'application/xml';
        $errorTemplate = 'templates/genericError.xml';
        $proxy_mode = '';

        $this->expectException(HorusException::class);

        $xmlinject->getResponses($templates, $vars, $formats, $preferredType, $errorTemplate, $proxy_mode);
    }

    function testGetResponses(): void
    {
        $xmlinject = new HorusXml('TTTT', null);
        $vars = array('id' => 'id12345', 'txid' => 'txid12345', 'endtoendid' => 'endtoendid', 'txdt' => '2019-10-17T22:00:00.000Z', 'frombic' => 'BNPAFRPPXXX', 'tobic' => 'BNPAFRPPXXX');
        $templates = array('pacs.002_ACCP.xml', 'pacs.002_RJCT.xml');
        $formats = array('pacs.002.001.03.xsd', 'pacs.002.001.03.xsd');
        $preferredType = 'application/xml';
        $errorTemplate = 'templates/genericError.xml';
        $proxy_mode = '';

        $r = $xmlinject->getResponses($templates, $vars, $formats, $preferredType, $errorTemplate, $proxy_mode);

        $this::assertEquals(2, count($r), 'Response array should have 2 elements');
        $r1 = simplexml_load_string($r[0]);
        $this::assertEquals($r1->getDocNamespaces(), array('' => 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03'), 'First response should be pacs2');
        $r2 = simplexml_load_string($r[1]);
        $this::assertEquals($r2->getDocNamespaces(), array('' => 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03'), 'Second response should be pacs2');
        $r1->registerXPathNamespace('a', 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03');
        $node = $r1->xpath('/a:Document/a:FIToFIPmtStsRpt/a:GrpHdr/a:InstgAgt/a:FinInstnId/a:BIC');
        $this::assertEquals(((string) $node[0]), 'BNPAFRPP', 'Valid data in first response');
    }


    function testOutQuery(): void
    {
        $xmlinject = new HorusXml('UUUU', null);

        $forwardparams = json_decode('[{"key":"onekey","value":"onevalue"},{"key":"two keys","value":"two values"}]', true);
        $url1 = 'http://localhost/?a=b';
        $url2 = 'http://localhost/';

        $this::assertEquals($xmlinject->formOutQuery(null, ''), '', 'Shouldn\'t throw any error');
        $this::assertEquals($xmlinject->formOutQuery($forwardparams, ''), '', 'Should return empty without URL');
        $this::assertEquals($xmlinject->formOutQuery(array(), ''), '', 'Shouldn\'t throw error either');
        $this::assertEquals($xmlinject->formOutQuery(null, $url1), $url1, 'Should return original URL');
        $this::assertEquals($xmlinject->formOutQuery(array(), $url1), $url1, 'Should return original URL as well');
        $this::assertEquals($xmlinject->formOutQuery(array($forwardparams), $url1), $url1 . '&onekey=onevalue&two+keys=two+values', 'Should return parameters urlencoded');
        $this::assertEquals($xmlinject->formOutQuery(array($forwardparams), $url2), $url2 . '?onekey=onevalue&two+keys=two+values', 'Should return query string');
    }


    function testXmlInjectNoProxySingle(): void
    {
        $xmlinject = new HorusXml('PPPP', null);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $mat = '[{
            "query": "pacs.002.001.03.xsd",
            "comment": "Cas pour acquisition fixe pacs.002 : pacs.002",
            "responseFormat": "pacs.002.001.03.xsd",
            "responseTemplate": "pacs.002_ACCP.xml",
            "errorTemplate": "errorTemplate.xml",
            "parameters" : {    "id":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId", 
                                "txid":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId", 
                                "endtoendid":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId", 
                                "frombic":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:InstgAgt/u:FinInstnId/u:BIC", 
                                "tobic":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:InstgAgt/u:FinInstnId/u:BIC", 
                                "txdt":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:CreDtTm",
                                "fragment":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:InstgAgt"},
            "destParameters": [{"key":"forwardkey1","value":"forwardvalue1"}, 
                               {"key":"forwardkey2","value":"forwardvalue2"}]
          }]';

        $matches = json_decode($mat, true);
        $queryParams = array('querykey1' => 'queryvalue1', 'querykey2' => 'queryvalue2');

        $res = $xmlinject->doInject($input, 'application/xml', '', $matches, 'application/xml', $queryParams, 'templates/genericError.xml');

        $this::assertNotNull($res, 'Response is not empty');
        $this::assertEquals(count($res), 1, 'Should get only 1 response');
        $xml = simplexml_load_string($res[0]);
        $this::assertEquals($xml->getDocNamespaces(), array('' => 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03'), 'Document is from expected namespace');
        $xml->registerXPathNamespace('u', 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03');
        $this::assertEquals($xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), $xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), 'These 2 elements should have the same value');
        $this::assertEquals($this::$mockheaders[0][0], 'HTTP/1.1 200 OK', 'We should return HTTP/200');
        $this::assertEquals($this::$mockheaders[1][0], 'Content-type: application/xml', 'We should return xml content-type');
    }

    function testXmlInjectProxySingle(): void
    {
        $xmlinject = new HorusXml('PPPP', null);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $matches = '[{
            "query": "pacs.002.001.03.xsd",
            "comment": "Cas pour acquisition fixe pacs.002 : pacs.002",
            "responseFormat": "pacs.002.001.03.xsd",
            "responseTemplate": "pacs.002_ACCP.xml",
            "errorTemplate": "errorTemplate.xml",
            "parameters" : {    "id":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId", 
                                "txid":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId", 
                                "endtoendid":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId", 
                                "frombic":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:InstgAgt/u:FinInstnId/u:BIC", 
                                "tobic":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:InstgAgt/u:FinInstnId/u:BIC", 
                                "txdt":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:CreDtTm",
                                "fragment":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:InstgAgt"},
            "destParameters": [{"key":"forwardkey1","value":"forwardvalue1"}, 
                               {"key":"forwardkey2","value":"forwardvalue2"}]
          }]';
        $queryParams = array('forwardkey1' => 'forwardvalue1', 'forwardkey2' => 'forwardvalue2');

        self::$curls[] = array(
            'url' => 'http://localhost',
            'options' => array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => array('Content-type: application/xml', 'Accept: application/xml', 'Expect:', 'X-Business-Id: PPPP'),
                CURLOPT_SSL_VERIFYPEER => False,
                CURLOPT_VERBOSE => True,
                CURLOPT_HEADER => True,
                CURLINFO_HEADER_OUT => True
            ),
            'data' => "HTTP/1.1 200 OK\nDate: Sun, 20 Oct 2019 11:22:04 GMT\nExpires: -1\nCache-Control: private, max-age=0\n" .
                "Content-type: application/xml; charset=ISO-8859-1\nAccept-Ranges: none\nVary: Accept-Encoding\nTransfer-Encoding: chunked\n" .
                "\n" .
                '<?xml version="1.0"?>' . "\n" . '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>RE34567890</MsgId><CreDtTm>2019-10-20T11:56:36</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPP</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPP</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>' . "\n",
            'returnHeaders' => array(
                CURLINFO_HTTP_CODE => 200,
                CURLINFO_HEADER_SIZE => 218
            ),
            'returnCode' => 200,
            'errorMessage' => '',
            'returnBody' => '<html>Test</html>'
        );


        $res = $xmlinject->doInject($input, 'application/xml', 'http://localhost', json_decode($matches, true), 'application/xml', $queryParams, 'templates/genericError.xml');

        $this::assertNotNull($res, 'Response is not empty');
        $this::assertEquals(count($res), 1, 'Should get only 1 response');
        $this::assertEquals(self::$curls[0]['url'], 'http://localhost?forwardkey1=forwardvalue1&forwardkey2=forwardvalue2');
        $xml = simplexml_load_string($res);

        $this::assertEquals($xml->getDocNamespaces(), array('' => 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03'), 'Document is from expected namespace');
        $xml->registerXPathNamespace('u', 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03');
        $this::assertEquals($xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), $xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), 'These 2 elements should have the same value');
        $this::assertEquals($this::$mockheaders[0][0], 'HTTP/1.1 200 OK', 'We should return HTTP/200');
        $this::assertEquals(explode(';', $this::$mockheaders[1][0])[0], 'Content-type: application/xml', 'We should return xml content-type');
    }

    function testXmlInjectProxyMult(): void
    {
        $xmlinject = new HorusXml('QQQQ', null);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $mat = '[{
            "query": "pacs.002.001.03.xsd",
            "comment": "Cas pour acquisition fixe pacs.002 : pacs.002",
            "responseFormat": ["pacs.002.001.03.xsd","pacs.002.001.03.xsd"],
            "responseTemplate": ["pacs.002_ACCP.xml","pacs.002_RJCT.xml"],
            "errorTemplate": "errorTemplate.xml",
            "parameters" : {    "id":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId", 
                                "txid":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId", 
                                "endtoendid":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId", 
                                "frombic":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:InstgAgt/u:FinInstnId/u:BIC", 
                                "tobic":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:InstgAgt/u:FinInstnId/u:BIC", 
                                "txdt":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:CreDtTm",
                                "fragment":"/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:InstgAgt"},
            "destParameters": [{"key":"forwardkey1","value":"forwardvalue1"}, 
                               {"key":"forwardkey2","value":"forwardvalue2"}]
          }]';
        $queryParams = array('forwardkey1' => 'forwardvalue1', 'forwardkey2' => 'forwardvalue2');

        self::$curls[] = array(
            'url' => 'http://localhost',
            'options' => array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => array('Content-type: application/xml', 'Accept: application/xml', 'Expect:', 'X-Business-Id: PPPP'),
                CURLOPT_SSL_VERIFYPEER => False,
                CURLOPT_VERBOSE => True,
                CURLOPT_HEADER => True,
                CURLINFO_HEADER_OUT => True
            ),
            'data' => "HTTP/1.1 200 OK\nDate: Sun, 20 Oct 2019 11:22:04 GMT\nExpires: -1\nCache-Control: private, max-age=0\n" .
                "Content-type: application/xml; charset=ISO-8859-1\nAccept-Ranges: none\nVary: Accept-Encoding\nTransfer-Encoding: chunked\n" .
                "\n" .
                "--b75a6e9befe222c24c10d5c9fa3007ee\r\nContent-Disposition: form-data; name=\"response_0\"; filename=\"response_0\"\r\nContent-Type: application/xml\r\nContent-Transfer-Encoding: base64\r\n\r\nPD94bWwgdmVyc2lvbj0iMS4wIj8+CjxEb2N1bWVudCB4bWxucz0idXJuOmlzbzpzdGQ6aXNvOjIw\r\nMDIyOnRlY2g6eHNkOnBhY3MuMDAyLjAwMS4wMyI+PEZJVG9GSVBtdFN0c1JwdD48R3JwSGRyPjxN\r\nc2dJZD5SRTM0NTY3ODkwPC9Nc2dJZD48Q3JlRHRUbT4yMDE5LTEwLTIwVDE0OjIyOjEyPC9DcmVE\r\ndFRtPjxJbnN0Z0FndD48RmluSW5zdG5JZD48QklDPkJOUEFGUlBQPC9CSUM+PC9GaW5JbnN0bklk\r\nPjwvSW5zdGdBZ3Q+PEluc3RkQWd0PjxGaW5JbnN0bklkPjxCSUM+Qk5QQUZSUFA8L0JJQz48L0Zp\r\nbkluc3RuSWQ+PC9JbnN0ZEFndD48L0dycEhkcj48T3JnbmxHcnBJbmZBbmRTdHM+PE9yZ25sTXNn\r\nSWQ+MTIzNDU2Nzg5MDwvT3JnbmxNc2dJZD48T3JnbmxNc2dObUlkPnBhY3MuMDA4PC9PcmdubE1z\r\nZ05tSWQ+PEdycFN0cz5BQ0NQPC9HcnBTdHM+PC9PcmdubEdycEluZkFuZFN0cz48VHhJbmZBbmRT\r\ndHM+PFN0c0lkPjEyMzQ1Njc4OTA8L1N0c0lkPjxPcmdubEVuZFRvRW5kSWQ+MTIzNDU2Nzg5MDwv\r\nT3JnbmxFbmRUb0VuZElkPjxPcmdubFR4SWQ+MTIzNDU2Nzg5MDwvT3JnbmxUeElkPjxBY2NwdG5j\r\nRHRUbT4yMDEyLTEyLTEzVDEyOjEyOjEyLjAwMFo8L0FjY3B0bmNEdFRtPjxPcmdubFR4UmVmPjxQ\r\nbXRUcEluZj48U3ZjTHZsPjxDZD5TRVBBPC9DZD48L1N2Y0x2bD48TGNsSW5zdHJtPjxDZD5JTlNU\r\nPC9DZD48L0xjbEluc3RybT48Q3RneVB1cnA+PENkPlBVUlA8L0NkPjwvQ3RneVB1cnA+PC9QbXRU\r\ncEluZj48RGJ0ckFndD48RmluSW5zdG5JZD48QklDPkJOUEFGUlBQWFhYPC9CSUM+PC9GaW5JbnN0\r\nbklkPjwvRGJ0ckFndD48L09yZ25sVHhSZWY+PC9UeEluZkFuZFN0cz48L0ZJVG9GSVBtdFN0c1Jw\r\ndD48L0RvY3VtZW50Pgo=\r\n\r\n--b75a6e9befe222c24c10d5c9fa3007ee\r\nContent-Disposition: form-data; name=\"response_1\"; filename=\"response_1\"\r\nContent-Type: application/xml\r\nContent-Transfer-Encoding: base64\r\n\r\nPD94bWwgdmVyc2lvbj0iMS4wIj8+CjxEb2N1bWVudCB4bWxucz0idXJuOmlzbzpzdGQ6aXNvOjIw\r\nMDIyOnRlY2g6eHNkOnBhY3MuMDAyLjAwMS4wMyI+PEZJVG9GSVBtdFN0c1JwdD48R3JwSGRyPjxN\r\nc2dJZD5SRTM0NTY3ODkwPC9Nc2dJZD48Q3JlRHRUbT4yMDE5LTEwLTIwVDE0OjIyOjEyPC9DcmVE\r\ndFRtPjxJbnN0Z0FndD48RmluSW5zdG5JZD48QklDPkJOUEFGUlBQPC9CSUM+PC9GaW5JbnN0bklk\r\nPjwvSW5zdGdBZ3Q+PEluc3RkQWd0PjxGaW5JbnN0bklkPjxCSUM+Qk5QQUZSUFBYWFg8L0JJQz48\r\nL0Zpbkluc3RuSWQ+PC9JbnN0ZEFndD48L0dycEhkcj48T3JnbmxHcnBJbmZBbmRTdHM+PE9yZ25s\r\nTXNnSWQ+MTIzNDU2Nzg5MDwvT3JnbmxNc2dJZD48T3JnbmxNc2dObUlkPnBhY3MuMDA4PC9Pcmdu\r\nbE1zZ05tSWQ+PEdycFN0cz5SSkNUPC9HcnBTdHM+PFN0c1JzbkluZj48T3JndHI+PE5tPlJUMTwv\r\nTm0+PC9Pcmd0cj48UnNuPjxDZD5GRjAxPC9DZD48L1Jzbj48L1N0c1JzbkluZj48L09yZ25sR3Jw\r\nSW5mQW5kU3RzPjxUeEluZkFuZFN0cz48U3RzSWQ+MTIzNDU2Nzg5MDwvU3RzSWQ+PE9yZ25sRW5k\r\nVG9FbmRJZD4xMjM0NTY3ODkwPC9PcmdubEVuZFRvRW5kSWQ+PE9yZ25sVHhJZD4xMjM0NTY3ODkw\r\nPC9PcmdubFR4SWQ+PEFjY3B0bmNEdFRtPjIwMTItMTItMTNUMTI6MTI6MTIuMDAwWjwvQWNjcHRu\r\nY0R0VG0+PE9yZ25sVHhSZWY+PFBtdFRwSW5mPjxTdmNMdmw+PENkPlNFUEE8L0NkPjwvU3ZjTHZs\r\nPjxMY2xJbnN0cm0+PENkPklOU1Q8L0NkPjwvTGNsSW5zdHJtPjxDdGd5UHVycD48Q2Q+UFVSUDwv\r\nQ2Q+PC9DdGd5UHVycD48L1BtdFRwSW5mPjxEYnRyQWd0PjxGaW5JbnN0bklkPjxCSUM+Qk5QQUZS\r\nUFBYWFg8L0JJQz48L0Zpbkluc3RuSWQ+PC9EYnRyQWd0PjwvT3JnbmxUeFJlZj48L1R4SW5mQW5k\r\nU3RzPjwvRklUb0ZJUG10U3RzUnB0PjwvRG9jdW1lbnQ+Cg==\r\n\r\n--b75a6e9befe222c24c10d5c9fa3007ee--\r\n\r\n",
            'returnHeaders' => array(
                CURLINFO_HTTP_CODE => 200,
                CURLINFO_HEADER_SIZE => 218
            ),
            'returnCode' => 200,
            'errorMessage' => '',
            'returnBody' => '<html>Test</html>'
        );

        $matches = json_decode($mat, true);

        $res = $xmlinject->doInject($input, 'application/xml', 'http://localhost', $matches, 'application/xml', $queryParams, 'templates/genericError.xml');

        $this::assertNotNull($res, 'Response is not empty');
        $this::assertEquals(count($res), 1, 'Should get only 1 response');
        $this::assertEquals(self::$curls[0]['url'], 'http://localhost?forwardkey1=forwardvalue1&forwardkey2=forwardvalue2');

        preg_match('/--(.*)\r\n/', $res, $mm);
        $this::assertNotNull($mm[0], "Should find a multipath boundary");
        $results = explode($mm[0], $res);
        $this::assertEquals(count($results), 3, 'Should find 3 multipart boundary markers for 2 files downloaded');
        $first = explode("\r\n", $results[1]);
        $b64 = '';
        for ($i = 4; $i < count($first) - 2; $i++)
            $b64 .= $first[$i];
        $b64 = base64_decode($b64);

        $xml = simplexml_load_string($b64);

        $this::assertEquals($xml->getDocNamespaces(), array('' => 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03'), 'Document is from expected namespace');
        $xml->registerXPathNamespace('u', 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03');
        $this::assertEquals($xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), $xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), 'These 2 elements should have the same value');
        $this::assertEquals($this::$mockheaders[0][0], 'HTTP/1.1 200 OK', 'We should return HTTP/200');
        $this::assertEquals(explode(';', $this::$mockheaders[1][0])[0], 'Content-type: application/xml', 'We should return xml content-type');
    }
}
