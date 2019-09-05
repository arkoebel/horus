<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('lib/horus_xml.php');
require_once('HorusTestCase.php');

class HorusXmlTest extends HorusTestCase {

    function testNotXmlInput(): void {
        $xmlinject = new HorusXml('1234',null);
        $input = 'not xml!';
        $res = $xmlinject->doInject($input,'application/xml','',array(),'application/xml',array(),'templates/genericError.xml');

        $xml = simplexml_load_string($res);

        $this::assertNotFalse($xml,'Output should be XML');
        $namespaces = $xml->getDocNamespaces();
        $this::assertEquals($namespaces,array(''=>'urn:iso:std:iso:20022:tech:xsd:DRAFT2admi.007.001.01'),'Return should be in the admin namespace');
    }

    function testNoResultFound(): void {
        $xmlinject = new HorusXml('1234',null);
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
          }]',true);

        $res = $xmlinject->doInject($input,'application/xml','',$matches,'application/xml',array(),'templates/genericError.xml');

        $xml = simplexml_load_string($res);

        $this::assertNotFalse($xml,'Output should be XML');
        $namespaces = $xml->getDocNamespaces();
        $this::assertEquals($namespaces,array(''=>'urn:iso:std:iso:20022:tech:xsd:DRAFT2admi.007.001.01'),'Return should be in the admin namespace');
        $xml->registerXPathNamespace('a','urn:iso:std:iso:20022:tech:xsd:DRAFT2admi.007.001.01');
        $node = $xml->xpath('/a:Document/a:RctAck/a:Rpt/a:ReqHdlg/a:Desc');
        
        $this::assertEquals((string) $node[0],"Unable to find appropriate response.\n",'Should return Unable to find appropriate response');
    }

    function testFindSchemaFound():void {
        $xmlinject = new HorusXml('XXXX',null);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $xsd = $xmlinject->findSchema(simplexml_load_string($input));
        $this::assertEquals($xsd,'pacs.002.001.03.xsd');
    }

    function testFindSchemaNotFound():void {
        $xmlinject = new HorusXml('XXXX',null);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.XXX.YYY.ZZ"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $xsd = $xmlinject->findSchema(simplexml_load_string($input));
        $this::assertEquals($xsd,'');        
    }

    function testFindSchemaNotValidated():void {
        $xmlinject = new HorusXml('XXXX',null);
        $input = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03IPS"><FIToFIPmtStsRpt><GrpHdr><MsgId>1234567890</MsgId><CreDtTm>2012-12-13T12:12:12.000Z</CreDtTm><InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt><InstdAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstdAgt></GrpHdr><OrgnlGrpInfAndSts><OrgnlMsgId>1234567890</OrgnlMsgId><OrgnlMsgNmId>pacs.008</OrgnlMsgNmId><GrpSts>ACCP</GrpSts></OrgnlGrpInfAndSts><TxInfAndSts><StsId>1234567890</StsId><OrgnlEndToEndId>1234567890</OrgnlEndToEndId><OrgnlTxId>1234567890</OrgnlTxId><AccptncDtTm>2012-12-13T12:12:12.000Z</AccptncDtTm><OrgnlTxRef><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>PURP</Cd></CtgyPurp></PmtTpInf><DbtrAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></DbtrAgt></OrgnlTxRef></TxInfAndSts></FIToFIPmtStsRpt></Document>';
        $xsd = $xmlinject->findSchema(simplexml_load_string($input));
        $this::assertEquals($xsd,'');        
    }

    function testGetVariables():void {
        $xmlinject = new HorusXml('ZZZZ',null);
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
        $xml->registerXPathNamespace('u','urn:iso:std:iso:20022:tech:xsd:pacs.002.001.03');
        $vars = $xmlinject->getVariables($xml,json_decode($matches,true),0);
        $expectedvars = array('msgid'=>'1234567890','bic'=>'BNPAFRPPXXX','test'=>'','fragment'=>'<InstgAgt><FinInstnId><BIC>BNPAFRPPXXX</BIC></FinInstnId></InstgAgt>');
        $this::assertEquals($expectedvars,$vars);        
    }


}