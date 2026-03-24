<?php

$params = [
    'res'           => [],
    'nbok'          => 0,
    'nbko'          => 0,
    'amtok'         => 0.0,
    'amtko'         => 0.0,
    'total'         => 0.0,
    'curInstrId'    => '',
    'curTxId'       => '',
    'curEndToEndId' => '',
    'status'        => '',
    'sendingInst'   => '',
    'receivingInst' => '',
    'fileRef'       => '',
    'svcId'         => '',
    'tstCode'       => '',
    'fType'         => '',
    'fTime'         => '',
    'msgId'         => '',
    'creDtTime'     => '',
    'settltDate'    => '',
    'pacs'          => ''];



    function readInput($file, $schema, $params, $cutoff = 15.0)
{

    $input = fopen($file, 'r');

    $xml = XMLReader::fromStream($input);

    $xml->setSchema($schema);
    $depth = 1;
    $inDbtrAgt = false;
    $inCdtAgt = false;
    $amt = 0.0;

    while ($xml->read() && ($depth != 0)) {
        if (! $xml->isValid()) {
            die("Couldn't validate xml at" . $xml->name . "\n");
        }

        if ($xml->nodeType == XMLReader::ELEMENT) {
            $depth++;
            if ($xml->name === 'TtlIntrBkSttlmAmt') {
                $params['total'] = floatval($xml->readString());
                echo "Total : " . $params['total'] . "\n";
            } elseif ($xml->localName === 'InstrId') {
                $params['curInstrId'] = $xml->readString();
            } elseif ($xml->localName === 'TxId') {
                $params['curTxId'] = $xml->readString();
            } elseif ($xml->localName === 'EndToEndId') {
                $params['curEndToEndId'] = $xml->readString();
            } elseif ($xml->localName === 'CdtrAgt') {
                $inCdtAgt = true;
            } elseif ($xml->localName === 'DbtrAgt') {
                $inDbtrAgt = true;
            } elseif ($xml->localName === 'BICFI') {
                if ($inCdtAgt) {
                    $params['bicCdtAgt'] = $xml->readString();
                } elseif ($inDbtrAgt) {
                    $params['bicDbtrAgt'] = $xml->readString();
                }
            } elseif ($xml->localName === 'IntrBkSttlmAmt') {
                $amt = floatval($xml->readString());
                if ($amt > $cutoff) {
                    $params['nbko']++;
                    $params['amtko'] += $amt;
                    $params['status'] = 'RJCT';
                    echo 'KO : ' . $amt . "\n";
                } else {
                    $params['nbok']++;
                    $params['amtok'] += $amt;
                    $params['status'] = 'ACCP';
                    echo 'OK : ' . $amt . "\n";
                }
            } elseif ($xml->localName === 'SndgInst') {
                $params['sendingInst'] = $xml->readString();
            } elseif ($xml->localName === 'RcvgInst') {
                $params['receivingInst'] = $xml->readString();
            } elseif ($xml->localName === 'FileRef') {
                $params['fileRef'] = $xml->readString();
            } elseif ($xml->localName === 'SrvcId') {
                $params['svcId'] = $xml->readString();
            } elseif ($xml->localName === 'TstCode') {
                $params['tstCode'] = $xml->readString();
            } elseif ($xml->localName === 'FType') {
                $params['fType'] = $xml->readString();
            } elseif ($xml->localName === 'FTime') {
                $params['fTime'] = $xml->readString();
            } elseif ($xml->localName === 'MsgId') {
                $params['msgId'] = $xml->readString();
            } elseif ($xml->localName === 'CreDtTm') {
                $params['creDtTime'] = $xml->readString();
            } elseif ($xml->localName === 'IntrBkSttlmDt') {
                $params['settltDate'] = $xml->readString();
            } elseif ($xml->localName === 'FIToFICstmrDrctDbt') {
                $tmparray       = explode(':', $xml->getAttribute('xmlns'));
                $params['pacs'] = array_pop($tmparray);
            }

        } elseif ($xml->nodeType == XMLReader::END_ELEMENT) {
            $depth--;
            if ($xml->localName === 'CdtrAgt') {
                $inCdtAgt = false;
            } elseif ($xml->localName === 'DbtrAgt') {
                $inDbtrAgt = false;
            } elseif ($xml->localName === 'DrctDbtTxInf') {
                if ($params['status'] === 'RJCT') {
                    $params['res'][] = [
                        'InstrId'    => $params['curInstrId'],
                        'TxId'       => $params['curTxId'],
                        'EndToEndId' => $params['curEndToEndId'],
                        'BICCdtr'    => $params['bicCdtAgt'],
                        'BICDbtr'    => $params['bicDbtrAgt'],
                        'Amt'        => $amt];
                }
            }
        }
    }

    fclose($input);

    return $params;
}

