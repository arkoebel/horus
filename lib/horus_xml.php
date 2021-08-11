<?php

use OpenTracing\Formats;
use OpenTracing\GlobalTracer;
use Jaeger\Config;

class HorusXml
{
    public $common = null;
    public $http = null;
    public $business = null;
    public $business_id = '';
    public $tracer = null;

    public const XMLENVSIG = "http://www.w3.org/2000/09/xmldsig#enveloped-signature";
    public const XMLC14N = "http://www.w3.org/2001/10/xml-exc-c14n#";
    public const XMLAES = "http://www.w3.org/2001/04/xmldsig-more#rsa-sha256";
    public const XMLDSIGNS = "http://www.w3.org/2000/09/xmldsig#";

    function __construct($business_id, $log_location, $colour = 'GREEN', $tracer)
    {
        $this->common = new HorusCommon($business_id, $log_location, $colour);
        $this->http = new HorusHttp($business_id, $log_location, $colour, $tracer);
        $this->business = new HorusBusiness($business_id, $log_location, $colour, $tracer);
        $this->business_id = $business_id;

        $this->tracer = $tracer;
    }

    function getRootNamespace($query, $defaultNamespace)
    {
        $namespaces = $query->getDocNamespaces();
        if ((count($namespaces) === 0) || (!array_key_exists('', $namespaces))) {
            $domns = dom_import_simplexml($query);
            $namespaces[''] = $domns->namespaceURI;
            if ($domns->namespaceURI == '') {
                $namespaces[''] = $defaultNamespace;
            }
        }
        return $namespaces[''];
    }

    function findSchema($query, $defaultNamespace = '')
    {

        $namespaces = $this->getRootNamespace($query, $defaultNamespace);

        $mnamespaces = explode(':', $namespaces);
        $namespace = array_pop($mnamespaces);

        $this->common->mlog("NS: " . print_r($namespace, true) . "\n", 'INFO');
        $domelement = dom_import_simplexml($query);
        $domdoc = $domelement->ownerDocument;

        $selectedXsd = '';

        foreach (scandir('xsd') as $schema) {
            if (!(strpos($schema, $namespace) === false)) {
                libxml_use_internal_errors(true);
                if ($domdoc->schemaValidate('xsd/' . $schema)) {
                    $selectedXsd = $schema;
                } else {
                    $this->common->mlog("Validation errors with $schema : " . $this->common->libxml_display_errors(), 'DEBUG');
                }
            } else {
                $this->common->mlog("Skipping schema $schema (doesn't match namespace)", 'DEBUG');
            }
        }
        if ('' === $selectedXsd) {
            $this->common->mlog("Failed to find appropriate XML Schema.", 'INFO');
        } else {
            $this->common->mlog("schema=$selectedXsd\n", 'DEBUG');
        }

        return $selectedXsd;
    }

    function getVariables($query, $matches, $selected)
    {
        $vars = array();

        $parameters = $this->business->findMatch($matches, $selected, "parameters");

        if (is_null($parameters) || !is_array($parameters) || (count($parameters) == 0)) {
            return $vars;
        }

        foreach ($parameters as $param => $path) {
            $vars[$param] = $this->getXpathVariable($query, $path);
        }

        return $vars;
    }

    function getXpathVariable($xml, $xpath)
    {
        $rr = $xml->xpath($xpath);
        if (($rr !== FALSE) && (count($rr) > 0)) {
            $dom = dom_import_simplexml($rr[0]);
            if ((XML_TEXT_NODE == $dom->childNodes->item(0)->nodeType) && ($dom->childNodes->count() == 1)) {
                return (string) $rr[0];
            } else {
                return $rr[0]->asXml();
            }
        } else {
            return '';
        }
    }

