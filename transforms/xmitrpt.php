<?php

// We're assuming incoming data is enclosed in a dataPDU
// Generate a SWIFT Transmission Report from any DataPDU
class DataPduXmitRpt implements HorusTransformerInterface
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
        $sender = HorusXml::getXpathValue($xml, '/u:DataPDU/u:Body/h:AppHdr/h:Fr/h:FIId/h:FinInstnId/h:BICFI');
        $dte = gmdate('YmdHis');
 
        return "<?xml version=\"1.0\"?>
<saa:DataPDU xmlns:saa=\"urn:swift:saa:xsd:saa.2.0\">
    <saa:Revision>$revision</saa:Revision>
    <saa:Header>
        <saa:TransmissionReport>
            <saa:SenderReference>$reference</saa:SenderReference>
            <saa:ReconciliationInfo>$reference-$bizidr</saa:ReconciliationInfo>
            <saa:NetworkDeliveryStatus>NetworkAcked</saa:NetworkDeliveryStatus>
            <saa:OriginalInstanceAddressee>
                <saa:X1>$sender</saa:X1>
            </saa:OriginalInstanceAddressee>
            <saa:ReportingApplication>SWIFTNetInterface</saa:ReportingApplication>
            <saa:NetworkInfo>
                <saa:Priority>Normal</saa:Priority>
                <saa:IsPossibleDuplicate>false</saa:IsPossibleDuplicate>
                <saa:Service>swift.eni</saa:Service>
                <saa:Network>SWIFTNet</saa:Network>
                <saa:SessionNr>000008</saa:SessionNr>
                <saa:SeqNr>000000001</saa:SeqNr>
                <saa:SWIFTNetNetworkInfo>
                    <saa:SWIFTRef>SWITCH21-2006-11-02T08:41:47.11481.1454972Z</saa:SWIFTRef>
                    <saa:SNLRef>SNL10391-2006-11-02T08:35:20.6268.030308Z</saa:SNLRef>
                    <saa:Reference>c98e3458-1dd1-11b2-91dd-5bdc6d3f0133</saa:Reference>
                    <saa:SnFInputTime>0102:2006-11-02T08:26:47</saa:SnFInputTime>
                </saa:SWIFTNetNetworkInfo>
            </saa:NetworkInfo>
            <saa:Interventions>
                <saa:Intervention>
                    <saa:IntvCategory>TransmissionReport</saa:IntvCategory>
                    <saa:CreationTime>$dte</saa:CreationTime>
                    <saa:OperatorOrigin>SYSTEM</saa:OperatorOrigin>
                    <saa:Contents>
                        <saa:AckNack>
                            <saa:PseudoAckNack>
                                {1:F21SAAABEBBAXXX000008000000001}{4:{177:231203154617}{451:0}{311:ACK}{108:$reference}}</saa:PseudoAckNack>
                        </saa:AckNack>
                    </saa:Contents>
                </saa:Intervention>
            </saa:Interventions>
            <saa:IsRelatedInstanceOriginal>true</saa:IsRelatedInstanceOriginal>
            <saa:MessageCreator>ApplicationInterface</saa:MessageCreator>
            <saa:IsMessageModified>false</saa:IsMessageModified>
            <saa:MessageFields>NoOriginal</saa:MessageFields>
        </saa:TransmissionReport>
    </saa:Header>
</saa:DataPDU>";

    }
}

