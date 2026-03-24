<?php


require_once 'lib/horus_http.php';
require_once 'lib/horus_common.php';
require_once 'lib/horus_xml.php';
require_once 'lib/horus_business.php';
require_once 'lib/horus_simplejson.php';
require_once 'lib/horus_exception.php';
require_once 'vendor/autoload.php';
use RobRichards\XMLSecLibs\XMLSecurityDSig;


$tracing = new HorusTracingMock('test', 'mytest', 'operation', array());
$http = new HorusHttp('testHorusHttp', 'php://stdout', 'GREEN', $tracing);
$business = new HorusBusiness(
    'testHorusBusiness',
    'php://stdout',
    'GREEN',
    $tracing,
    null
);
/* 
$xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?><impl:BackOfficeXchange xmlns:impl="urn:intercope:box:implintf:xsd:$implintf" xmlns:bmi="urn:intercope:box:miintf:xsd:$miintf">
    <impl:BOX-Submission>
        <impl:SubmissionProfileName>SubmissionProfileName</impl:SubmissionProfileName>
        <impl:DomainModule>_TIPS</impl:DomainModule>
        <impl:Message>
            <impl:Submission-Parameter>
                <impl:SWIFT-IPRT-Parameter>
                    <bmi:Sender>Sender DN</bmi:Sender>
                    <bmi:Receiver>Receiver DN</bmi:Receiver>
                    <bmi:Service>Service</bmi:Service>
                    <bmi:MsgRef>EP8-18TIPS-P8E-14-189100142</bmi:MsgRef>
                </impl:SWIFT-IPRT-Parameter>
            </impl:Submission-Parameter>
            <impl:Payload>
                <impl:NoHeader>true</impl:NoHeader>
                <Document xmlns="urn:iso:std:iso:20022:tech:xsd:pacs.008.001.08"><FIToFICstmrCdtTrf><GrpHdr><MsgId>EP8-18TIPS-P8E-14-189100142</MsgId><CreDtTm>2024-07-08T10:01:42Z</CreDtTm><NbOfTxs>1</NbOfTxs><!-- MODIF : 1 --><TtlIntrBkSttlmAmt Ccy="EUR">4786.23</TtlIntrBkSttlmAmt><IntrBkSttlmDt>2024-07-08</IntrBkSttlmDt><SttlmInf><SttlmMtd>CLRG</SttlmMtd><ClrSys><Prtry>TIPS</Prtry></ClrSys></SttlmInf><PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl><LclInstrm><Cd>INST</Cd></LclInstrm><CtgyPurp><Cd>TAXS</Cd></CtgyPurp></PmtTpInf><InstgAgt><FinInstnId><BICFI>BNKAFRP0XXX</BICFI></FinInstnId></InstgAgt></GrpHdr><CdtTrfTxInf><PmtId><InstrId>IIDEP8TIPS-P8E-14-189100142</InstrId><EndToEndId>EIDEP8TIPS-P8E-14-189100142</EndToEndId><TxId>TIDEP8TIPS-P8E-14-189100142</TxId></PmtId><IntrBkSttlmAmt Ccy="EUR">4786.23</IntrBkSttlmAmt><AccptncDtTm>2024-07-08T12:01:42.664+02:00</AccptncDtTm><ChrgBr>SLEV</ChrgBr><UltmtDbtr><Nm>SIMONE VEIL</Nm><Id><OrgId><Othr><Id>35x</Id><SchmeNm><Cd>CUST</Cd></SchmeNm><Issr>35x</Issr></Othr></OrgId></Id></UltmtDbtr><Dbtr><Nm>BancoPopolare</Nm><PstlAdr><Ctry>FR</Ctry><AdrLine>02 Rue Jean jaures capitale du bresil et de la hollande ou de la suede</AdrLine><AdrLine>71200 Saint quentin</AdrLine></PstlAdr><Id><PrvtId><DtAndPlcOfBirth><BirthDt>1987-10-28</BirthDt><PrvcOfBirth>15</PrvcOfBirth><CityOfBirth>AIXEV</CityOfBirth><CtryOfBirth>FR</CtryOfBirth></DtAndPlcOfBirth></PrvtId></Id></Dbtr><DbtrAcct><Id><IBAN>FR44X1111122222333333333333</IBAN></Id><Prxy><Tp><Cd>Prxy</Cd></Tp><Id>IdPrxy</Id></Prxy></DbtrAcct><DbtrAgt><FinInstnId><BICFI>BNKAFRPPXXX</BICFI></FinInstnId></DbtrAgt><CdtrAgt><FinInstnId><BICFI>BNPAFRPPXXX</BICFI></FinInstnId></CdtrAgt><Cdtr><Nm>P8E-14-NOANSWER</Nm><PstlAdr><Ctry>FR</Ctry><AdrLine>02 Rue Jean jaures capitale du bresil et de la hollande ou de la suede</AdrLine><AdrLine>71200 Saint quentin</AdrLine></PstlAdr><Id><OrgId><AnyBIC>TOTOFRPPXXX</AnyBIC></OrgId></Id></Cdtr><CdtrAcct><Id><IBAN>FR5500111002220033333333355</IBAN></Id><Prxy><Tp><Prtry>PrxyPrtry35xxxxxxxxxxxxxxxxxxxxxxxx</Prtry></Tp><Id>IdPrxy</Id></Prxy></CdtrAcct><UltmtCdtr><Nm>Steves Saibiensituvepatanpy</Nm><Id><OrgId><LEI>BALISELEISUR20CHAR99</LEI></OrgId></Id></UltmtCdtr><Purp><Cd>GOVT</Cd></Purp><RmtInf><Strd><CdtrRefInf><Tp><CdOrPrtry><Cd>SCOR</Cd></CdOrPrtry><Issr>CdtrRefInf_TypeIssuer35x</Issr></Tp><Ref>CdtrRefInf_Ref35x</Ref></CdtrRefInf></Strd></RmtInf></CdtTrfTxInf></FIToFICstmrCdtTrf></Document>
            </impl:Payload>
        </impl:Message>
    </impl:BOX-Submission>
<BOX-SecData><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/><ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#hmac-sha256"/><ds:Reference URI=""><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/><ds:DigestValue>+dRF41QsO9tzVswEj5buyRZj4w0AEwgGkWkFLknXtUI=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>DFoYoABU66O7QUmigC9euS9NeSRFUlOhuxJzUxlQ0zA=</ds:SignatureValue></ds:Signature></BOX-SecData></impl:BackOfficeXchange>';

  HorusXML::validateSignature(
    $xml,
    null,
    array(
        'signatureAlgorithm' => 'SHA256',
        'digestAlgorithm' => 'SHA256',
        'method' => 'XMLDSIG',
        'documentNSPrefix' => 'impl',
        'documentNSURI' => 'urn:intercope:box:implintf:xsd:$implintf',
        'destinationXPath' => '/impl:BackOfficeXchange/BOX-SecData',
        'key' => 'SECRET_KEY',
    ),
    array()
);

echo "Signature OK\n"; */

