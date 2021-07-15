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
        $xmlinject = new HorusXml('1234', null,'GREEN',self::$tracer);
        $input = 'not xml!';
        try {   
            $res = $xmlinject->doInject($input, 'application/xml', '', array(), 'application/xml', array(), 'templates/genericError.xml','',self::$rootSpan);
        } catch (HorusException $e) {
            $xml = simplexml_load_string($e->getMessage());

            $this::assertNotFalse($xml, 'Output should be XML');
            $namespaces = $xml->getDocNamespaces();
            $this::assertEquals($namespaces, array('' => 'urn:iso:std:iso:20022:tech:xsd:DRAFT2admi.007.001.01'), 'Return should be in the admin namespace');
        }
    }

    function testNoResultFound(): void
    {
        $xmlinject = new HorusXml('1234', null,'GREEN',self::$tracer);
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
            $res = $xmlinject->doInject($input, 'application/xml', '', $matches, 'application/xml', array(), 'templates/genericError.xml','',self::$rootSpan);
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
        $xmlinject = new HorusXml('XXXX', null,'GREEN',self::$tracer);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $xsd = $xmlinject->findSchema(simplexml_load_string($input));
        $this::assertEquals($xsd, 'pacs.002.001.03.xsd');
    }

    function testFindSchemaNotFound(): void
    {
        $xmlinject = new HorusXml('XXXX', null,'GREEN',self::$tracer);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.XXX.YYY.ZZ"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $xsd = $xmlinject->findSchema(simplexml_load_string($input));
        $this::assertEquals($xsd, '');
    }

    function testFindSchemaNotValidated(): void
    {
        $xmlinject = new HorusXml('XXXX', null,'GREEN',self::$tracer);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03IPS"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $xsd = $xmlinject->findSchema(simplexml_load_string($input));
        $this::assertEquals($xsd, '');
    }

    function testGetVariables(): void
    {
        $xmlinject = new HorusXml('ZZZZ', null,'GREEN',self::$tracer);
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
        $xmlinject = new HorusXml('TTTT', null,'GREEN',self::$tracer);
        $vars = array('id' => 'id12345', 'txid' => 'txid12345', 'endtoendid' => 'endtoendid', 'txdt' => '2019-10-17T22:00:00.000Z', 'frombic' => 'BNPAFRPPXXX', 'tobic' => 'BNPAFRPPXXX21111111111111111111111111');
        $templates = array('pacs.002_ACCP.xml', 'pacs.002_RJCT.xml');
        $formats = array('pacs.002.001.03.xsd', 'pacs.002.001.03.xsd');
        $preferredType = 'application/xml';
        $errorTemplate = 'templates/genericError.xml';
        $proxy_mode = '';

        $this->expectException(HorusException::class);

        $xmlinject->getResponses($templates, $vars, $formats, $preferredType, $errorTemplate, self::$rootSpan);
    }

    function testGetResponses(): void
    {
        $xmlinject = new HorusXml('TTTT', null,'GREEN',self::$tracer);
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
        $xmlinject = new HorusXml('UUUU', null,'GREEN',self::$tracer);

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
        $xmlinject = new HorusXml('PPPP', null,'GREEN',self::$tracer);
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
    
        $res = $xmlinject->doInject($input, 'application/xml', '', $matches, 'application/xml', $queryParams, 'templates/genericError.xml','',self::$rootSpan);

        $this::assertNotNull($res, 'Response is not empty');
        $this::assertEquals(count($res), 1, 'Should get only 1 response');
        $xml = simplexml_load_string($res[0]);
        $this::assertEquals($xml->getDocNamespaces(), array('' => 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03'), 'Document is from expected namespace');
        $xml->registerXPathNamespace('u', 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03');
        $this::assertEquals($xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), $xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), 'These 2 elements should have the same value');
        $this::assertEquals($this::$mockheaders[0][0], 'HTTP/1.1 200 OK', 'We should return HTTP/200');
        $this::assertEquals($this::$mockheaders[1][0], 'Content-Type: application/xml', 'We should return xml content-type');
    }

    function testXmlInjectProxySingle(): void
    {
        $xmlinject = new HorusXml('PPPP', null,'GREEN',self::$tracer);
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
            'returnBody' => '<html>Test</html>'
        );


        $res = $xmlinject->doInject($input, 'application/xml', 'http://localhost', json_decode($matches, true), 'application/xml', $queryParams, 'templates/genericError.xml','',self::$rootSpan);
        $this::assertNotNull($res, 'Response is not empty');
        //$this::assertEquals(count($res), 1, 'Should get only 1 response');
        $this::assertEquals(self::$curls[0]['url'], 'http://localhost?forwardkey1=forwardvalue1&forwardkey2=forwardvalue2&id=1234567890&txid=1234567890&endtoendid=1234567890&frombic=BNPAFRPPXXX&tobic=BNPAFRPPXXX&txdt=2012-12-13T12%3A12%3A12.000Z&fragment=%3CInstgAgt%3E%3CFinInstnId%3E%3CBIC%3EBNPAFRPPXXX%3C%2FBIC%3E%3C%2FFinInstnId%3E%3C%2FInstgAgt%3E&forwardkey1=forwardvalue1&forwardkey2=forwardvalue2');
        $xml = simplexml_load_string($res);

        $this::assertEquals($xml->getDocNamespaces(), array('' => 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03'), 'Document is from expected namespace');
        $xml->registerXPathNamespace('u', 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03');
        $this::assertEquals($xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), $xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), 'These 2 elements should have the same value');
        $this::assertEquals($this::$mockheaders[0][0], 'HTTP/1.1 200 OK', 'We should return HTTP/200');
        $this::assertEquals(explode(';', $this::$mockheaders[1][0])[0], 'Content-type: application/xml', 'We should return xml content-type');
    }

    function testXmlInjectProxyMult(): void
    {
        $xmlinject = new HorusXml('QQQQ', null,'GREEN',self::$tracer);
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
                CURLOPT_HTTPHEADER => array('Content-Type: application/xml', 'Accept: application/xml', 'Expect:', 'X-Business-Id: PPPP'),
                CURLOPT_SSL_VERIFYPEER => False,
                CURLOPT_VERBOSE => True,
                CURLOPT_HEADER => True,
                CURLINFO_HEADER_OUT => True
            ),
            'data' => "HTTP/1.1 200 OK\nDate: Sun, 20 Oct 2019 11:22:04 GMT\nExpires: -1\nCache-Control: private, max-age=0\n" .
                "Content-Type: application/xml; charset=ISO-8859-1\nAccept-Ranges: none\nVary: Accept-Encoding\nTransfer-Encoding: chunked\n" .
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

        $res = $xmlinject->doInject($input, 'application/xml', 'http://localhost', $matches, 'application/xml', $queryParams, 'templates/genericError.xml','',self::$rootSpan);

        $this::assertNotNull($res, 'Response is not empty');
        //$this::assertEquals(count($res), 1, 'Should get only 1 response');
        $this::assertEquals(self::$curls[0]['url'], 'http://localhost?forwardkey1=forwardvalue1&forwardkey2=forwardvalue2&id=1234567890&txid=1234567890&endtoendid=1234567890&frombic=BNPAFRPPXXX&tobic=BNPAFRPPXXX&txdt=2012-12-13T12%3A12%3A12.000Z&fragment=%3CInstgAgt%3E%3CFinInstnId%3E%3CBIC%3EBNPAFRPPXXX%3C%2FBIC%3E%3C%2FFinInstnId%3E%3C%2FInstgAgt%3E&forwardkey1=forwardvalue1&forwardkey2=forwardvalue2');

        preg_match('/--(.*)\r\n/', $res, $mm);
        $this::assertNotNull($mm[0], "Should find a multipath boundary");
        $results = explode($mm[0], $res);
        $this::assertEquals(count($results), 3, 'Should find 3 multipart boundary markers for 2 files downloaded');
        $first = explode("\r\n", $results[1]);
        $b64 = '';
        for ($i = 4; $i < count($first) - 2; $i++){
            $b64 .= $first[$i];
        }
        $b64 = base64_decode($b64);

        $xml = simplexml_load_string($b64);

        $this::assertEquals($xml->getDocNamespaces(), array('' => 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03'), 'Document is from expected namespace');
        $xml->registerXPathNamespace('u', 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03');
        $this::assertEquals($xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), $xml->xpath('/u:Document/u:FIToFIPmtStsRpt/u:GrpHdr/u:MsgId'), 'These 2 elements should have the same value');
        $this::assertEquals($this::$mockheaders[0][0], 'HTTP/1.1 200 OK', 'We should return HTTP/200');
        $this::assertEquals(explode(';', $this::$mockheaders[1][0])[0], 'Content-type: application/xml', 'We should return xml content-type');
    }

    function testSearchNamespace(): void {
        $xmlinject = new HorusXml('WWWW', null,'GREEN',self::$tracer);
        $xmlinject->common->mlog("Testing search headers","INFO");
        $testmsg = '<body><AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><Fr><FIId><FinInstnId><BICFI>SOGEFRP0XXX</BICFI></FinInstnId></FIId></Fr><To><FIId><FinInstnId><BICFI>ZYEXFRP0XXX</BICFI></FinInstnId></FIId></To><BizMsgIdr>AV20190030001321</BizMsgIdr><MsgDefIdr>pacs.008.001.07</MsgDefIdr><CreDt>2019-11-28T12:24:40Z</CreDt></AppHdr><Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.008.001.08" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="urn:iso:std:iso:20022:tech:xsd:pacs.008.001.08.xsd"><FIToFICstmrCdtTrf><GrpHdr><MsgId>NONREF</MsgId><CreDtTm>2019-11-14T09:30:47.0Z</CreDtTm><NbOfTxs>1</NbOfTxs><SttlmInf><SttlmMtd>CLRG</SttlmMtd></SttlmInf></GrpHdr><CdtTrfTxInf><PmtId><InstrId>ChampRef</InstrId><EndToEndId>NOTPROVIDED</EndToEndId><UETR>d747fc5c-59c5-41e9-be4c-d45102fc807d</UETR><ClrSysRef>AV20190030001321</ClrSysRef></PmtId><IntrBkSttlmAmt Ccy="EUR">1900.50</IntrBkSttlmAmt><IntrBkSttlmDt>2019-11-14</IntrBkSttlmDt><SttlmPrty>NORM</SttlmPrty><SttlmTmIndctn><CdtDtTm>2019-11-14T11:03:47.0Z</CdtDtTm></SttlmTmIndctn><InstdAmt Ccy="EUR">15550.00</InstdAmt><ChrgBr>DEBT</ChrgBr><ChrgsInf><Amt Ccy="EUR">2.00</Amt><Agt><FinInstnId><BICFI>CEPAFRPP888</BICFI></FinInstnId></Agt></ChrgsInf><InstgAgt><FinInstnId><BICFI>AGRIFRPXXXX</BICFI></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BICFI>ZYEXFRPXXXX</BICFI></FinInstnId></InstdAgt><IntrmyAgt1><FinInstnId><BICFI>BPCEFRP0XXX</BICFI></FinInstnId></IntrmyAgt1><IntrmyAgt1Acct><Id><Othr><Id>34567890123456789012345678901234</Id></Othr></Id></IntrmyAgt1Acct><Dbtr><Nm>LAMBERT PIERRE FR/35000 RENNES</Nm></Dbtr><DbtrAcct><Id><IBAN>FR7640168000191924101877984</IBAN></Id></DbtrAcct><DbtrAgt><FinInstnId><BICFI>CEPAFRPP888</BICFI></FinInstnId></DbtrAgt><CdtrAgt><FinInstnId><BICFI>CEPAFRPP888</BICFI></FinInstnId></CdtrAgt><CdtrAgtAcct><Id><Othr><Id>rien</Id></Othr></Id></CdtrAgtAcct><Cdtr><Nm>JAN VAN GALEN</Nm></Cdtr><Purp><Cd>TYP</Cd></Purp><RmtInf><Ustrd>12345678901234657890123456789012345123456789012346578901234567890123451234567890123465789012345678901234512345678901234657890123456789012345</Ustrd></RmtInf></CdtTrfTxInf></FIToFICstmrCdtTrf></Document></body>';
        $xml = simplexml_load_string($testmsg);
        $this::assertEquals('urn:iso:std:iso:20022:tech:xsd:head.001.001.01', $xmlinject->searchNameSpace('AppHdr',$xml),'Find NS on first element');
        $this::assertEquals('urn:iso:std:iso:20022:tech:xsd:pacs.008.001.08', $xmlinject->searchNameSpace('Document',$xml), 'Find NS on next element');
        $this::assertEquals('',$xmlinject->searchNameSpace('test',$xml),'Unknown element should return empty ns');
    }

    function testRegisterExtraNamespaces(): void {
        $xmlinject = new HorusXml('WZWZ', null,'GREEN',self::$tracer);
        $testmsg = '<body><AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><Fr><FIId><FinInstnId><BICFI>SOGEFRP0XXX</BICFI></FinInstnId></FIId></Fr><To><FIId><FinInstnId><BICFI>ZYEXFRP0XXX</BICFI></FinInstnId></FIId></To><BizMsgIdr>AV20190030001321</BizMsgIdr><MsgDefIdr>pacs.008.001.07</MsgDefIdr><CreDt>2019-11-28T12:24:40Z</CreDt></AppHdr><Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.008.001.08" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="urn:iso:std:iso:20022:tech:xsd:pacs.008.001.08.xsd"><FIToFICstmrCdtTrf><GrpHdr><MsgId>NONREF</MsgId><CreDtTm>2019-11-14T09:30:47.0Z</CreDtTm><NbOfTxs>1</NbOfTxs><SttlmInf><SttlmMtd>CLRG</SttlmMtd></SttlmInf></GrpHdr><CdtTrfTxInf><PmtId><InstrId>ChampRef</InstrId><EndToEndId>NOTPROVIDED</EndToEndId><UETR>d747fc5c-59c5-41e9-be4c-d45102fc807d</UETR><ClrSysRef>AV20190030001321</ClrSysRef></PmtId><IntrBkSttlmAmt Ccy="EUR">1900.50</IntrBkSttlmAmt><IntrBkSttlmDt>2019-11-14</IntrBkSttlmDt><SttlmPrty>NORM</SttlmPrty><SttlmTmIndctn><CdtDtTm>2019-11-14T11:03:47.0Z</CdtDtTm></SttlmTmIndctn><InstdAmt Ccy="EUR">15550.00</InstdAmt><ChrgBr>DEBT</ChrgBr><ChrgsInf><Amt Ccy="EUR">2.00</Amt><Agt><FinInstnId><BICFI>CEPAFRPP888</BICFI></FinInstnId></Agt></ChrgsInf><InstgAgt><FinInstnId><BICFI>AGRIFRPXXXX</BICFI></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BICFI>ZYEXFRPXXXX</BICFI></FinInstnId></InstdAgt><IntrmyAgt1><FinInstnId><BICFI>BPCEFRP0XXX</BICFI></FinInstnId></IntrmyAgt1><IntrmyAgt1Acct><Id><Othr><Id>34567890123456789012345678901234</Id></Othr></Id></IntrmyAgt1Acct><Dbtr><Nm>LAMBERT PIERRE FR/35000 RENNES</Nm></Dbtr><DbtrAcct><Id><IBAN>FR7640168000191924101877984</IBAN></Id></DbtrAcct><DbtrAgt><FinInstnId><BICFI>CEPAFRPP888</BICFI></FinInstnId></DbtrAgt><CdtrAgt><FinInstnId><BICFI>CEPAFRPP888</BICFI></FinInstnId></CdtrAgt><CdtrAgtAcct><Id><Othr><Id>rien</Id></Othr></Id></CdtrAgtAcct><Cdtr><Nm>JAN VAN GALEN</Nm></Cdtr><Purp><Cd>TYP</Cd></Purp><RmtInf><Ustrd>12345678901234657890123456789012345123456789012346578901234567890123451234567890123465789012345678901234512345678901234657890123456789012345</Ustrd></RmtInf></CdtTrfTxInf></FIToFICstmrCdtTrf></Document></body>';
        $xml = simplexml_load_string($testmsg);
        $matches = json_decode('[
            {
              "query": "isis.xsd",
              "comment": "body wrapper",
              "responseFormat": "isis.xml",
              "errorTemplate": "errorTemplate.xml",
              "extraNamespaces": [
                  {"prefix": "h", "namespace":"urn:iso:std:iso:20022:tech:xsd:head.001.001.01"},
                  {"prefix": "d", "element": "Document"}
              ],
              "parameters" : {
                "frombic" : "/body/h:AppHdr/h:Fr/h:FIId/h:FinInstnId/h:BICFI",
                "tobic" : "/body/h:AppHdr/h:To/h:FIId/h:FinInstnId/h:BICFI",
                "msgid" : "/body/h:AppHdr/h:BizMsgIdr",
                "msgdef" : "/body/h:AppHdr/h:MsgDefIdr",
                "msgdate" : "/body/h:AppHdr/h:CreDt",
                "document" : "/body/d:Document",
                "msgiddoc" : "/body/d:Document/d:FIToFICstmrCdtTrf/d:GrpHdr/d:MsgId"
              }
            }]',true);

            $xmlinject->registerExtraNamespaces($xml,$matches[0]['extraNamespaces']);

            $fragment1 = $xml->xpath('/body/h:AppHdr/h:MsgDefIdr');
            $fragment2 = $xml->xpath('/body/d:Document/d:FIToFICstmrCdtTrf/d:GrpHdr/d:MsgId');

            $this::assertEquals('pacs.008.001.07',(string) $fragment1[0],'Should return valid value from fixed NS');
            $this::assertEquals('NONREF',(string) $fragment2[0],'Should return valid value for NS derived by element name');

            $vars = $xmlinject->getVariables($xml,$matches,0);

            $this::assertEquals('SOGEFRP0XXX', $vars['frombic']);
            $this::assertEquals('AV20190030001321',$vars['msgid']);
            $this::assertEquals('NONREF',$vars['msgiddoc']);

            $fragment3 = simplexml_load_string($vars['document']);

            $this::assertEquals(array(''=>'urn:iso:std:iso:20022:tech:xsd:pacs.008.001.08','xsi'=>'http://www.w3.org/2001/XMLSchema-instance'),$fragment3->getDocNamespaces());
           

    }

    function testGlobalNamespace():void {
        $xmlinject = new HorusXml('XXXX', null,'GREEN',self::$tracer);
        $input = '<a:test xmlns:a="urn:a"><a:test2>AAA</a:test2></a:test>';
        $this::assertEquals('urn:a',$xmlinject->getRootNamespace(simplexml_load_string($input),'aa'));

        $input2 = simplexml_load_string('<?xml version="1.0"?><saa:DataPDU xmlns:saa="urn:swift:saa:xsd:saa.2.0"><saa:Revision>2.0.9</saa:Revision><saa:Header><saa:Message><saa:SenderReference>SR20190780000010</saa:SenderReference><saa:MessageIdentifier>pacs.008.001.08</saa:MessageIdentifier><saa:Format>AnyXML</saa:Format><saa:Sender><saa:DN>ou=bpce,ou=target2,o=bpcefrpp,o=swift</saa:DN></saa:Sender><saa:Receiver><saa:DN>cn=rtgs,o=trgtxepm,o=swift</saa:DN></saa:Receiver><saa:InterfaceInfo><saa:UserReference>UR20190780000010</saa:UserReference><saa:ValidationLevel>Minimum</saa:ValidationLevel><saa:MessageNature>Financial</saa:MessageNature><saa:ProductInfo><saa:Product><saa:VendorName>Diamis</saa:VendorName><saa:ProductName>Cristal</saa:ProductName><saa:ProductVersion>5.0</saa:ProductVersion></saa:Product></saa:ProductInfo></saa:InterfaceInfo><saa:NetworkInfo><saa:Service>esmig.t2.iast</saa:Service></saa:NetworkInfo><saa:SecurityInfo><saa:SWIFTNetSecurityInfo><saa:IsNRRequested>true</saa:IsNRRequested></saa:SWIFTNetSecurityInfo></saa:SecurityInfo></saa:Message></saa:Header><saa:Body><AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><Fr><FIId><FinInstnId><BICFI>BPCEFRPPXXX</BICFI></FinInstnId></FIId></Fr><To><FIId><FinInstnId><BICFI>ZYEXFRP0XXX</BICFI></FinInstnId></FIId></To><BizMsgIdr>BizMsgIdr6520</BizMsgIdr><MsgDefIdr>pacs.008.001.08</MsgDefIdr><CreDt>2020-03-04T17:28:38Z</CreDt></AppHdr><Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.008.001.08"><FIToFICstmrCdtTrf><GrpHdr><MsgId>NONREF</MsgId><CreDtTm>2020-03-17T15:08:49+01:00</CreDtTm><NbOfTxs>1</NbOfTxs><SttlmInf><SttlmMtd>CLRG</SttlmMtd><ClrSys><Cd>TGT</Cd></ClrSys></SttlmInf></GrpHdr><CdtTrfTxInf><PmtId><InstrId>InstrId6520</InstrId><EndToEndId>EndToEndId6520</EndToEndId><UETR>eb6305c9-1f7f-49de-aed0-16487c27b43d</UETR></PmtId><IntrBkSttlmAmt Ccy="EUR">27535371.01</IntrBkSttlmAmt><IntrBkSttlmDt>2020-01-03</IntrBkSttlmDt><SttlmPrty>HIGH</SttlmPrty><ChrgBr>DEBT</ChrgBr><InstgAgt><FinInstnId><BICFI>BNPAFRPPXXX</BICFI></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BICFI>ZYFAFRP0XXX</BICFI></FinInstnId></InstdAgt><Dbtr/><DbtrAgt><FinInstnId/></DbtrAgt><CdtrAgt><FinInstnId/></CdtrAgt><Cdtr/></CdtTrfTxInf></FIToFICstmrCdtTrf></Document></saa:Body></saa:DataPDU>');

        $namespaces = $xmlinject->getRootNamespace($input2,'aaa');
        $input2->registerXPathNamespace('u',$namespaces);
        $xx = $xmlinject->getXpathVariable($input2,'/u:DataPDU/u:Revision');
        $this::assertEquals('2.0.9',$xx);
        $yy = $xmlinject->getXpathVariable($input2,'/u:DataPDU/*');

    }

    function testSpecialRegisterExtraNamespaces():void {
        $xml = '<?xml version="1.0"?>
        <saa:DataPDU xmlns:saa="urn:swift:saa:xsd:saa.2.0"><saa:Revision>2.0.9</saa:Revision><saa:Header><saa:Message><saa:SenderReference>SR20190780000010</saa:SenderReference><saa:MessageIdentifier>pacs.008.001.08</saa:MessageIdentifier><saa:Format>AnyXML</saa:Format><saa:Sender><saa:DN>ou=bpce,ou=target2,o=bpcefrpp,o=swift</saa:DN></saa:Sender><saa:Receiver><saa:DN>cn=rtgs,o=trgtxepm,o=swift</saa:DN></saa:Receiver><saa:InterfaceInfo><saa:UserReference>UR20190780000010</saa:UserReference><saa:ValidationLevel>Minimum</saa:ValidationLevel><saa:MessageNature>Financial</saa:MessageNature><saa:ProductInfo><saa:Product><saa:VendorName>Diamis</saa:VendorName><saa:ProductName>Cristal</saa:ProductName><saa:ProductVersion>5.0</saa:ProductVersion></saa:Product></saa:ProductInfo></saa:InterfaceInfo><saa:NetworkInfo><saa:Service>esmig.t2.iast</saa:Service></saa:NetworkInfo><saa:SecurityInfo><saa:SWIFTNetSecurityInfo><saa:IsNRRequested>true</saa:IsNRRequested></saa:SWIFTNetSecurityInfo></saa:SecurityInfo></saa:Message></saa:Header><saa:Body><AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><Fr><FIId><FinInstnId><BICFI>BPCEFRPPXXX</BICFI></FinInstnId></FIId></Fr><To><FIId><FinInstnId><BICFI>ZYEXFRP0XXX</BICFI></FinInstnId></FIId></To><BizMsgIdr>BizMsgIdr7503</BizMsgIdr><MsgDefIdr>pacs.008.001.08</MsgDefIdr><CreDt>2020-03-04T17:28:38Z</CreDt></AppHdr><pacs:Document xmlns:pacs="urn:iso:std:iso:20022:tech:xsd:pacs.008.001.08"><pacs:FIToFICstmrCdtTrf><pacs:GrpHdr><pacs:MsgId>NONREF</pacs:MsgId><pacs:CreDtTm>2020-03-17T15:08:49+01:00</pacs:CreDtTm><pacs:NbOfTxs>1</pacs:NbOfTxs><pacs:SttlmInf><pacs:SttlmMtd>CLRG</pacs:SttlmMtd><pacs:ClrSys><pacs:Cd>TGT</pacs:Cd></pacs:ClrSys></pacs:SttlmInf></pacs:GrpHdr><pacs:CdtTrfTxInf><pacs:PmtId><pacs:InstrId>-C0</pacs:InstrId><pacs:EndToEndId>InstrIdnumid</pacs:EndToEndId><pacs:UETR>eb6305c9-1f7f-49de-aed0-16487c27b43d</pacs:UETR></pacs:PmtId><pacs:IntrBkSttlmAmt Ccy="EUR">27535371.01</pacs:IntrBkSttlmAmt><pacs:IntrBkSttlmDt>2020-01-03</pacs:IntrBkSttlmDt><pacs:SttlmPrty>HIGH</pacs:SttlmPrty><pacs:ChrgBr>DEBT</pacs:ChrgBr><pacs:InstgAgt><pacs:FinInstnId><pacs:BICFI>BNPAFRPPXXX</pacs:BICFI></pacs:FinInstnId></pacs:InstgAgt><pacs:InstdAgt><pacs:FinInstnId><pacs:BICFI>ZYFAFRP0XXX</pacs:BICFI></pacs:FinInstnId></pacs:InstdAgt><pacs:Dbtr/><pacs:DbtrAgt><pacs:FinInstnId/></pacs:DbtrAgt><pacs:CdtrAgt><pacs:FinInstnId/></pacs:CdtrAgt><pacs:Cdtr/></pacs:CdtTrfTxInf></pacs:FIToFICstmrCdtTrf><!-- HORUS:DetectedAction=ACK_CSM_OK-DetectedMsgType=pacs.008-DetectedCode=2-C0GO --></pacs:Document></saa:Body></saa:DataPDU>
        ';
        $config = json_decode('[
            {
              "query": "isis.xsd",
              "comment": "body wrapper",
              "responseFormat": "isis.xml",
              "errorTemplate": "errorTemplate.xml",
              "extraNamespaces": [
                  {"prefix": "saa", "namespace":"urn:swift:saa:xsd:saa.2.0"},
                  {"prefix": "sah", "namespace":"urn:iso:std:iso:20022:tech:xsd:head.001.001.01"},
                  {"prefix": "d", "element": "Document"}
              ],
              "parameters" : {
                "header" : "/saa:DataPDU/saa:Body/sah:AppHdr",
                "doc" : "/saa:DataPDU/saa:Body/d:Document"
              }
            }]',true);
        $xmlinject = new HorusXml('ABCABC',null,'GREEN',self::$tracer);
        $input = simplexml_load_string($xml);
        $xmlinject->registerExtraNamespaces($input,$config[0]['extraNamespaces']);

    }
 
   function testDestOutQuery(): void
    {
        $xmlinject = new HorusXml('ZAZA', null,'GREEN',self::$tracer);

        $forwardparams = json_decode('[{"key":"onekey","phpvalue":"echo \"onevalue\";"},{"key":"two keys","phpvalue":"echo $vars[\"test\"];"}]', true);
        $url1 = 'http://localhost/?a=b';
        $url2 = 'http://localhost/';

        $this::assertEquals($xmlinject->formOutQuery(array($forwardparams), $url1, array('test' => 'XXX')), $url1 . '&test=XXX&onekey=onevalue&two+keys=XXX', 'Should return parameters urlencoded');
        $this::assertEquals($xmlinject->formOutQuery(array($forwardparams), $url2), $url2 . '?onekey=onevalue&two+keys=', 'Should return query string');
    }

}
