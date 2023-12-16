<?php

// We're assuming incoming data is enclosed in a dataPDU
// Generate a SWIFT ACK report from any DataPDU
class DataPduAck implements HorusTransformerInterface
{

    public static function doTransform(string $toTransform, array $headers, array $queryparams): string
    {
        $xml = simplexml_load_string($toTransform);

        //Let's assume for simplicity the actual versions are the right ones
        $xml->registerXPathNamespace('u','urn:swift:saa:xsd:saa.2.0');

        //Retrieve info from the incoming payload
        $reference = HorusXml::getXpathValue($xml,'/u:DataPDU/u:Header/u:Message/u:SenderReference');
        $revision = HorusXml::getXpathValue($xml, '/u:DataPDU/u:Revision');
        $seq = '00002';

        //Apply the template
        $template = "<?xml version=\"1.0\"?>
<saa:DataPDU xmlns:saa=\"urn:swift:saa:xsd:saa.2.0\">
    <saa:Revision>$revision</saa:Revision>
    <saa:Header>
        <saa:MessageStatus>
            <saa:SenderReference>$reference</saa:SenderReference>
            <saa:SeqNr>$seq</saa:SeqNr>
            <saa:IsSuccess>true</saa:IsSuccess>
        </saa:MessageStatus>
    </saa:Header>
</saa:DataPDU>";

        // Trying to validate output, just in case

        $outputxml = new DOMDocument();
        $outputxml->loadXML(preg_replace('/\s*(<[^>]*>)\s*/', '$1', $template));
        if ($outputxml->schemaValidate('xsd/saa.2.0.13.xsd') !== true) {
            //Oops. Didn't validate...
            //Return error
            $errorMessage='ACK didn\'t pass XSD validation';
            ob_start();
            include_once 'templates\genericError.xml';
            $output = ob_get_contents();
            ob_end_clean();
            return $output;
        } else {
            //Get result
            return $template;
        }
    }

}