$params = json_decode('[
    {"query": {"key": "key1", "value": "value1"}},
    {"query": {"key": "key1", "value": "value1"}, "queryMatch": "match"},
    {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1",
        "queryValue": "qvalue1"}},
    {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1",
        "queryValue": "qvalue1"},"queryMatch": "match"},
    {"query": {"key": "key1", "value": "value1", "queryKey": "qkey1",
        "queryValue": "qvalue1"},"queryMatch": "match"},
    {"query": {"key": "zip"},"queryMatch": "match3"},
    {"query": {"jsonpath": "$.key2", "value": "toto"}},
    {"query": {"jsonpath": "$.key2"},"queryMatch": "match4"},
    {"query": {"key": "zip"}}
]',
    true
);

//$this->common->mlog('params : ' . print_r($params, true), 'DEBUG');
$input1   = ['key1' => 'value1', 'someotherkey' => 'XXX', 'parttomatch' => 'true'];
$input2   = ['key1' => 'value1', 'someotherkey' => 'XXX', 'part' => 'true'];
$input3   = ['key2' => ['this', 'toto', 'nomatch4'], 'someotherkey' => 'XXX'];
$qparams1 = ['qkey1' => 'nope'];
$qparams2 = ['qkey1' => 'qvalue1'];

echo $business->locateJson($params, $input3);
//echo $business->locateJson($params, $input1, null) . "\n";