function writeOutput($file, $params)
{
    $fo = fopen($file, 'w');
    $xw = XMLWriter::toStream($fo);

    $xw->setIndent(true);
    $xw->startDocument('1.0', 'UTF-8');
    $xw->startElementNs('S2SDDRsf', 'MPEDDRsfBlkDirDeb', 'urn:S2SDDRsf:xsd:$MPEDDRsfBlkDirDeb');
    $xw->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $xw->writeAttribute('xsi:schemaLocation', 'urn:S2SDDRsf:xsd:$MPEDDRsfBlkDirDeb MPEDDRsfBlkDirDeb.xsd');
    $xw->writeAttribute('xmlns', 'urn:S2SDDRsf:xsd:$MPEDDRsfBlkDirDeb');
    $xw->writeElementNs('S2SDDRsf', 'SndgInst', null, $params['sendingInst']);
    $xw->writeElementNs('S2SDDRsf', 'RcvgInst', null, $params['receivingInst']);
    $xw->writeElementNs('S2SDDRsf', 'SrvcId', null, $params['svcId']);
    $xw->writeElementNs('S2SDDRsf', 'TstCode', null, $params['tstCode']);
    $xw->writeElementNs('S2SDDRsf', 'FType', null, 'RSF');
    $xw->writeElementNs('S2SDDRsf', 'FileRef', null, $params['fileRef']);
    $xw->writeElementNs('S2SDDRsf', 'RoutingInd', null, 'ALL');
    $xw->writeElementNs('S2SDDRsf', 'FileBusDt', null, $params['settltDate']);
    $xw->writeElementNs('S2SDDRsf', 'FileLacNo', null, $params['nbok'] + $params['nbko']);
    $xw->startElementNs('S2SDDRsf', 'FIToFIPmtStsRptS2', null);
    $xw->writeAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.10S2');
    $xw->startElement('GrpHdr');
    $xw->writeAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.10S2');
    $xw->writeElement('MsgId', 'AAAAA');
    $xw->writeElement('CreDtTm', $params['creDtTime']);
    $xw->startElement('InstgAgt');
    $xw->startElement('FinInstnId');
    $xw->writeElement('BICFI', $params['receivingInst'] . 'XXX');
    $xw->endElement(); //FinInstnId
    $xw->endElement(); //InstgAgt
    $xw->endElement(); //GrpHdr
    $xw->startElement('OrgnlGrpInfAndSts');
    $xw->writeElement('OrgnlMsgId', $params['msgId']);
    $xw->writeElement('OrgnlMsgNmId', $params['pacs']);
    $xw->writeElement('OrgnlNbOfTxs', $params['nbko'] + $params['nbok']);
    $xw->writeElement('OrgnlCtrlSum', $params['amtok'] + $params['amtko']);
    if (($params['nbok'] != 0) && ($params['nbko'] != 0)) {
        $xw->writeElement('GrpSts', 'PART');
        $cd = 'B01';
    } elseif ($params['nbko'] == 0) {
        $xw->writeElement('GrpSts', 'ACCP');
        $cd = 'B00';
    } else { // $nbok==0
        $xw->writeElement('GrpSts', 'RJCT');
        $cd = 'B99';
    }
    $xw->startElement('StsRsnInf');
    $xw->startElement('Orgtr');
    $xw->startElement('Id');
    $xw->startElement('OrgId');
    $xw->writeElement('AnyBIC', $params['receivingInst']);
    $xw->endElement(); //OrgId
    $xw->endElement(); //Id
    $xw->endElement(); //Orgtr
    $xw->startElement('Rsn');
    $xw->writeElement('Cd', $cd);
    $xw->endElement(); //Rsn
    $xw->endElement(); //StsRsnInf
    if ($params['nbok'] != 0) {
        $xw->startElement('NbOfTxsPerSts');
        $xw->writeElement('DtldNbOfTxs', $params['nbok']);
        $xw->writeElement('DtldSts', 'ACCP');
        $xw->writeElement('DtldCtrlSum', $params['amtok']);
        $xw->endElement();
    }
    if ($params['nbko'] != 0) {
        $xw->startElement('NbOfTxsPerSts');
        $xw->writeElement('DtldNbOfTxs', $params['nbko']);
        $xw->writeElement('DtldSts', 'RJCT');
        $xw->writeElement('DtldCtrlSum', $params['amtko']);
        $xw->endElement();
    }
    $xw->endElement(); //FIToFIPmtStsRptS2
    if ($cd == 'B01') {
        foreach ($params['res'] as $r) {
            $xw->startElement('TxInfAndSts');
            $xw->writeElement('StsId', 'StsId');
            $xw->writeElement('OrgnlInstrId', $r['InstrId']);
            $xw->writeElement('OrgnlEndToEndId', $r['EndToEndId']);
            $xw->writeElement('OrgnlTxId', $r['TxId']);
            $xw->writeElement('TxSts', 'RJCT');
            $xw->startElement('StsRsnInf');
            $xw->startElement('Orgtr');
            $xw->startElement('Id');
            $xw->startElement('OrgId');
            $xw->writeElement('AnyBIC', $params['receivingInst']);
            $xw->endElement(); //OrgId
            $xw->endElement(); //Id
            $xw->endElement(); //Orgtr
            $xw->startElement('Rsn');
            $xw->writeElement('Cd', 'ED98');
            $xw->endElement(); //Rsn
            $xw->endElement(); //StsRsnInf
            $xw->startElement('OrgnlTxRef');
            $xw->startElement('IntrBkSttlmAmt');
            $xw->writeAttribute('Ccy', 'EUR');
            $xw->text($r['Amt']);
            $xw->endElement(); //IntrBkSttlmAmt
            $xw->writeElement('IntrBkSttlmDt', $params['settltDate']);
            $xw->startElement('DbtrAgt');
            $xw->startElement('FinInstnId');
            $xw->writeElement('BICFI', $r['BICDbtr']);
            $xw->endElement(); //FinInstnId
            $xw->endElement(); //DbtrAgt
            $xw->startElement('CdtrAgt');
            $xw->startElement('FinInstnId');
            $xw->writeElement('BICFI', $r['BICCdtr']);
            $xw->endElement(); //FinInstnId
            $xw->endElement(); //CdtrAgt
            $xw->endElement(); //OrgnlTxRef
            $xw->endElement(); //TxInfAndSts

        }
    }

    $xw->endElement(); //MPEDDRsfBlkDirDeb
    $xw->endDocument();
    //fwrite($fo,$xw->outputMemory());
    //$out =  $xw->outputMemory();
    //echo $out . "\n";
    //$outxml = new DOMDocument();
    //$outxml->loadXML($out);
    //if (!$outxml->schemaValidate('xsd/MPEDDRsfBlkDirDeb.xsd')){
    //    echo print_r(libxml_get_errors(), true);
    //}
    fclose($fo);
}

$params = readInput('samples/fluw_emis2.xml', 'xsd/MPEDDIdfBlkDirDeb.xsd', $params, 15);

echo print_r($params, true);

writeOutput('samples/output.xml', $params);

echo "OK : " . $params['nbok'] . " " . $params['amtok'] . "\n";
echo "KO : " . $params['nbko'] . " " . $params['amtko'] . "\n";
if ($params['nbko'] > 0 && $params['nbok'] != 0) {
    echo "Mixed\n";
} elseif ($params['nbko'] == 0 && $params['nbok'] != 0) {
    echo "All OK\n";
} else {
    echo "All KO\n";
}

if ($params['total'] != ($params['amtok'] + $params['amtko'])) {
    echo "Total mismatch " . $params['total'] . "\n";
}

$outxml = new DOMDocument();
$outxml->load('samples/output.xml');
if ($outxml->schemaValidate('xsd/MPEDDRsfBlkDirDeb.xsd')) {
    echo "Output is valid\n";
} else {
    echo "Output is invalid\n";
}
