<?php

// We're assuming incoming data is enclosed in a dataPDU
// Extract Datapdu, then split document into chunks of 1024bytes
// Then form a multipart body
// No XSD validation needed. Incoming XML was validated beforehand.
class DataPduSwap implements HorusTransformerInterface
{

    const SENDER_PATH='/u:DataPDU/u:Header/u:Message/u:Sender/u:DN';
    const RECEIVER_PATH='/u:DataPDU/u:Header/u:Message/u:Receiver/u:DN';
    const RECEIVER_BIC='/u:DataPDU/u:Body/h:AppHdr/h:To/h:FIId/h:FinInstnId/h:BICFI';
    const DN_CONVERSION=array(
            'CPMEFRPPXXX'=>'ou=cpme,ou=target2,o=cpmefrpp,o=swift',
            'AGRIFRPPXXX'=>'cn=AGRIFRPP,ou=payment,o=bank,o=swift'
        );

    public static function doTransform(string $toTransform, array $headers, array $queryparams): string
    {
        $xml = simplexml_load_string($toTransform);

        //Let's assume for simplicity the actual versions are the right ones
        $xml->registerXPathNamespace('u','urn:swift:saa:xsd:saa.2.0');
        $xml->registerXPathNamespace('h', 'urn:iso:std:iso:20022:tech:xsd:head.001.001.01');

        //Extract the Body Header & Document
        $bodyHeader = reset($xml->xpath('/u:Body/u:Body/h:AppHdr'));
        if($bodyHeader!==false){
            $appHdr = $bodyHeader[0]->saveXML();
        }

        $bodyDocument = reset($xml->xpath("/u:Body/u:Body/*[local-name()='Document']"));
        if($bodyDocument!==false){
            $document = $bodyDocument[0]->saveXML();
        }
        
        // Remove the Body element from xml

        $doc = dom_import_simplexml($xml);
        $bodyxpath = new DOMXPath($doc->ownerDocument,false);
        $bodylist = reset($bodyxpath->query("//*[local-name()='Body']"));
        $body = $bodylist[0];
        $body->remove();

        $parts = array();
        $parts[] = $doc->asXML();

        $headerdocument = $appHdr . $document;

        $otherparts = str_split($headerdocument,1024);
        $parts = array_merge($parts, $otherparts);
        
        
        $receiverdn = HorusXml::getXpathValue($xml,self::RECEIVER_PATH);
        $receiverbic = HorusXml::getXpathValue($xml, self::RECEIVER_BIC);

        //Let's swap the values

        $senderxpath = reset($xml->xpath(self::SENDER_PATH));
        if ($senderxpath!==false) {
            $senderxpath[0] = $receiverdn;
        }

        $receiverxpath = reset($xml->xpath(self::RECEIVER_PATH));
        if ($receiverxpath!==false){
            $receiverxpath[0] = self::DN_CONVERSION[$receiverbic];
        }
        //Just send back the modified XML.
        return $xml->asXML();

    }
}
