<?php

declare (strict_types = 1);

require_once 'lib/horus_http.php';
require_once 'lib/horus_common.php';
require_once 'lib/horus_xml.php';
require_once 'HorusTestCase.php';
require_once 'lib/horus_exception.php';
require_once 'vendor/autoload.php';
use RobRichards\XMLSecLibs\XMLSecurityDSig;

class HorusSignatureTest extends HorusTestCase
{

    public static $input1LAU = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
        '<Saa:DataPDU xmlns:Saa="urn:swift:saa:xsd:saa.2.0" xmlns:Sw="urn:swift:snl:ns.Sw" ' .
        'xmlns:SwInt="urn:swift:snl:ns.SwInt" xmlns:SwGbl="urn:swift:snl:ns.SwGbl" ' .
        'xmlns:SwSec="urn:swift:snl:ns.SwSec">' . "\n" .
        '    <Saa:Revision>2.0.6</Saa:Revision>' . "\n" .
        '    <Saa:Header>' . "\n" .
        '        <Saa:Message>' . "\n" .
        '            <Saa:SenderReference>Ref760FA1234</Saa:SenderReference>' . "\n" .
        '            <Saa:MessageIdentifier>tsrv.fin.mt7xx.gteesstandbys</Saa:MessageIdentifier>' . "\n" .
        '            <Saa:Format>File</Saa:Format>' . "\n" .
        '            <Saa:SubFormat>Input</Saa:SubFormat>' . "\n" .
        '            <Saa:Sender>' . "\n" .
        '                <Saa:DN>cn=su8,o=ptsqgbbb,o=swift</Saa:DN>' . "\n" .
        '                <Saa:FullName>' . "\n" .
        '                    <Saa:X1>PTSQGBBBXXX</Saa:X1>' . "\n" .
        '                </Saa:FullName>' . "\n" .
        '            </Saa:Sender>' . "\n" .
        '            <Saa:Receiver>' . "\n" .
        '                <Saa:DN>cn=abc,ou=saa,o=xxx,o=swift</Saa:DN>' . "\n" .
        '                <Saa:FullName>' . "\n" .
        '                    <Saa:X1>PTSXXXXXXXX</Saa:X1>' . "\n" .
        '                    <Saa:X2>saa</Saa:X2>' . "\n" .
        '                </Saa:FullName>' . "\n" .
        '            </Saa:Receiver>' . "\n" .
        '            <Saa:InterfaceInfo>' . "\n" .
        '                <Saa:UserReference>CRE...</Saa:UserReference>' . "\n" .
        '                <Saa:MessageCreator>ApplicationInterface</Saa:MessageCreator>' . "\n" .
        '                <Saa:MessageContext>Original</Saa:MessageContext>' . "\n" .
        '                <Saa:MessageNature>Financial</Saa:MessageNature>' . "\n" .
        '            </Saa:InterfaceInfo>' . "\n" .
        '            <Saa:NetworkInfo>' . "\n" .
        '                <Saa:Priority>Normal</Saa:Priority>' . "\n" .
        '                <Saa:IsPossibleDuplicate>true</Saa:IsPossibleDuplicate>' . "\n" .
        '                <Saa:Service>swift.corp.fast!x</Saa:Service>' . "\n" .
        '                <Saa:Network>Application</Saa:Network>' . "\n" .
        '                <Saa:SessionNr>0080</Saa:SessionNr>' . "\n" .
        '                <Saa:SeqNr>000001</Saa:SeqNr>' . "\n" .
        '                <Saa:SWIFTNetNetworkInfo>' . "\n" .
        '                    <Saa:RequestType>tsrv.fin.mt7xx.gteesstandbys</Saa:RequestType>' . "\n" .
        '                    <Saa:Reference>2f949999-d32e-49eb-9999-9a819b9b9c0d</Saa:Reference>' . "\n" .
        '                    <Saa:FileInfo>SwCompression=Zip</Saa:FileInfo>' . "\n" .
        '                </Saa:SWIFTNetNetworkInfo>' . "\n" .
        '            </Saa:NetworkInfo>' . "\n" .
        '            <Saa:SecurityInfo>' . "\n" .
        '                <Saa:SWIFTNetSecurityInfo>' . "\n" .
        '                    <Saa:FileDigestAlgorithm>SHA-256</Saa:FileDigestAlgorithm>' . "\n" .
        '                    <Saa:FileDigestValue>9tnnjIgsowPSU+ehm8Rb0J5TvZIvhCYnySzFkpur1aw=' .
        '</Saa:FileDigestValue>' . "\n" .
        '                </Saa:SWIFTNetSecurityInfo>' . "\n" .
        '            </Saa:SecurityInfo>' . "\n" .
        '            <Saa:FileLogicalName>Payload.ZIP</Saa:FileLogicalName>' . "\n" .
        '            <Saa:ExpiryDateTime>20210712074808</Saa:ExpiryDateTime>' . "\n" .
        '        </Saa:Message>' . "\n" .
        '    </Saa:Header>' . "\n" .
        '    <Saa:Body>Payload.ZIP</Saa:Body>' . "\n" .
        '   <Saa:LAU>' . "\n" .
        "\t\t" . '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . "\n" .
        "\t\t\t" . '<ds:SignedInfo>' . "\n" .
        "\t\t\t\t" . '<ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xmlexc-c14n#" />' . "\n" .
        "\t\t\t\t" . '<ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsigmore#hmac-sha256" />' . "\n" .
        "\t\t\t\t" . '<ds:Reference URI="">' . "\n" .
        "\t\t\t\t\t" . '<ds:Transforms>' . "\n" .
        "\t\t\t\t\t\t" . '<ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature" />' . "\n" .
        "\t\t\t\t\t\t" . '<ds:Transform Algorithm="http://www.w3.org/2001/10/xml-excc14n#"/>' . "\n" .
        "\t\t\t\t\t" . '</ds:Transforms>' . "\n" .
        "\t\t\t\t\t" . '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256" />' . "\n" .
        "\t\t\t\t\t" . '<ds:DigestValue>d793Xkjuzq7vkT38tu6EPt3vQj2XQL1QbzF7tmHMMg4=</ds:DigestValue>' . "\n" .
        "\t\t\t\t" . '</ds:Reference>' . "\n" .
        "\t\t\t" . '</ds:SignedInfo>' . "\n" .
        "\t\t\t" . '<ds:SignatureValue>zDVHg3NDF8yRpPgpEGUfxYoWeq8QChbC0bnfJ9tIsnU=</ds:SignatureValue>' . "\n" .
        "\t\t" . '</ds:Signature>' . "\n" .
        '   </Saa:LAU>' . "\n" .
        '</Saa:DataPDU>';

