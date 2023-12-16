<?php

// We're assuming incoming data is enclosed in a dataPDU
// Generate a SWIFT Delivery Notification report from any DataPDU
class DataPduDelivNotif implements HorusTransformerInterface
{

    public static function doTransform(string $toTransform, array $headers, array $queryparams): string
    {
        $xml = simplexml_load_string($toTransform);

        //Let's assume for simplicity the actual versions are the right ones
        $xml->registerXPathNamespace('u','urn:swift:saa:xsd:saa.2.0');
        $xml->registerXPathNamespace('h', 'urn:iso:std:iso:20022:tech:xsd:head.001.001.01');

        //Retrieve info from the incoming payload
        $reference = HorusXml::getXpathValue($xml,'/u:DataPDU/u:Header/u:Message/u:SenderReference');
        $revision = HorusXml::getXpathValue($xml, '/u:DataPDU/u:Revision');
        $bizidr = HorusXml::getXpathValue($xml, '/u:DataPDU/u:Body/h:AppHdr/h:/BizMsgIdr');
        $msgidr = HorusXml::getXpathValue($xml, '/u:DataPDU/u:Header/u:Message/u:MessageIdentifier');
        $sender = HorusXml::getXpathValue($xml, '/u:DataPDU/u:Body/h:AppHdr/h:Fr/h:FIId/h:FinInstnId/h:BICFI');
        $dte = gmdate('Y-m-d\TH:i:s\Z');
 
        return "<?xml version=\"1.0\"?>
<saa:DataPDU xmlns:saa=\"urn:swift:saa:xsd:saa.2.0\">
    <saa:Revision>$revision</saa:Revision>
    <saa:Header>
        <saa:DeliveryNotification>
            <saa:ReconciliationInfo>$reference-$bizidr</saa:ReconciliationInfo>
            <saa:ReceiverDeliveryStatus>RcvDelivered</saa:ReceiverDeliveryStatus>
            <saa:MessageIdentifier>$msgidr</saa:MessageIdentifier>
            <saa:Receiver>
                <saa:BIC12>$sender</saa:BIC12>
                <saa:FullName>
                    <saa:X1>$sender</saa:X1>
                </saa:FullName>
            </saa:Receiver>
            <saa:InterfaceInfo>
                <saa:MessageCreator>FINInterface</saa:MessageCreator>
                <saa:MessageContext>Original</saa:MessageContext>
                <saa:MessageNature>Network</saa:MessageNature>
            </saa:InterfaceInfo>
            <saa:NetworkInfo>
                <saa:Priority>System</saa:Priority>
                <saa:IsPossibleDuplicate>false</saa:IsPossibleDuplicate>
                <saa:Service>swift.fin</saa:Service>
                <saa:Network>Other</saa:Network>
                <saa:SessionNr>0023</saa:SessionNr>
                <saa:SeqNr>000299</saa:SeqNr>
                <saa:SWIFTNetNetworkInfo>
                    <saa:SnFDeliveryTime>$dte</saa:SnFDeliveryTime>
                </saa:SWIFTNetNetworkInfo>
            </saa:NetworkInfo>
            <saa:SecurityInfo>
                <saa:FINSecurityInfo>
                    <saa:ChecksumResult>Success</saa:ChecksumResult>
                    <saa:ChecksumValue>8BB8247F0C2E</saa:ChecksumValue>
                </saa:FINSecurityInfo>
            </saa:SecurityInfo>
        </saa:DeliveryNotification>
    </saa:Header>
</saa:DataPDU>";

    }
}

