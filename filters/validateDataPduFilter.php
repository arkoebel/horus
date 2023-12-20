<?php

// Let's try to get rid of INDIGO boxes just to validate an incoming DataPDU fragment
class ValidateDataPduFilter implements HorusFilterInterface 
{
    // Try not to presume the actual namespaces
    const APP_HDR_XPATH = "//*[local-name()='Body']/*[local-name()='AppHdr']";
    const DOCUMENT_XPATH = "//*[local-name()='Body']/*[local-name()='Document']";
    const SERVICE_XPATH = "//*[local-name()='Header']/*[local-name()='Message']/*[local-name()='NetworkInfo']/*[local-name()='Service']";
    const SERVICE_TO_SYSTEM = array('esmig.t2.iast!pu'=>'RTGS','test'=>'ANOTHER_SYSTEM');
    const SAA_XSD = 'xsd/saa.2.0.13.xsd';


    private function isXmlFragmentValid(SimpleXmlElement $xml, String $path, String $service): bool {

        // Performs XPath search
        $fragment = $xml->xpath($path);
        if (false !== $fragment && is_array($fragment) && (!empty($fragment))) {

            // Generate a new document with the extracted fragment
            $xpathResult = $fragment[0];
            $fragdom = dom_import_simplexml($xpathResult);
            $fragdoc = new DomDocument('1.0', 'utf-8');
            $fragdoc->appendChild($fragdoc->importNode($fragdom, true));

            // Retrieve the last part of the namespace
            $headns = explode(':',$fragdoc->documentElement->namespaceURI);
            $headns = array_pop($headns);

            // Build the schema name
            $head_schema = 'xsd/' . self::SERVICE_TO_SYSTEM[$service] . '_' . $headns . '.xsd';

            // Tests if the schema is present
            if (!file_exists($head_schema)){
                return false;
            }

            // Perform the schema validation
            return $fragdoc->schemaValidate($head_schema);

        }else{
            // XPath not found
            return false;
        }
    }

    public function doFilter($input, $source, $headers, $queryparams): bool
    {

        // Load the incoming XML. We don't need to 
        // set up namespaces since our XPaths are all relative.
        $xml = simplexml_load_string($input);

        // If xml isn't well-formed, we can fail here.
        if ($xml === false){
            return false;
        }

        // Have to switch to DOM to perform XSD validation
        $domelement = dom_import_simplexml($xml);
        $domdoc = $domelement->ownerDocument;

        // Disable XSD validation errors
        libxml_use_internal_errors(true);

        // Fail if we didn't validate the SAA Schema
        if(!$domdoc->schemaValidate(self::SAA_XSD)) {
            return false;
        }

        // We'll need this to select the right schema later
        $service = HorusXml::getXpathValue($xml,self::SERVICE_XPATH);

        if (!array_key_exists($service, self::SERVICE_TO_SYSTEM)){
            // Either we didn't have the right configuration or the incoming document has the wrong system
            return false;
        }

        // Test AppHdr
        if(!$this->isXmlFragmentValid($xml,self::APP_HDR_XPATH, $service)){
            return false;
        }
       
        // Test Document
        if(!$this->isXmlFragmentValid($xml,self::DOCUMENT_XPATH, $service)){
            return false;
        }

        return true;
    }

}