    public function testSample(): void
    {

        $input = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<Saa:DataPDU xmlns:Saa="urn:swift:saa:xsd:saa.2.0" xmlns:Sw="urn:swift:snl:ns.Sw" ' .
            'xmlns:SwInt="urn:swift:snl:ns.SwInt" xmlns:SwGbl="urn:swift:snl:ns.SwGbl" ' .
            'xmlns:SwSec="urn:swift:snl:ns.SwSec">' . "\n" .
            '    <Saa:Revision>2.0.6</Saa:Revision>' . "\n" .
            '    <Saa:Header>' . "\n" .
            '        <Saa:Message>' . "\n" .
            '            <Saa:SenderReference>Ref760FA1234</Saa:SenderReference>' . "\n" .
            '            <Saa:MessageIdentifier>tsrv.fin.mt7xx.gteesstandbys</Saa:MessageIdentifier>' . "\n" .
            '            <Saa:Format>File</Saa:Format>' . "\n" .
            '            <Saa:SubFormat>Input</Saa:SubFormat>' . "\n" .
            '            <Saa:Sender>' . "\n" .
            '                <Saa:DN>cn=su8,o=ptsqgbbb,o=swift</Saa:DN>' . "\n" .
            '                <Saa:FullName>' . "\n" .
            '                    <Saa:X1>PTSQGBBBXXX</Saa:X1>' . "\n" .
            '                </Saa:FullName>' . "\n" .
            '            </Saa:Sender>' . "\n" .
            '            <Saa:Receiver>' . "\n" .
            '                <Saa:DN>cn=abc,ou=saa,o=xxx,o=swift</Saa:DN>' . "\n" .
            '                <Saa:FullName>' . "\n" .
            '                    <Saa:X1>PTSXXXXXXXX</Saa:X1>' . "\n" .
            '                    <Saa:X2>saa</Saa:X2>' . "\n" .
            '                </Saa:FullName>' . "\n" .
            '            </Saa:Receiver>' . "\n" .
            '            <Saa:InterfaceInfo>' . "\n" .
            '                <Saa:UserReference>CRE...</Saa:UserReference>' . "\n" .
            '                <Saa:MessageCreator>ApplicationInterface</Saa:MessageCreator>' . "\n" .
            '                <Saa:MessageContext>Original</Saa:MessageContext>' . "\n" .
            '                <Saa:MessageNature>Financial</Saa:MessageNature>' . "\n" .
            '            </Saa:InterfaceInfo>' . "\n" .
            '            <Saa:NetworkInfo>' . "\n" .
            '                <Saa:Priority>Normal</Saa:Priority>' . "\n" .
            '                <Saa:IsPossibleDuplicate>true</Saa:IsPossibleDuplicate>' . "\n" .
            '                <Saa:Service>swift.corp.fast!x</Saa:Service>' . "\n" .
            '                <Saa:Network>Application</Saa:Network>' . "\n" .
            '                <Saa:SessionNr>0080</Saa:SessionNr>' . "\n" .
            '                <Saa:SeqNr>000001</Saa:SeqNr>' . "\n" .
            '                <Saa:SWIFTNetNetworkInfo>' . "\n" .
            '                    <Saa:RequestType>tsrv.fin.mt7xx.gteesstandbys</Saa:RequestType>' . "\n" .
            '                    <Saa:Reference>2f949999-d32e-49eb-9999-9a819b9b9c0d</Saa:Reference>' . "\n" .
            '                    <Saa:FileInfo>SwCompression=Zip</Saa:FileInfo>' . "\n" .
            '                </Saa:SWIFTNetNetworkInfo>' . "\n" .
            '            </Saa:NetworkInfo>' . "\n" .
            '            <Saa:SecurityInfo>' . "\n" .
            '                <Saa:SWIFTNetSecurityInfo>' . "\n" .
            '                    <Saa:FileDigestAlgorithm>SHA-256</Saa:FileDigestAlgorithm>' . "\n" .
            '                    <Saa:FileDigestValue>9tnnjIgsowPSU+ehm8Rb0J5TvZIvhCYnySzFkpur1aw=' .
            '</Saa:FileDigestValue>' . "\n" .
            '                </Saa:SWIFTNetSecurityInfo>' . "\n" .
            '            </Saa:SecurityInfo>' . "\n" .
            '            <Saa:FileLogicalName>Payload.ZIP</Saa:FileLogicalName>' . "\n" .
            '            <Saa:ExpiryDateTime>20210712074808</Saa:ExpiryDateTime>' . "\n" .
            '        </Saa:Message>' . "\n" .
            '    </Saa:Header>' . "\n" .
            '    <Saa:Body>Payload.ZIP</Saa:Body>' . "\n" .
            '   <Saa:LAU>' . "\n" .
            "\t\t" . '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . "\n" .
            "\t\t\t" . '<ds:SignedInfo>' . "\n" .
            "\t\t\t\t" . '<ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xmlexc-c14n#" />' . "\n" .
            "\t\t\t\t" . '<ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsigmore#hmac-sha256" />' . "\n" .
            "\t\t\t\t" . '<ds:Reference URI="">' . "\n" .
            "\t\t\t\t\t" . '<ds:Transforms>' . "\n" .
            "\t\t\t\t\t\t" . '<ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature" />' .
            "\n" .
            "\t\t\t\t\t\t" . '<ds:Transform Algorithm="http://www.w3.org/2001/10/xml-excc14n#"/>' . "\n" .
            "\t\t\t\t\t" . '</ds:Transforms>' . "\n" .
            "\t\t\t\t\t" . '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256" />' . "\n" .
            "\t\t\t\t\t" . '<ds:DigestValue>d793Xkjuzq7vkT38tu6EPt3vQj2XQL1QbzF7tmHMMg4=</ds:DigestValue>' . "\n" .
            "\t\t\t\t" . '</ds:Reference>' . "\n" .
            "\t\t\t" . '</ds:SignedInfo>' . "\n" .
            "\t\t\t" . '<ds:SignatureValue>zDVHg3NDF8yRpPgpEGUfxYoWeq8QChbC0bnfJ9tIsnU=</ds:SignatureValue>' . "\n" .
            "\t\t" . '</ds:Signature>' . "\n" .
            '   </Saa:LAU>' . "\n" .
            '</Saa:DataPDU>';

        HorusXML::validateSignature(
            $input,
            null,
            array(
                'signatureAlgorithm' => 'SHA256',
                'digestAlgorithm' => 'SHA256',
                'method' => 'XMLDSIG',
                'documentNSPrefix' => 'Saa',
                'documentNSURI' => 'urn:swift:saa:xsd:saa.2.0',
                'destinationXPath' => '/Saa:DataPDU/Saa:LAU',
                'key' => 'Abcd1234abcd1234Abcd1234abcd1234',
            ),
            null
        );
    }