    function getResponses($templates, &$vars, $formats, $preferredType, $errorTemplate, $span, $multiple)
    {
        $response = array();
        $nrep = 0;

        foreach ($templates as $template) {
            $respxml = 'templates/' . HorusBusiness::getTemplateName($template, $vars);
            $vars['nb'] = $nrep;
            $this->common->mlog("Using template " . $respxml, 'INFO');
            ob_start();
            include $respxml;
            $output = ob_get_contents();
            ob_end_clean();

            if ('null' === $formats[$nrep]) {
                // Special case for empty xsd
                $this->common->mlog('Response is supposed to be empty', 'INFO');
                $this->common->mlog('Effective body : ' . $output, 'INFO');
                if ($multiple) {
                    $response[] = array("data" => null, "headers" => $vars);
                } else
                    $response[] = null;
            } else if ('' === $output) {
                // Special case for empty responses
                $this->common->mlog('Response is empty', 'INFO');
                if ($multiple) {
                    $response[] = array("data" => null, "headers" => $vars);
                }
                $response[] = null;
            } else {
                $outputxml = new DOMDocument();
                $outputxml->loadXML(preg_replace('/\s*(<[^>]*>)\s*/', '$1', $output));
                if (!($outputxml->schemaValidate('xsd/' . $formats[$nrep]) === TRUE)) {
                    $errorMessage = "Could not validate output with " . $formats[$nrep] . "\n";
                    $errorMessage .= $this->common->libxml_display_errors();
                    $this->common->mlog($errorMessage . "\n", 'ERROR');
                    throw new HorusException($this->business->returnGenericError($preferredType, $errorTemplate, $errorMessage, '', $span));
                } else {
                    $outputxml->formatOutput = false;
                    $outputxml->preserveWhiteSpace = false;
                    $rs = $this->http->convertOutData($outputxml->saveXML(), $preferredType);
                    if ($multiple) {
                        $response[] = array("data" => $rs, "headers" => $vars);
                    } else
                        $response[] = $rs;
                }
            }
            $nrep++;
        }

        return $response;
    }

    function formOutQuery($forwardparams, $proxy_mode, $vars = array())
    {
        $url = $proxy_mode;
        if ($url !== '' && $forwardparams !== null && $forwardparams != "" && is_array($forwardparams) && (count($forwardparams) == 1) && is_array($forwardparams[0]) && (count($forwardparams[0]) > 0)) {
            $this->common->mlog('params forward : ' . print_r($forwardparams, true), 'INFO');
            $fwd_params = array();
            if (is_array($forwardparams[0])) {
                foreach ($forwardparams[0] as $forwardparam) {
                    $key = urlencode($forwardparam['key']);
                    if (array_key_exists('phpvalue', $forwardparam))
                        try {
                            ob_start();
                            eval($forwardparam['phpvalue']);
                            $value = urlencode(ob_get_contents());
                            ob_end_clean();
                        } catch (\Throwable $th) {
                            if (array_key_exists('value', $forwardparams))
                                $value = urlencode($forwardparam['value']);
                            else
                                $value = '';
                        }
                    else {
                        $value = urlencode($forwardparam['value']);
                    }
                    if (strlen($value) < 50)
                        $fwd_params[] = $key . '=' . $value;
                }
                $this->common->mlog('query out (urlparameters) : ' . print_r($fwd_params, true), 'INFO');
                if (stripos($proxy_mode, '?') === FALSE) {
                    $url .= '?';
                } else {
                    $url .= '&';
                }
                $vv1 = array();
                foreach ($vars as $k => $v)
                    if (strlen($v) < 50)
                        $vv1[] = urlencode($k) . "=" . urlencode($v);

                $vv = array_merge($vv1, $fwd_params);

                $url .= implode('&', $vv);
            }
        } else if ($url === '' && $forwardparams !== null && $forwardparams != "" && is_array($forwardparams) && (count($forwardparams) == 1) && is_array($forwardparams[0]) && count($forwardparams[0]) > 0) {
            $this->common->mlog('return headers : ' . print_r($forwardparams, true), 'INFO');
            $fwd_params = array();
            if (is_array($forwardparams[0])) {
                foreach ($forwardparams[0] as $forwardparam) {
                    $key = urlencode($forwardparam['key']);
                    if (array_key_exists('phpvalue', $forwardparam))
                        try {
                            ob_start();
                            eval($forwardparam['phpvalue']);
                            $value = urlencode(ob_get_contents());
                            ob_end_clean();
                        } catch (\Throwable $th) {
                            if (array_key_exists('value', $forwardparams))
                                $value = urlencode($forwardparam['value']);
                            else
                                $value = '';
                        }
                    else {
                        $value = urlencode($forwardparam['value']);
                    }
                    if (strpos($key, 'x-horus-') === 0)
                        $fwd_params[$key] = $key . ': ' . $key . ';' . $value;
                    else
                        $fwd_params['x-horus-' . $key] = 'x-horus-' . $key . ': ' . $key . ';' . $value;
                }
            }
            foreach ($vars as $key => $value) {
                if (strpos($key, 'x-horus-') === 0)
                    $key2 = $key;
                else
                    $key2 = 'x-horus-' . $key;

                if (!array_key_exists($key2, $fwd_params))
                    $fwd_params[$key2] = $key2 . ': ' . $key . ';' . $value;
            }

            $ff = array();
            foreach ($fwd_params as $f)
                $ff[] = $f;

            $this->common->mlog('query out (headers): ' . print_r($ff, true), 'INFO');
            return $ff;
        }

        return $url;
    }

