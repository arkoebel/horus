<?php

// We're assuming incoming data is a multipart fileact dataPDU

class FileactJoin implements HorusTransformerInterface
{
    public const EOL = "\r\n";

    public static function doTransform(string $toTransform, array $headers, array $queryparams): string
    {
        // First, if it's not a multipart, we don't want it
        if(array_key_exists('Content-Type', $headers) && (strpos('multipart',$headers['Content-Type'])===false)){
            // Extract boundary
            preg_match('/boundary=(.*)/', $headers['Content-Type'], $mm);
            $boundary = $mm[1];

            // Split input into content chunks
            $parts = explode('--' . $boundary . FileactJoin::EOL, $toTransform);
            
            $envelope = '';
            $payload = '';
            foreach ($parts as $part){
                if ($part !== ''){
                    // Separate chunk headers from the body
                    $sep = explode(FileactJoin::EOL . FileactJoin::EOL, $part);
                    // Remove parts headers
                    array_shift($sep);
                    // Reassemble base64 content
                    $sep = implode(FileactJoin::EOL . FileactJoin::EOL, $sep);

                    // Last part has extra '--' used for boundary closing.
                    // Need to remove them
                    if(str_ends_with($sep, '--')){
                        $sep = rtrim($sep, '-');
                    }
                    
                    if('' !== $envelope){
                        // Other parts are joined together
                        //$payload .= base64_decode($sep,true);
                        $payload .= $sep;
                    } else {
                        // First part has to be taken separately
                        //$envelope = base64_decode($sep,true);
                        $envelope .= $sep;
                    }
                }
            }

            // Remove the last boundary
            $last = strpos($payload,'--' . $boundary);
            $payload = substr($payload,0,$last);

            // Let's switch to XML
            libxml_use_internal_errors(true);

            // Create and load XML DOM objects
            // We're forcing the namespace for the Body element to saa.2.0; this might need to change later
            $envelopeDom = new DOMDocument('1.0', 'UTF-8');
            $envelopeDom->loadXml($envelope);
            $payloadDom = new DOMDocument('1.0', 'UTF-8');
            $payloadDom->loadXml('<Body xmlns="urn:swift:saa:xsd:saa.2.0">' . $payload . '</Body>');

            // Test if the XMLs were properly formatted
            If (0 === count(libxml_get_errors())){
                // At least, we're dealing with proper XML
                $saaBody = $payloadDom->getElementsByTagNameNS('urn:swift:saa:xsd:saa.2.0', 'Body')->item(0);
                // We should have something here, since we forced the Body element a few lines ago
                if(false !== $saaBody){
                    // Import the SAA Body element into the DataPDU document and append it at the last place
                    $target = $envelopeDom->importNode($saaBody->cloneNode(true), true);
                    $envelopeDom->firstChild->appendChild($target);
                    // And we're done
                    return $target->ownerDocument->saveXML();
                } else {
                    // Case empty SAA Body (shouldn't happen)
                    return '';
                }
            } else {
                // Case input wasn't XML (or wasn't in the right order)
                return '';
            }
        } else {
            // Case content-type wasn't MULTIPART
            return '';
        }
    }
}
