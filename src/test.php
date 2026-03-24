<?php

//$pacs = file_get_contents('samples/pacs008.xml');

use PHPUnit\TextUI\XmlConfiguration\File;

require_once 'mappers/mapperInterface.php';
require_once 'lib/horus_xml.php';
require_once 'mappers/dynamicMapper.php';
require_once 'filters/horusFilterInterface.php';
require_once 'filters/validateDataPduFilter.php';
require_once 'transforms/transformerInterface.php';
require_once 'transforms/fileactjoin.php';

//$filter = new ValidateDataPduFilter();

//if($filter->doFilter($pacs, 'test', array(), array())){
//    echo "OK";
//}else{
//    echo "FAILED";
//}

$transform = new Fileactjoin();
$ct = 'multipart/form-data; boundary=abcd';
$input = 
    '--abcd' . "\n" .
    'Content-disposition: form-data; name=test' . FileactJoin::EOL .
    'Content-type: text/plain' . FileactJoin::EOL .
    'Content-transfer-encoding: base64' . FileactJoin::EOL .
    FileactJoin::EOL .
    base64_encode('<?xml version="1.0" encoding="UTF-8"?><DataPDU xmlns="urn:swift:saa:xsd:saa.2.0"><Revision>2.0.10</Revision><Header><Message><SenderReference>ES20233480000420</SenderReference><MessageIdentifier>colr.xxx.creditclaimsfile</MessageIdentifier><Format>File</Format><Sender><DN>ou=gscf,ou=ecm,o=gscffr22,o=swift</DN></Sender><Receiver><DN>cn=ecms,o=trgtxecm,o=swift</DN></Receiver><InterfaceInfo><UserReference>ES20233480000420</UserReference><MessageNature>Financial</MessageNature></InterfaceInfo><NetworkInfo><Service>esmig.ecms.fast!pu</Service><SWIFTNetNetworkInfo><RequestType>colr.xxx.creditclaimsfile</RequestType><FileInfo>SwCompression=None</FileInfo></SWIFTNetNetworkInfo></NetworkInfo><FileLogicalName>colr.xxx.creditclaimsfile-ES20233480000420</FileLogicalName></Message></Header></DataPDU>') . FileactJoin::EOL .
    '--abcd' . "\n" .
    'Content-disposition: form-data; name=test' . FileactJoin::EOL .
    'Content-type: text/plain' . FileactJoin::EOL .
    'Content-transfer-encoding: base64' . FileactJoin::EOL .
    FileactJoin::EOL .
    base64_encode('<AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01">
    <Fr>
      <FIId>
        <FinInstnId>
          <BICFI>GSCFFR22XXX</BICFI>
          <ClrSysMmbId>
            <ClrSysId>
              <Prtry>ECMS</Prtry>
            </ClrSysId>
            <MmbId>TOBEMODIFIEDSURGSF</MmbId>
          </ClrSysMmbId>
          <Othr>
            <Id>BDFEFR2TXXX</Id>
          </Othr>
        </FinInstnId>
      </FIId>
    </Fr>
    <To>
      <FIId>
        <FinInstnId>
          <BICFI>TRGTXECMXXX</BICFI>
          <Othr>
            <Id>BDFEFR2TXXX</Id>
          </Othr>
        </FinInstnId>
      </FIId>
    </To>
    <BizMsgIdr>ES20233480000420</BizMsgIdr>
    <MsgDefIdr>colr.xxx.creditclaimsfile</MsgDefIdr>
    <CreDt>2023-12-15T10:59:03Z</CreDt>
    </AppHdr>')  . FileactJoin::EOL .
    '--abcd' . "\n" .
    'Content-disposition: form-data; name=test' . FileactJoin::EOL .
    'Content-type: text/plain' . FileactJoin::EOL .
    'Content-transfer-encoding: base64' . FileactJoin::EOL .
    FileactJoin::EOL .
    base64_encode('<Document xmlns="urn:swift:xsd:colr.xxx.creditclaimsfile">
    <CrdtClms>
            <GrpHdr>
                    <MsgId>FR159682023000042</MsgId>
                    <CreDtTm>2023-12-15T11:59:02.000</CreDtTm>
                    <SttlmDt>2023-12-15</SttlmDt>
                    <CptyRiad>FR15968</CptyRiad>
                    <MsgPgntn>
                            <PgNb>1</PgNb>
                            <LastPgInd>true</LastPgInd>
                    </MsgPgntn>
                    <NbOfCCR>0</NbOfCCR>
                    <NbOfCCU>0</NbOfCCU>
                    <NbOfCCOAU>1</NbOfCCOAU>
                    <NbOfRR>0</NbOfRR>
                    <NbOfRU>0</NbOfRU>
                    <NbOfMob>0</NbOfMob>
                    <NbOfDemob>0</NbOfDemob>
            </GrpHdr>
            <Ccru>
                    <OpeTp>CCOAU</OpeTp>
                    <CCRef>CCDPREF0214934-RT-FIBEN03</CCRef>
                    <ECMSCCId>FRCC20490000003</ECMSCCId>
                    <InstrId>231215AEGQHQ12UX</InstrId>
                    <OutstdAmt Ccy="EUR">3333.00</OutstdAmt>
                    <UpdateDt>2023-12-15</UpdateDt>
            </Ccru>
    </CrdtClms>
</Document>')  . FileactJoin::EOL .
    '--abcd--';

echo $transform->doTransform($input, array('Content-Type'=>$ct),array());