    function searchNameSpace($elementName, $xml)
    {
        $dom = dom_import_simplexml($xml);
        $list = $dom->getElementsByTagName($elementName);
        if ($list->length == 1) {
            $this->common->mlog('Found element ' . $elementName . ' at namespace ' . $list->item(0)->namespaceURI, 'DEBUG');

            return $list->item(0)->namespaceURI;
        } else {
            if ($list->length == 0) {
                $this->common->mlog('Element ' . $elementName . ' not found', 'DEBUG');
            } else {
                $this->common->mlog('Found too many elements named ' . $elementName . ' (' . $list->length . ')', 'DEBUG');
            }
            return '';
        }
    }

    function registerExtraNamespaces($query, $extraNamespaces)
    {

        if ('' !== $extraNamespaces) {
            foreach ($extraNamespaces as $ns) {
                if (array_key_exists('namespace', $ns)) {
                    $query->registerXPathNamespace($ns['prefix'], $ns['namespace']);
                    $this->common->mlog('Registering extra namespace ' . $ns['prefix'] . ', ' . $ns['namespace'], 'INFO');
                } elseif (array_key_exists('element', $ns)) {
                    $elementns = $this->searchNameSpace($ns['element'], $query);
                    $query->registerXPathNamespace($ns['prefix'], $elementns);
                    $this->common->mlog('Registering extra namespace ' . $ns['prefix'] . ', ' . $elementns . ' (' . $ns['element'] . ')', 'INFO');
                }
            }
        } else {
            $this->common->mlog('No extra namespace to register', 'INFO');
        }
    }