    public function testSample2(): void
    {

        $input = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<Saa:DataPDU xmlns:Saa="urn:swift:saa:xsd:saa.2.0"' . "\n" .
            'xmlns:Sw="urn:swift:snl:ns.Sw" xmlns:SwGbl="urn:swift:snl:ns.SwGbl"' . "\n" .
            'xmlns:SwInt="urn:swift:snl:ns.SwInt" xmlns:SwSec="urn:swift:snl:ns.SwSec">' . "\n" .
            "\t" . '<Saa:Revision>2.0.9</Saa:Revision>' . "\n" .
            "\t" . '<Saa:Header>' . "\n" .
            "\t\t" . '<Saa:Message>' . "\n" .
            "\t\t\t" . '<Saa:SenderReference>f70530bb1633fe8863b20001000001</Saa:SenderReference>' . "\n" .
            "\t\t\t" . '<Saa:MessageIdentifier>camt.998.001.02</Saa:MessageIdentifier>' . "\n" .
            "\t\t\t\t" . '<Saa:Format>AnyXML</Saa:Format>' . "\n" .
            "\t\t\t" . '<Saa:SubFormat>Input</Saa:SubFormat>' . "\n" .
            "\t\t\t" . '<Saa:Sender>' . "\n" .
            "\t\t\t\t" . '<Saa:DN>o=swhqbebb,o=swift</Saa:DN>' . "\n" .
            "\t\t\t\t" . '<Saa:FullName>' . "\n" .
            "\t\t\t\t\t" . '<Saa:X1>SWHQBEBBXXX</Saa:X1>' . "\n" .
            "\t\t\t\t" . '</Saa:FullName>' . "\n" .
            "\t\t\t" . '</Saa:Sender>' . "\n" .
            "\t\t\t" . '<Saa:Receiver>' . "\n" .
            "\t\t\t\t" . '<Saa:DN>o=swhqbebb,o=swift</Saa:DN>' . "\n" .
            "\t\t\t\t" . '<Saa:FullName>' . "\n" .
            "\t\t\t\t\t" . '<Saa:X1>SWHQBEBBXXX</Saa:X1>' . "\n" .
            "\t\t\t\t" . '</Saa:FullName>' . "\n" .
            "\t\t\t" . '</Saa:Receiver>' . "\n" .
            "\t\t\t" . '<Saa:InterfaceInfo>' . "\n" .
            "\t\t\t\t" . '<Saa:UserReference>f70530bb1633fe8863b20001000001</Saa:UserReference>' . "\n" .
            "\t\t\t\t" . '<Saa:MessageCreator>ApplicationInterface</Saa:MessageCreator>' . "\n" .
            "\t\t\t\t" . '<Saa:MessageContext>Original</Saa:MessageContext>' . "\n" .
            "\t\t\t\t" . '<Saa:MessageNature>Financial</Saa:MessageNature>' . "\n" .
            "\t\t\t" . '</Saa:InterfaceInfo>' . "\n" .
            "\t\t\t" . '<Saa:NetworkInfo>' . "\n" .
            "\t\t\t\t" . '<Saa:Priority>Normal</Saa:Priority>' . "\n" .
            "\t\t\t\t" . '<Saa:IsPossibleDuplicate>false</Saa:IsPossibleDuplicate>' . "\n" .
            "\t\t\t\t" . '<Saa:IsNotificationRequested>false</Saa:IsNotificationRequested>' . "\n" .
            "\t\t\t\t" . '<Saa:Service>swift.eni</Saa:Service>' . "\n" .
            "\t\t\t\t" . '<Saa:Network>Application</Saa:Network>' . "\n" .
            "\t\t\t\t" . '<Saa:SessionNr>0016</Saa:SessionNr>' . "\n" .
            "\t\t\t\t" . '<Saa:SeqNr>000001</Saa:SeqNr>' . "\n" .
            "\t\t\t\t" . '<Saa:SWIFTNetNetworkInfo>' . "\n" .
            "\t\t\t\t\t" . '<Saa:RequestType>camt.998.001.02</Saa:RequestType>' . "\n" .
            "\t\t\t\t\t" . '<Saa:Reference>4749da88-25a3-11e7-9c45-4128ba5efce6</Saa:Reference>' . "\n" .
            "\t\t\t\t\t" . '<Saa:IsCopyRequested>false</Saa:IsCopyRequested>' . "\n" .
            "\t\t\t\t" . '</Saa:SWIFTNetNetworkInfo>' . "\n" .
            "\t\t\t" . '</Saa:NetworkInfo>' . "\n" .
            "\t\t\t" . '<Saa:ExpiryDateTime>20170510082806</Saa:ExpiryDateTime>' . "\n" .
            "\t\t" . '</Saa:Message>' . "\n" .
            "\t" . '</Saa:Header>' . "\n" .
            "\t" . '<Saa:Body>' . "\n" .
            "\t\t" . '<Document xmlns="urn:swift:xsd:camt.998.001.02" xmlns:xsi="http://www.w3.org/' .
            '2001/XMLSchema-instance">' . "\n" .
            "\t\t\t" . '<PrtryMsg>' . "\n" .
            "\t\t\t\t" . '<MsgId>' . "\n" .
            "\t\t\t\t\t" . '<Ref>ABCDEFGHIJKLMNOPQRST123456789012345</Ref>' . "\n" .
            "\t\t\t\t" . '</MsgId>' . "\n" .
            "\t\t\t\t" . '<Rltd>' . "\n" .
            "\t\t\t\t\t" . '<Ref>ABCDEFGHIJKLMNOPQRST123456789012345</Ref>' . "\n" .
            "\t\t\t\t" . '</Rltd>' . "\n" .
            "\t\t\t\t" . '<Prvs>' . "\n" .
            "\t\t\t\t\t" . '<Ref>ABCDEFGHIJKLMNOPQRST123456789012345</Ref>' . "\n" .
            "\t\t\t\t" . '</Prvs>' . "\n" .
            "\t\t\t\t" . '<Othr>' . "\n" .
            "\t\t\t\t\t" . '<Ref>ABCDEFGHIJKLMNOPQRST123456789012345</Ref>' . "\n" .
            "\t\t\t\t" . '</Othr>' . "\n" .
            "\t\t\t\t" . '<PrtryData>' . "\n" .
            "\t\t\t\t" . '<Tp>ABCDEFGHIJKLMNOPQRST123456789012345</Tp>' . "\n" .
            "\t\t\t\t\t" . '<Cntnt>:20:MyReference' . "\n" .
            ':32A:20051212' . "\n" .
            ':57:BKNY' . "\n" .
            ':72:/Own</Cntnt>' . "\n" .
            "\t\t\t\t" . '</PrtryData>' . "\n" .
            "\t\t\t" . '</PrtryMsg>' . "\n" .
            "\t\t" . '</Document>' . "\n" .
            "\t" . '</Saa:Body>' . "\n" .
            "\t" . '<Saa:LAU>' . "\n" .
            "\t\t" . '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . "\n" .
            "\t\t\t" . '<ds:SignedInfo>' . "\n" .
            "\t\t\t\t" . '<ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xmlexc-c14n#" />' . "\n" .
            "\t\t\t\t" . '<ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsigmore#hmac-sha256" />' . "\n" .
            "\t\t\t\t" . '<ds:Reference URI="">' . "\n" .
            "\t\t\t\t\t" . '<ds:Transforms>' . "\n" .
            "\t\t\t\t\t\t" . '<ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig' .
            '#enveloped-signature" />' . "\n" .
            "\t\t\t\t\t\t" . '<ds:Transform Algorithm="http://www.w3.org/2001/10/xml-excc14n#"/>' . "\n" .
            "\t\t\t\t\t" . '</ds:Transforms>' . "\n" .
            "\t\t\t\t\t" . '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256" />' . "\n" .
            "\t\t\t\t\t" . '<ds:DigestValue>K6NH7o+dj/D5N2tqhQelvR2aFrZlyZWySusZhS+1Mu8=</ds:DigestValue>' . "\n" .
            "\t\t\t\t" . '</ds:Reference>' . "\n" .
            "\t\t\t" . '</ds:SignedInfo>' . "\n" .
            "\t\t\t" . '<ds:SignatureValue>/+v2SH3w593Jvok7WPB2A9JP8njZk6uPooj7fYz705k=</ds:SignatureValue>' . "\n" .
            "\t\t" . '</ds:Signature>' . "\n" .
            "\t" . '</Saa:LAU>' . "\n" .
            '</Saa:DataPDU>';

        file_put_contents('sample.xml', $input);
        HorusXML::validateSignature(
            $input,
            null,
            array(
                'signatureAlgorithm' => 'SHA256',
                'digestAlgorithm' => 'SHA256',
                'method' => 'XMLDSIG',
                'documentNSPrefix' => 'Saa',
                'documentNSURI' => 'urn:swift:saa:xsd:saa.2.0',
                'destinationXPath' => '/Saa:DataPDU/Saa:LAU',
                'key' => 'Abcd1234abcd1234Abcd1234abcd1234'),
            array('business_id' => '122345')
          );
    }

