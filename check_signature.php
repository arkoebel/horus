<?php

require_once 'lib/horus_common.php';
require_once 'lib/horus_xml.php';
require_once 'lib/horus_exception.php';

$file = $argv[2];
$mode = $argv[1];
if ($argc>3) {
    $secret = $argv[3];
} else {
    $secret = 'secret';
}

if ('BHA'=== $mode) {
    $params = array(
        'signatureAlgorithm'=>'RSA_SHA256',
        'digestAlgorithm'=>'SHA256',
        'method'=>'DATAPDUSIG',
        'name'=>'BHA',
        'documentns' => array(
            'Saa' => 'urn:swift:saa:xsd:saa.2.0',
            'h' => 'urn:iso:std:iso:20022:tech:xsd:head.001.001.01',
            'd' => 'urn:iso:std:iso:20022:tech:xsd:pacs.008.001.08'),
        'destinationXPath'=>'/Saa:DataPDU/Saa:Body/h:AppHdr/h:Sgntr',
        'references'=>array(
          array('comment'=>'Key',
                  'xpath'=>'/Saa:DataPDU/Saa:Body/h:AppHdr/h:Sgntr/ds:Signature/ds:KeyInfo',
                  'sigxpath'=>'/ds:Signature/ds:SignedInfo/ds:Reference[starts-with(@URI,"#")]'
                ),
            array('comment'=>'AppHdr',
                    'xpath'=>'/Saa:DataPDU/Saa:Body/h:AppHdr',
                    'removeSignature'=>'true',
                    'sigxpath'=>'/ds:Signature/ds:SignedInfo/ds:Reference[@URI=""]'
                  ),
            array('comment'=>'Document',
                    'xpath'=>'/Saa:DataPDU/Saa:Body/*[name() = "Document"]',
                    'sigxpath'=>'/ds:Signature/ds:SignedInfo/ds:Reference[not(@URI)]'
                  )
             )
                );
            } else {
                $params = array(
                    'signatureAlgorithm'=>'SHA256',
                    'digestAlgorithm'=>'SHA256',
                    'method'=>'XMLDSIG',
                    'documentNSPrefix'=>'Saa',
                    'documentNSURI'=>'urn:swift:saa:xsd:saa.2.0',
                    'destinationXPath'=>'/Saa:DataPDU/Saa:LAU',
                    'key'=>$secret
                );
                
            }

            $input = file_get_contents($file);
            try {
                $res =  HorusXML::validateSignature(
                    $input,
                    null,
                    $params,
                    array('logLocation'=>'php://stdout', 'business_id'=>'123456')
                );
                echo "\n########## SIGNATURE CHECKED OK ##########\n";

            } catch (HorusException $e) {
                echo "\n########## SIGNATURE FAILED ##########\n";
                echo $e->getMessage();
            }

echo $argc;