    function doInject($reqbody, $content_type, $proxy_mode, $matches, $preferredType, $queryParams, $genericError, $defaultNamespace = '', $rootSpan = null)
    {
        $input = $this->business->extractPayload($content_type, $reqbody, $genericError, $preferredType, $rootSpan);
        libxml_use_internal_errors(true);
        $rootSpan->log(['message' => 'Validating XML Input']);
        $query = simplexml_load_string($input);
        if ($query === FALSE) {
            $errorMessage = "Input XML not properly formatted.\n";
            $errorMessage .= $this->common->libxml_display_errors();
            $ret = $this->business->returnGenericError($preferredType, $genericError, $errorMessage, '', $rootSpan);

            throw new HorusException($ret);
        }


        $namespaces = $this->getRootNamespace($query, $defaultNamespace);
        $query->registerXPathNamespace('u', $namespaces);

        $rootSpan->log(['message' => 'Finding XSD']);
        $selectedXsd = $this->findSchema($query, $defaultNamespace);

        if ('' !== $selectedXsd) {
            $selected = $this->business->locate($matches, $selectedXsd, $input);
            if ($selected == -1) {
                $errorMessage = "Found match, but filtered out\n";
                $errorMessage .= "XSD = $selectedXsd";
                $this->common->mlog($errorMessage . "\n", 'INFO');
                throw new HorusException($this->business->returnGenericError($preferredType, $genericError, $errorMessage, '', $rootSpan));
            }
            $this->registerExtraNamespaces($query, $this->business->findMatch($matches, $selected, "extraNamespaces"));
            $vars = $this->getVariables($query, $matches, $selected);

            $this->common->mlog("Match comment : " . $this->business->findMatch($matches, $selected, "comment") . "\n", 'INFO');
            $rootSpan->setTag('section', $this->business->findMatch($matches, $selected, "comment"));
            $vars = array_merge($queryParams, $vars, $this->http->filterMQHeaders(apache_request_headers(), 'UNPACK'));
            $this->common->mlog("Variables: " . print_r($vars, true) . "\n", 'INFO');

            $validator = $this->business->findMatch($matches, $selected, "validator");

            if ('' !== $validator) {
                $this->common->mlog('Attempting to validate Signature/Digest', 'INFO');
                $rootSpan->log(['message' => 'Attempt to validate Signature/Digest']);
                try {
                    HorusXml::validateSignature($reqbody, $vars, $validator, $this->common->cnf);
                    $this->common->mlog('Signature/Digest checked OK', 'INFO');
                } catch (HorusException $e) {
                    $this->common->mlog('Signature/Digest validation failed ' . $e->getMessage(), 'ERROR');
                    throw new HorusException($e->getMessage());
                }
            }

            $rootSpan->log(['message' => 'Finding Response Template']);
            $templs = $this->business->findMatch($matches, $selected, "responseTemplate");
            if (is_array($templs)) {
                $this->common->mlog("Selected template : " . implode(',', $templs) . "\n", 'INFO');
            } else {
                $this->common->mlog("Selected template : " . $templs . "\n", 'INFO');
            }

            $errorTemplate = $this->business->findMatch($matches, $selected, "errorTemplate");
            $errorTemplate = (($errorTemplate == null) ? $genericError : $errorTemplate);
            $errorTemplate = 'templates/' . $errorTemplate;
            if ($this->business->findMatch($matches, $selected, "displayError") === "On") {
                throw new HorusException($this->business->returnGenericError($preferredType, $errorTemplate, "Requested error", '', $rootSpan));
            }
            $response = '';
            $multiple = false;
            if (!is_array($this->business->findMatch($matches, $selected, "responseTemplate"))) {
                $templates = array($this->business->findMatch($matches, $selected, "responseTemplate"));
                $formats = array($this->business->findMatch($matches, $selected, "responseFormat"));
                $forwardparams = array($this->business->findMatch($matches, $selected, "destParameters"));
            } else {
                $templates = $this->business->findMatch($matches, $selected, "responseTemplate");
                $formats = $this->business->findMatch($matches, $selected, "responseFormat");
                $forwardparams = array($this->business->findMatch($matches, $selected, "destParameters"));
                $multiple = true;
            }

            $eol = "\r\n";
            $mime_boundary = md5(time());

            try {
                $rootSpan->log(['message' => 'Generate XML Response']);
                $resp = $this->getResponses($templates, $vars, $formats, $preferredType, $errorTemplate, $rootSpan, $multiple);
            } catch (HorusException $e) {
                throw new HorusException($e->getMessage());
            }
            $forwardData = $this->formOutQuery($forwardparams, $proxy_mode, $vars);


            if ($multiple) {
                $response = '';
                foreach ($resp as $i => $r) {
                    $response .= $this->http->formMultiPart("response_$i", $r['data'], $mime_boundary, $eol, $preferredType, $r['headers']);
                }
                $ret = null;
                if ('' === $proxy_mode)
                    $ret = $this->http->returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol, "multipart/form-data; boundary=$mime_boundary", 200, $proxy_mode, false, 'POST', $forwardData, $rootSpan);
                else
                    $ret = $this->http->returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol, "multipart/form-data; boundary=$mime_boundary", 200, $forwardData, false, 'POST', $vars, $rootSpan);
            } else {
                if ('' === $proxy_mode)
                    $ret = $this->http->returnWithContentType($resp, $preferredType, 200, $proxy_mode, false, 'POST', $forwardData, $rootSpan);
                else
                    $ret = $this->http->returnWithContentType($resp, $preferredType, 200, $forwardData, false, 'POST', $vars, $rootSpan);
            }
            return $ret;
        } else {
            $errorMessage = "Unable to find appropriate response.\n";
            $errorMessage .= $this->common->libxml_display_errors();
            $this->common->mlog($errorMessage . "\n", 'ERROR');
            $rootSpan->log(['message' => 'Generate Error']);
            $res = $this->business->returnGenericError($preferredType, $genericError, $errorMessage, '', $rootSpan);
            //if ('' === $proxy_mode)
            throw new HorusException($res);
        }
    }

    static function getSignaturePart($fragment, $url, $transforms)
    {
        foreach ($transforms as $transform) {
        }
    }

    static function getSignatureFragment($namespaces, $digests, $signature){
        $dsigns = 'http://www.w3.org/2000/09/xmldsig#';
        $doc = new DOMDocument();
        $fragment = $doc->appendChild($doc->createElementNS($dsigns,'ds:Signature'));
        $signedInfo = $fragment->appendChild(new DOMElement('ds:SignedInfo',null,$dsigns));
        $cmethod = $signedInfo->appendChild(new DOMElement('ds:CanonicalizationMethod',null,$dsigns));
        $cmethod->setAttribute('Algorithm','http://www.w3.org/2001/10/xmlexc-c14n#');
        $sigMethod = $signedInfo->appendChild(new DOMElement('ds:SignatureMethod',null,$dsigns));
        $sigMethod->setAttribute('Algorithm','http://www.w3.org/2001/04/xmldsigmore#hmac-sha256');
        foreach ($namespaces as $i=>$namespace){
            $ref = $signedInfo->appendChild(new DOMElement('ds:Reference',null,$dsigns));
            $ref->setAttribute('URI',$namespace);
            $transforms = $ref->appendChild(new DOMElement('ds:Transforms',null,$dsigns));
            $envsig = $transforms->appendChild(new DOMElement('ds:Transform',null,$dsigns));
            $envsig->setAttribute('Algorithm','http://www.w3.org/2000/09/xmldsig#enveloped-signature');
            $c14n = $transforms->appendChild(new DOMElement('ds:Transform',null,$dsigns));
            $c14n->setAttribute('Algorithm','http://www.w3.org/2001/10/xml-excc14n#');
            $digestmethod = $ref->appendChild(new DOMElement('ds:DigestMethod',null,$dsigns));
            $digestmethod->setAttribute('Algorithm','http://www.w3.org/2001/04/xmlenc#sha256');
            $ref->appendChild(new DOMElement('ds:DigestValue',$digests[$i],$dsigns));
        }
        $fragment->appendChild(new DOMElement('ds:SignatureValue',$signature,$dsigns));
        return $doc->saveXML();
    }

    static function validateSignature($document, $headers, $definition, $conf)
    {
        if ('HMAC' === $definition['method']) {
            $totest = '';
            foreach ($definition['parameters'] as $field) {
                if ('Document' === $field)
                    $totest .= rtrim($document);
                else {
                    if (array_key_exists($field, $headers))
                        $totest .= $headers[$field];
                    else if (array_key_exists($conf[HorusCommon::RFH_PREFIX] . $field, $headers))
                        $totest .= $headers[$conf[HorusCommon::RFH_PREFIX] . $field];
                    else if (array_key_exists($conf[HorusCommon::MQMD_PREFIX] . $field, $headers))
                        $totest .= $headers[$conf[HorusCommon::MQMD_PREFIX] . $field];
                }
            }
            //error_log('Digest variables : key=' . $definition['key'] . "\nXXX" . $totest . "XXX\n", 3, '/var/log/horus/horus_http.log');
            //file_put_contents('/var/log/horus/test.txt', $totest);
            $digest = base64_encode(hash_hmac($definition['algorithm'], $totest, hex2bin($definition['key']), true));

            $expectedDigest = '';

            if (array_key_exists($definition['valueField'], $headers))
                $expectedDigest = $headers[$definition['valueField']];
            else if (array_key_exists($conf[HorusCommon::RFH_PREFIX] . $definition['valueField'], $headers))
                $expectedDigest = $headers[$conf[HorusCommon::RFH_PREFIX] . $definition['valueField']];

            if ($digest != $expectedDigest)
                throw new HorusException('Digest Verification Failed (found ' . $digest . ', expected ' . $expectedDigest . ')');
        }else if ('XMLDSIG' === $definition['method']){
            // Load original document, preserving its format.
            $xml = new DOMDocument();
            $xml->preserveWhiteSpace = true;
            $xml->formatOutput = false;
            $xml->loadXML($document);

            $xpath = new DOMXPath($xml);
            $xpath->registerNamespace('ds',HorusXml::XMLDSIGNS);
            if((array_key_exists('documentNSPrefix',$definition))&&(array_key_exists('documentNSURI',$definition)))
                $xpath->registerNamespace($definition['documentNSPrefix'],$definition['documentNSURI']);
            else
                $xpath->registerNamespace('u',$xml->namespaceURI);

            // Lookup the XMLDSIG Signature Element 
            if(array_key_exists('destinationXPath',$definition))
                $sig = $xpath->query($definition['destinationXPath'] . '/ds:Signature');
            else
                $sig = $xml->getElementsByTagNameNS(HorusXml::XMLDSIGNS,'Signature');
            if($sig->length==0){
                throw new HorusException('Document doesn\'t appear to be signed');
            }

            // Lookup the XMLDSIG SignedInfo Element
            $signedInfo = $sig->item(0)->getElementsByTagNameNS(HorusXML::XMLDSIGNS,'SignedInfo');
            if($signedInfo->length==0){
                throw new HorusException('Malformed Signature (missing SignedInfo)');
            }

            // While the signature is still there, canonicalize the SignedInfo and extract the Digest value
            $canonical2 = $signedInfo->item(0)->c14N(true,false,null,array('ds'));
            error_log('Canonical Signed Info XXXX' . $canonical2 . 'XXXX' . "\n");
            $digest = $signedInfo->item(0)->getElementsByTagNameNS(HorusXML::XMLDSIGNS,'DigestValue');
            if($digest->length==0){
                throw new HorusException('Malformed Signature (missing DigestValue)');
            }
            $digestValue = $digest->item(0)->nodeValue;

            // While the signature is still there, extract its value
            $signature = $sig->item(0)->getElementsByTagNameNS(HorusXML::XMLDSIGNS,'SignatureValue');
            if($signature->length==0){
                throw new HorusException('Malformed Signature (missing SignatureValue)');
            }
            $signatureValue = $signature->item(0)->nodeValue;

            // Remove the Signature element from the original XML (http://www.w3.org/2000/09/xmldsig#enveloped-signature)
            $sig->item(0)->parentNode->removeChild($sig->item(0));

            // Canonicalize the original document without the signature (http://www.w3.org/2001/10/xml-exc-c14n#)
            $canonical1 = $xml->c14N(false,false);
            error_log('Canonical Document XXXX' . $canonical1 . 'XXXX' . "\n");

            // Calculate the digest for the whole document
            $digest = base64_encode(openssl_digest($canonical1,$definition['digestAlgorithm'],true));
            if($digest!==$digestValue){
                throw new HorusException('Wrong Digest (Found: ' . $digestValue . ', Computed: ' . $digest);
            }

            // Calculate the signature using the supplied Key and algorithm.
            if(preg_match('/^RSA/',$definition['signatureAlgorithm'])){
                // If we had the private key
                $private = openssl_pkey_get_private($definition['key'],$definition['passphrase']);
                
                openssl_sign($canonical2,$computedSignature,$private,$definition['signatureAlgorithm']);
                $computedSignature = base64_encode($computedSignature);

            }else{
                $computedSignature = base64_encode(hash_hmac($definition['signatureAlgorithm'],$canonical2,$definition['key'],true));
            }
            if($signatureValue!==$computedSignature){
                throw new HorusException('Wrong Signature (Found: ' . $signatureValue . ', Computed: ' . $computedSignature);
            }

        }
    }
}