    public function test3(): void
    {

        $input = '<?xml version="1.0" encoding="UTF-8"?><Envelope xmlns="http://example.org/envelope">' .
        '<Body>Olá mundo</Body><Signature xmlns="http://www.w3.org/2000/09/xmldsig#">' .
        '<SignedInfo><CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315" />' .
        '<SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1" /><Reference URI="">' .
        '<Transforms><Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature" />' .
        '</Transforms><DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1" />' .
        '<DigestValue>AzgXlUQAvdSKPKnHlP4O8S0kvro=</DigestValue></Reference></SignedInfo>' .
        '<SignatureValue>E8GWQYMa9spOyrLxQR/tXLdRcHbteI1RgwgO6owGJkyYh+zAqD93Ndiw7g7pu0DHWXsgSyYY6+' .
        'UBcgBe6YQAJKp+Xx1/WQK409HnRk8d/0SlBlaxiBBxjjXxrT9IJJge95cUJH/e1RR4DC4S62GvloRK9xzHUlSfEfXUvzK' .
        'nlfY=</SignatureValue><KeyInfo><KeyValue><RSAKeyValue><Modulus>4IlzOY3Y9fXoh3Y5f06wBbtTg94Pt6' .
        'vcfcd1KQ0FLm0S36aGJtTSb6pYKfyX7PqCUQ8wgL6xUJ5GRPEsu9gyz8ZobwfZsGCsvu40CWoT9fcFBZPfXro1Vtlh/xl' .
        '/yYHm+Gzqh0Bw76xtLHSfLfpVOrmZdwKmSFKMTvNXOFd0V18=</Modulus><Exponent>AQAB</Exponent></RSAKeyValue>' .
        '</KeyValue></KeyInfo></Signature></Envelope>';
        $xml = new DOMDocument();
        $xml->loadXML($input);
        $sign = $xml->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature')->item(0);
        $sign->parentNode->removeChild($sign);
        $canon = $xml->C14N(true, false, null, array(''));
        error_log($canon);
        $digest = base64_encode(openssl_digest($canon, 'sha1', true));
        error_log($digest);
        $ss = HorusXML::getSignatureFragment(array(""), array($digest), 'XXXX');
        $new = new DOMDocument();
        $new->loadXML($ss);
        $signedInfo = $new->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'SignedInfo')->item(0);
        $samp = '<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">
      <CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"></CanonicalizationMethod>
      <SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></SignatureMethod>
      <Reference URI="">
        <Transforms>
          <Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"></Transform>
        </Transforms>
        <DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></DigestMethod>
        <DigestValue>UWuYTYug10J1k5hKfonxthgrAR8=</DigestValue>
      </Reference>
    </SignedInfo>';
        $new3 = new DOMDocument();
        $new3->preserveWhiteSpace = true;
        $new3->formatOutput = false;
        $new3->loadXML($samp);
        $canon3 = $new3->C14N(true, false, null, array('ds'));
        $canon2 = $signedInfo->C14N(true, false, null, array('ds'));
        error_log($canon3);
        $key = '-----BEGIN ENCRYPTED PRIVATE KEY-----
MIICojAcBgoqhkiG9w0BDAEDMA4ECFleZ90vhGrRAgIEAASCAoA9rti16XVH
K4AJVe1CNf61NIpIogu/Xs4Yn4hXflvewiOwe6/9FkxBXLbhKdbQWn1Z4p3C
njVns2VYEO/qpJR3LciHMwp5dsqedUVVia//CqFHtEV9WfvCKWgmlkkT1YEm
1aChZnPP5i6IhwVT9qvFluTZhvVmjW0YyF86OrOp0uxxVic7phPbnPrOMelf
ZPc3A3EGpzDPkxN+o0obw87tUgCL+s0KtUOr3c6Si4KQ3IQjrjZxQF4Se3t/
4PEpqUl5EpYiCx9q5uqb0Lr1kWiiQ5/inZm5ETc+qO+ENcp0KjnX523CATYd
U5iOjl/X9XZeJrMpOCXogEuhmLPRauYP1HEWnAY/hLW93v10QJXY6ALlbkL0
sd5WU8Ces7T04b/p4/12yxqYqV68QePyfHpegdraDq3vRfopSwrUxtL9cisP
jsQcJ5FL/SfloFbmld4CKIjMsromsEWqo6rfo3JqNizgTVIIWExy3jDT9VvK
d9ADH0g3JCbuFzaWVOZMmZ0wlo28PKkLQ8FkW8CG/Lq/Q/bHLPM+sPdLN+ke
gpA6fvL4wpku4ST7hmeN1vWbRLlCfuFijux77hdM7knO9/MawICsA4XdzR78
p0C2hJlc6p46IWZaINQXGstTbJMh+mJ7i1lrbG2kvZ2Twf9R+RaLp2mPHjb1
+P+3f2L3tOoC31oJ18u/L1MXEWxLEZHB0+ANg+N/0/icwImcI0D+wVN2puU4
m58j81sGZUEAB3aFEbPxoX3y+qYlOnt1OfdY7WnNdyr9ZzI09fkrTvujF4LU
nycqE+MXerf0PxkNu1qv9bQvCoH8x3J2EVdMxPBtH1Fb7SbE66cNyh//qzZo
B9Je
-----END ENCRYPTED PRIVATE KEY-----';
        $sign = 'XXX';
        $private = openssl_pkey_get_private($key, 'password');
        $test = openssl_sign($canon3, $sign, $private, 'RSA-SHA1');
        error_log("result $test \n");
        $this::assertEquals(
            'TSQUoVrQ0kg1eiltNwIhKPrIdsi1VhWjYNJlXvfQqW2EKk3X37X862SCfrz7v8IYJ7Oo' .
            'rWwlFpGDStJDSR6saOScqSvmesCrGEEq+U6zegR9nH0lvcGZ8Rvc/y7U9kZrE4fHqEiL' .
            'yfpmzJyPmWUT9Uta14nPJYsl3cmdThHB8Bs=',
            base64_encode($sign)
        );
        $this::assertEquals('AzgXlUQAvdSKPKnHlP4O8S0kvro=', $digest);
    }

    public function stest4(): void
    {
        $input = '<?xml version="1.0" encoding="UTF-8"?><Envelope xmlns="http://example.org/envelope">' .
          '<Body>Olá mundo</Body><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo>' .
          '<CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315" />' .
          '<SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1" /><Reference URI="">' .
          '<Transforms><Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature" />' .
          '</Transforms><DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1" />' .
          '<DigestValue>AzgXlUQAvdSKPKnHlP4O8S0kvro=</DigestValue></Reference></SignedInfo>' .
          '<SignatureValue>E8GWQYMa9spOyrLxQR/tXLdRcHbteI1RgwgO6owGJkyYh+zAqD93Ndiw7g7pu0DH' .
          'WXsgSyYY6+UBcgBe6YQAJKp+Xx1/WQK409HnRk8d/0SlBlaxiBBxjjXxrT9IJJge95cUJH/e1RR4DC4S' .
          '62GvloRK9xzHUlSfEfXUvzKnlfY=</SignatureValue><KeyInfo><KeyValue><RSAKeyValue>' .
          '<Modulus>4IlzOY3Y9fXoh3Y5f06wBbtTg94Pt6vcfcd1KQ0FLm0S36aGJtTSb6pYKfyX7PqCUQ8wgL' .
          '6xUJ5GRPEsu9gyz8ZobwfZsGCsvu40CWoT9fcFBZPfXro1Vtlh/xl/yYHm+Gzqh0Bw76xtLHSfLfpVO' .
          'rmZdwKmSFKMTvNXOFd0V18=</Modulus><Exponent>AQAB</Exponent></RSAKeyValue></KeyValue>' .
          '</KeyInfo></Signature></Envelope>';
        $params = array('method' => 'XMLDSIG', 'key' => '-----BEGIN ENCRYPTED PRIVATE KEY-----
MIICojAcBgoqhkiG9w0BDAEDMA4ECFleZ90vhGrRAgIEAASCAoA9rti16XVH
K4AJVe1CNf61NIpIogu/Xs4Yn4hXflvewiOwe6/9FkxBXLbhKdbQWn1Z4p3C
njVns2VYEO/qpJR3LciHMwp5dsqedUVVia//CqFHtEV9WfvCKWgmlkkT1YEm
1aChZnPP5i6IhwVT9qvFluTZhvVmjW0YyF86OrOp0uxxVic7phPbnPrOMelf
ZPc3A3EGpzDPkxN+o0obw87tUgCL+s0KtUOr3c6Si4KQ3IQjrjZxQF4Se3t/
4PEpqUl5EpYiCx9q5uqb0Lr1kWiiQ5/inZm5ETc+qO+ENcp0KjnX523CATYd
U5iOjl/X9XZeJrMpOCXogEuhmLPRauYP1HEWnAY/hLW93v10QJXY6ALlbkL0
sd5WU8Ces7T04b/p4/12yxqYqV68QePyfHpegdraDq3vRfopSwrUxtL9cisP
jsQcJ5FL/SfloFbmld4CKIjMsromsEWqo6rfo3JqNizgTVIIWExy3jDT9VvK
d9ADH0g3JCbuFzaWVOZMmZ0wlo28PKkLQ8FkW8CG/Lq/Q/bHLPM+sPdLN+ke
gpA6fvL4wpku4ST7hmeN1vWbRLlCfuFijux77hdM7knO9/MawICsA4XdzR78
p0C2hJlc6p46IWZaINQXGstTbJMh+mJ7i1lrbG2kvZ2Twf9R+RaLp2mPHjb1
+P+3f2L3tOoC31oJ18u/L1MXEWxLEZHB0+ANg+N/0/icwImcI0D+wVN2puU4
m58j81sGZUEAB3aFEbPxoX3y+qYlOnt1OfdY7WnNdyr9ZzI09fkrTvujF4LU
nycqE+MXerf0PxkNu1qv9bQvCoH8x3J2EVdMxPBtH1Fb7SbE66cNyh//qzZo
B9Je
-----END ENCRYPTED PRIVATE KEY-----',
            'passphrase' => 'password', 'digestAlgorithm' => 'SHA1', 'signatureAlgorithm' => 'RSA-SHA1',
            'documentNSPrefix' => 'a',
            'documentNSURI' => 'http://example.org/envelope',
            'destinationXPath' => '/a:Envelope',
        );
        HorusXML::validateSignature($input, null, $params, null);
    }

    public function test5(): void
    {
        $cert = "-----BEGIN CERTIFICATE-----\n" . 'MIIE3DCCAsSgAwIBAgIEYGl6AjANBgkqhkiG9w0BAQsFADAQMQ4w' .
        'DAYDVQQKEwVTV0lGVDAeFw0yMTA1MjAwNjQ4MjFaFw0yMzA1MjAwNzE4MjFaMEExDjAMBgNVBAoTBXN3aWZ0MREwDwYDVQ' .
        'QKEwhzd2h0YmViMzENMAsGA1UECxMEdHN0MjENMAsGA1UEAxMEcnRnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoC' .
        'ggEBALcuSaNaWz6UxbdbcxHH71MkD8TmE5Mm+X+Q9ArQCHYEC1RO2tOAkfJtVjtwSBFraHrw7ax3QxguTFeQQwRgRGIs8j' .
        'L9bGXq7HPM6eiA40vwC/JE0s4sEf5SJj12EHP+EOwWbN4eyrAWAjePAGTCIesyUk2rCHddO3SS3VYrOoe4Kx2u4s/d2n3b' .
        'aU+RzV5obs2iL/cBdMdZab+Aynrch7gU+ZGXKZkyvTALX4vLfDxvgO2rbiwOcoW39Jd/ySaMDWJQGobA/ptGGNVf9pWqYe' .
        'MpFNioMFJWDPWH9v0XvDOe7MAeLU9bo7c+IYUptGgTByEtPV6v3eDvxHrp3Kkgy8ECAwEAAaOCAQswggEHMAsGA1UdDwQE' .
        'AwIGwDARBgNVHSAECjAIMAYGBCsVBgIwGwYDVR0JBBQwEjAQBgkqhkiG9n0HRB0xAwIBDzA1BgNVHR8ELjAsMCqgKKAmpC' .
        'QwIjEOMAwGA1UEChMFU1dJRlQxEDAOBgNVBAMTB0NSTDQyOTkwKwYDVR0QBCQwIoAPMjAyMTA1MjAwNjQ4MjFagQ8yMDIy' .
        'MTExODE5MTgyMVowHwYDVR0jBBgwFoAUUqMBKGDZlSdB4hCeCZ+rg9DxlkIwHQYDVR0OBBYEFGNS/RRl0xdB0eFnysKtVf' .
        '5/algPMAkGA1UdEwQCMAAwGQYJKoZIhvZ9B0EABAwwChsEVjguMgMCBLAwDQYJKoZIhvcNAQELBQADggIBAK7YYCXsc37L' .
        'USpEj4+6HZ1DWo5xxwIQLwUDAnqDdOWgwsS+Ovj1gfjjfiWkKTZCUBjc7H8TuKlaqdyAzvVJ+CW/eV2YgfBZq6sWIYOJIe' .
        'yNGY6lYcIg0Qy6dB4CPCxFN6IpsBkG6b8MPfpXBk00B3HizkY6JL6ehN3brui9Mml+GlnM6/rBTX/T6krUTIcM3NNr1Iwo' .
        'BGlRicBo1PyxwO40NdYtiBZc7W4K1p91CKGzl3jYF0qofFRN8T30PXcRXNcvi4MwhjFDbGXkWQdKJrrMcOPwXs/c6ic7z7' .
        '/PSOyBOs5U/UPvMkkZ5egH517/Zm4s7IhdoX3YNeFNRZ5DfD6udFG6Rox5F70uywVLt03GtI3uzHuVirrBhs78RdhV7XL6' .
        'Yt31WZrwYycwE7JP59H0+gbjciAysTGw1vtA9H6twmTCpqEyCYwJzFjb+AbILcIhDrJfFchlYy7zMNFQp3Ds99D0y9Lgzp' .
        'MFRuaAwz0pD5rJQJ0sckv6GKzUoUMZXWXSoyR2FV0wSC/gsVOKjKqVvQxUh+5UGAFpHYi3X0U7qu+EHBSBM/6pQHAqLyZH' .
        'rsUXppB5dofln7vtTDPCs7AbfojkeeqOl4v0oljVFwxv5p3No/uV2UKIWBllkLbT0rEwX9dY0yN1DeRdBZ47nhCJ66yAHC' .
        'mw9eChOH5X5VTT' . "\n-----END CERTIFICATE-----\n";
        $key = openssl_pkey_get_public($cert);
        $input = file_get_contents('samples/exampleFromOutside.xml');
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = true;
        $xml->formatOutput = false;
        $xml->loadXML($input);
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('ds', HorusXML::XMLDSIGNS);

        $namespaces = array(
            'Saa' => '',
            'h' => 'urn:iso:std:iso:20022:tech:xsd:head.001.001.01',
            'ds' => HorusXML::XMLDSIGNS,
            'u' => 'urn:iso:std:iso:20022:tech:xsd:pacs.009.001.08'
        );
        $digest1 = HorusXML::calculateDigestPart($input, '/Saa:DataPDU/Saa:Body/h:AppHdr', 'sha256', $namespaces, true);
        $digest2 = HorusXML::calculateDigestPart(
            $input,
            '/Saa:DataPDU/Saa:Body/u:Document',
            'sha256',
            $namespaces,
            false
        );
        $digest3 = HorusXML::calculateDigestPart(
            $input,
            '/Saa:DataPDU/Saa:Body/h:AppHdr/h:Sgntr/ds:Signature/ds:KeyInfo',
            'sha256',
            $namespaces,
            false
        );

        error_log('Digests : ' . $digest1 . '/' . $digest2 . '/' . $digest3);

        $ss = HorusXml::validateSignedInfoSignature($input, '//ds:Signature', '//ds:X509Certificate');
        
        $this::assertEquals('EmY7SKQtbB+x98wIxPs4OYPxK1428Mi4jIrBD55AUzs=', $digest1);
        $this::assertEquals('O4rpwReHDnneKO/2JToJOnTGJAdYxQC1IublevKcbbk=', $digest2);
        $this::assertEquals('YKL/CYQi8tJsablKmYeqdq/q0nBpxBJihnayvpDEHb8=', $digest3);

    }

    public function test6(): void
    {
        $input = file_get_contents('samples/xmldsig.xml');

        HorusXML::validateSignature(
            $input,
            null,
            array(
                'signatureAlgorithm' => 'SHA256',
                'digestAlgorithm' => 'SHA256',
                'method' => 'XMLDSIG',
                'documentNSPrefix' => 'Saa',
                'documentNSURI' => 'urn:swift:saa:xsd:saa.2.0',
                'destinationXPath' => '/Saa:DataPDU/Saa:LAU',
                'key' => 'secret'),
            array('business_id' => '122345')
          );

    }

    public function test7(): void
    {
        $input = HorusSignatureTest::$input1LAU;
        $dsig = new XMLSecurityDSig('ds');
        $dom = new DOMDocument();
        $dom->loadXML($input);
        $objDSig = $dsig->locateSignature($dom);

        if (!$objDSig) {
            throw new Exception("Cannot locate Signature Node");
        }
        $dsig->canonicalizeSignedInfo();

        try {
            $dsig->validateReference();
            echo "Reference Validation Succeeded\n";
            $this::assertTrue(true);
        } catch (Exception $e) {
            echo "Reference Validation Failed : " . $e->getMessage() . "\n";
        }
    }

    public function test8(): void
    {
        $input = file_get_contents('samples/exempleDAAOut.xml');
        $sign = "\t" . '<LAU>' . "\n" .
            "\t\t" . '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . "\n" .
            "\t\t\t" . '<ds:SignedInfo>' . "\n" .
            "\t\t\t\t" . '<ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#" />' . "\n" .
            "\t\t\t\t" . '<ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/' .
            'xmldsig-more#hmac-sha256" />' . "\n" .
            "\t\t\t\t" . '<ds:Reference URI="">' . "\n" .
            "\t\t\t\t\t" . '<ds:Transforms>' . "\n" .
            "\t\t\t\t\t\t" . '<ds:Transform Algorithm="http://www.w3.org/2000/09/' .
            'xmldsig#enveloped-signature" />' . "\n" .
            "\t\t\t\t\t\t" . '<ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>' . "\n" .
            "\t\t\t\t\t" . '</ds:Transforms>' . "\n" .
            "\t\t\t\t\t" . '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256" />' . "\n" .
            "\t\t\t\t\t" . '<ds:DigestValue>WMeBJ8rRAgx3y1Q3NUKBz7CP+tM7fFtcn4BgOE4A5Pw=</ds:DigestValue>' . "\n" .
            "\t\t\t\t" . '</ds:Reference>' . "\n" .
            "\t\t\t" . '</ds:SignedInfo>' . "\n" .
            "\t\t\t" . '<ds:SignatureValue>NuHSwllRR5E1BSKU2qNGEQGOQrYFQ10zgN4cTcBDYzc=</ds:SignatureValue>' . "\n" .
            "\t\t" . '</ds:Signature>' . "\n" .
            "\t" . '</LAU>' . "\n" . '</DataPDU>';

        $xml = str_replace('</DataPDU>', $sign, $input);
        file_put_contents('sample2.xml', $xml);
        HorusXML::validateSignature(
            $xml,
            null,
            array(
                'signatureAlgorithm' => 'SHA256',
                'digestAlgorithm' => 'SHA256',
                'method' => 'XMLDSIG',
                'documentNSPrefix' => 'Saa',
                'documentNSURI' => 'urn:swift:saa:xsd:saa.2.0',
                'destinationXPath' => '/Saa:DataPDU/Saa:LAU',
                'key' => 'secret'),
            array('business_id' => '122345')
          );
    }

    public function test9(): void
    {
        $input = file_get_contents('samples/real_sample2.xml');
        $params = array(
            'signatureAlgorithm' => 'RSA_SHA256',
            'digestAlgorithm' => 'SHA256',
            'method' => 'DATAPDUSIG',
            'name' => 'BHA',
            'documentns' => array(
              'Saa' => 'urn:swift:saa:xsd:saa.2.0',
              'h' => 'urn:iso:std:iso:20022:tech:xsd:head.001.001.01',
              'd' => 'urn:iso:std:iso:20022:tech:xsd:pacs.008.001.08'
            ),
            'destinationXPath' => '/Saa:DataPDU/Saa:Body/h:AppHdr/h:Sgntr',
            'references' => array(
                array('comment' => 'Key',
                    'xpath' => '/Saa:DataPDU/Saa:Body/h:AppHdr/h:Sgntr/ds:Signature/ds:KeyInfo',
                    'sigxpath' => '/ds:Signature/ds:SignedInfo/ds:Reference[starts-with(@URI,"#")]',
                ),
                array('comment' => 'AppHdr',
                    'xpath' => '/Saa:DataPDU/Saa:Body/h:AppHdr',
                    'removeSignature' => 'true',
                    'sigxpath' => '/ds:Signature/ds:SignedInfo/ds:Reference[@URI=""]',
                ),
                array('comment' => 'Document',
                    'xpath' => '/Saa:DataPDU/Saa:Body/*[name() = "Document"]',
                    'sigxpath' => '/ds:Signature/ds:SignedInfo/ds:Reference[not(@URI)]',
                ),
            ),
        );

        $res = HorusXML::validateSignature(
          $input,
          null,
          $params,
          array(
            'logLocation' => 'php://stdout',
            'business_id' => '123456'
          )
        );

        $this::assertEquals(0, $res);
    }

    public function testLau(): void
    {
        $input = '<?xml version="1.0" encoding="UTF-8"?><DataPDU xmlns="urn:swift:saa:xsd:saa.2.0">' .
        '<Revision>2.0.10</Revision><Header><Message><SenderReference>AX20222800717240</SenderReference>' .
        '<MessageIdentifier>camt.003.001.07</MessageIdentifier><Format>MX</Format><Sender>' .
        '<DN>cn=cristaltest,ou=rec,ou=esmig,o=bpcefrpp,o=swift</DN></Sender><Receiver>' .
        '<DN>cn=clm,o=trgtxepm,o=swift</DN></Receiver><InterfaceInfo>' .
        '<UserReference>AX20222800717240</UserReference><MessageNature>Financial</MessageNature>' .
        '</InterfaceInfo><NetworkInfo><Service>esmig.t2.ia!pu</Service></NetworkInfo></Message>' .
        '</Header><Body><AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01"><Fr><FIId>' .
        '<FinInstnId><BICFI>BPCEFRPPXXX</BICFI><ClrSysMmbId><MmbId>BPCEFRPPXXX</MmbId></ClrSysMmbId>' .
        '</FinInstnId></FIId></Fr><To><FIId><FinInstnId><BICFI>TRGTXEPMCLM</BICFI></FinInstnId></FIId>' .
        '</To><BizMsgIdr>AX20222800717240</BizMsgIdr><MsgDefIdr>camt.003.001.07</MsgDefIdr>' .
        '<CreDt>2022-10-07T10:49:20Z</CreDt></AppHdr><Document xmlns="urn:iso:std:iso:20022:' .
        'tech:xsd:camt.003.001.07"><GetAcct><MsgHdr><MsgId>NONREF</MsgId></MsgHdr><AcctQryDef>' .
        '<AcctCrit><NewCrit><SchCrit><AcctId><EQ><Othr><Id>MFREURBPCEFRPPXXX-MCA</Id></Othr></EQ>' .
        '</AcctId></SchCrit></NewCrit></AcctCrit></AcctQryDef></GetAcct></Document></Body><LAU>' .
        '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo>' .
        '<ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>' .
        '<ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#hmac-sha256"/>' .
        '<ds:Reference URI=""><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2000/09/' .
        'xmldsig#enveloped-signature"/><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>' .
        '</ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>' .
        '<ds:DigestValue>kzK9Z065o/JiZEtbNciQo7rhGcOgRjitCS5wCR+/onE=</ds:DigestValue></ds:Reference>' .
        '</ds:SignedInfo><ds:SignatureValue>fVbRwMPOBddTXIE9uMYvHUOgqFjVNpNaHmlAXuzUpOc=</ds:SignatureValue>' .
        '</ds:Signature></LAU></DataPDU>';
        HorusXML::validateSignature(
            $input,
            null,
            array(
                'signatureAlgorithm' => 'SHA256',
                'digestAlgorithm' => 'SHA256',
                'method' => 'SWIFTLAU',
                'documentNSPrefix' => 'Saa',
                'documentNSURI' => 'urn:swift:saa:xsd:saa.2.0',
                'destinationXPath' => '/Saa:DataPDU/Saa:LAU',
                'key' => 'abcdeFGHIJ123456abcdeFGHIJ123456'),
            array('business_id' => '122345')
          );

        $this::assertTrue(true, 'Passed LAU Test 1');
        $input2 = '<?xml version="1.0" encoding="UTF-8"?><DataPDU xmlns="urn:swift:saa:xsd:saa.2.0">' .
        '<Revision>2.0.10</Revision><Header><Message><SenderReference>AX20222870003890</SenderReference>' .
        '<MessageIdentifier>camt.003.001.07</MessageIdentifier><Format>AnyXML</Format><Sender>' .
        '<DN>cn=BPCEFRPP,ou=payment,o=bank,o=swift</DN></Sender><Receiver' .
        '<DN>cn=clm,o=trgtxepm,o=swift</DN></Receiver><InterfaceInfo><UserReference>AX20222870003890</UserReference>' .
        '<MessageNature>Financial</MessageNature></InterfaceInfo><NetworkInfo><Service>esmig.t2.ia!pu</Service>' .
        '</NetworkInfo></Message></Header><Body><AppHdr xmlns="urn:iso:std:iso:20022:tech:xsd:head.001.001.01">' .
        '<Fr><FIId><FinInstnId><BICFI>BPCEFRPPXXX</BICFI><ClrSysMmbId><ClrSysId><Prtry>CLM</Prtry></ClrSysId>' .
        '<MmbId>system-user-bpcefrppxxx</MmbId></ClrSysMmbId></FinInstnId></FIId></Fr><To><FIId><FinInstnId>' .
        '<BICFI>TRGTXEPMCLM</BICFI></FinInstnId></FIId></To><BizMsgIdr>AX20222870003890</BizMsgIdr>' .
        '<MsgDefIdr>camt.003.001.07</MsgDefIdr><CreDt>2022-10-14T12:23:13Z</CreDt></AppHdr>' .
        '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.003.001.07"><GetAcct><MsgHdr>' .
        '<MsgId>NONREF</MsgId></MsgHdr><AcctQryDef><AcctCrit><NewCrit><SchCrit><AcctId><EQ><Othr>' .
        '<Id>FRBDFEPBPCEFRPPMCA</Id></Othr></EQ></AcctId></SchCrit></NewCrit></AcctCrit></AcctQryDef>' .
        '</GetAcct></Document></Body><LAU><ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' .
        '<ds:SignedInfo><ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>' .
        '<ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#hmac-sha256"/>' .
        '<ds:Reference URI=""><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2000/09/' .
        'xmldsig#enveloped-signature"/><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>' .
        '</ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>' .
        '<ds:DigestValue>pho8AyTEESWE4Clj6ncC8y5gofO4xF4lOYqa52H2k0I=</ds:DigestValue></ds:Reference>' .
        '</ds:SignedInfo><ds:SignatureValue>qNoiPRdWpgsVof/0V5AittfX/vMd612/Es2GpzXwAuQ=</ds:SignatureValue>' .
        '</ds:Signature></LAU></DataPDU>';
        HorusXML::validateSignature(
            $input2,
            null,
            array(
                'signatureAlgorithm' => 'SHA256',
                'digestAlgorithm' => 'SHA256',
                'method' => 'SWIFTLAU',
                'documentNSPrefix' => 'Saa',
                'documentNSURI' => 'urn:swift:saa:xsd:saa.2.0',
                'destinationXPath' => '/Saa:DataPDU/Saa:LAU',
                'key' => 'secret',
            ),
            array('business_id' => '122345')
        );
        $this::assertTrue(true, 'Passed LAU Test 2');
    }
}
