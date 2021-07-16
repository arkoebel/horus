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

    function __construct($business_id, $log_location, $colour = 'GREEN',$tracer)
    {
        $this->common = new HorusCommon($business_id, $log_location, $colour);
        $this->http = new HorusHttp($business_id, $log_location, $colour,$tracer);
        $this->business = new HorusBusiness($business_id, $log_location, $colour,$tracer);
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
            if ((XML_TEXT_NODE == $dom->childNodes->item(0)->nodeType)&&($dom->childNodes->count()==1)) 
            {
                return (string) $rr[0];
            } else {
                return $rr[0]->asXml();
            }
        } else {
            return '';
        }
    }

    function getResponses($templates, $vars, $formats, $preferredType, $errorTemplate,$span)
    {
        $response = array();
        $nrep = 0;

        foreach ($templates as $template) {
            $respxml = 'templates/' . HorusBusiness::getTemplateName($template, $vars);
            $this->common->mlog("Using template " . $respxml, 'INFO');
            ob_start();
            include $respxml;
            $output = ob_get_contents();
            ob_end_clean();
            $outputxml = new DOMDocument();
            $outputxml->loadXML(preg_replace('/\s*(<[^>]*>)\s*/', '$1', $output));
            if (!($outputxml->schemaValidate('xsd/' . $formats[$nrep]) === TRUE)) {
                $errorMessage = "Could not validate output with " . $formats[$nrep] . "\n";
                $errorMessage .= $this->common->libxml_display_errors();
                $this->common->mlog($errorMessage . "\n", 'ERROR');
                throw new HorusException($this->business->returnGenericError($preferredType, $errorTemplate, $errorMessage, '',$span));
            } else {
                $outputxml->formatOutput = false;
                $outputxml->preserveWhiteSpace = false;
                $response[] = $this->http->convertOutData($outputxml->saveXML(), $preferredType);
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
                    if(strlen($value)<50)
                        $fwd_params[] = $key . '=' . $value;
                }
                $this->common->mlog('query out (urlparameters) : ' . print_r($fwd_params, true), 'INFO');
                if (stripos($proxy_mode, '?') === FALSE) {
                    $url .= '?';
                } else {
                    $url .= '&';
                }
                $vv1 = array();
                foreach($vars as $k=>$v)
                    if(strlen($v)<50)
                        $vv1[] = urlencode($k) . "=" . urlencode($v);

                $vv = array_merge($vv1, $fwd_params);

                $url .= implode('&', $vv);
            }
        } else if ($url === '' && $forwardparams !== null && $forwardparams != "" && is_array($forwardparams) && (count($forwardparams) == 1) && count($forwardparams[0]) > 0) {
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
                    if(strpos($key,'x-horus-')===0)
                        $fwd_params[$key] = $key . ': ' . $key . ';' . $value;
                    else
                        $fwd_params['x-horus-' . $key] = 'x-horus-' . $key . ': ' . $key . ';' . $value;
                }
            }
            foreach ($vars as $key => $value){
                if (strpos($key,'x-horus-')===0)
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

    function doInject($reqbody, $content_type, $proxy_mode, $matches, $preferredType, $queryParams, $genericError, $defaultNamespace = '',$rootSpan = null)
    {
        $input = $this->business->extractPayload($content_type, $reqbody, $genericError, $preferredType,$rootSpan);
        libxml_use_internal_errors(true);
        $rootSpan->log(['message'=>'Validating XML Input']);
        $query = simplexml_load_string($input);
        if ($query === FALSE) {
            $errorMessage = "Input XML not properly formatted.\n";
            $errorMessage .= $this->common->libxml_display_errors();
            $ret = $this->business->returnGenericError($preferredType, $genericError, $errorMessage, '',$rootSpan);

            throw new HorusException($ret);
        }


        $namespaces = $this->getRootNamespace($query, $defaultNamespace);
        $query->registerXPathNamespace('u', $namespaces);

        $rootSpan->log(['message'=>'Finding XSD']);
        $selectedXsd = $this->findSchema($query, $defaultNamespace);

        if ('' !== $selectedXsd) {
            $selected = $this->business->locate($matches, $selectedXsd, $input);
            if ($selected == -1) {
                $errorMessage = "Found match, but filtered out\n";
                $errorMessage .= "XSD = $selectedXsd";
                $this->common->mlog($errorMessage . "\n", 'INFO');
                throw new HorusException($this->business->returnGenericError($preferredType, $genericError, $errorMessage, '',$rootSpan));
            }
            $this->registerExtraNamespaces($query, $this->business->findMatch($matches, $selected, "extraNamespaces"));
            $vars = $this->getVariables($query, $matches, $selected);

            $this->common->mlog("Match comment : " . $this->business->findMatch($matches, $selected, "comment") . "\n", 'INFO');
            $rootSpan->setTag('section',$this->business->findMatch($matches, $selected, "comment"));
            $vars = array_merge($queryParams, $vars);
            $this->common->mlog("Variables: " . print_r($vars, true) . "\n", 'INFO');

            $rootSpan->log(['message'=>'Finding Response Template']);
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
                throw new HorusException($this->business->returnGenericError($preferredType, $errorTemplate, "Requested error", '',$rootSpan));
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
                $rootSpan->log(['message'=>'Generate XML Response']);
                $resp = $this->getResponses($templates, $vars, $formats, $preferredType, $errorTemplate,$rootSpan);
            } catch (HorusException $e) {
                throw new HorusException($e->getMessage());
            }
            $forwardData = $this->formOutQuery($forwardparams, $proxy_mode, $vars);


            if ($multiple) {
                $response = '';
                foreach ($resp as $i => $r) {
                    $response .= $this->http->formMultiPart("response_$i", $r, $mime_boundary, $eol, $preferredType);
                }
                $ret=null;
                if (''===$proxy_mode)
                    $ret=$this->http->returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol, "multipart/form-data; boundary=$mime_boundary", 200, $proxy_mode,false,'POST',$forwardData,$rootSpan);
                else
                    $ret=$this->http->returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol, "multipart/form-data; boundary=$mime_boundary", 200, $forwardData,false,'POST',$vars,$rootSpan);
            } else {
                if (''===$proxy_mode)
                    $ret=$this->http->returnWithContentType($resp, $preferredType, 200, $proxy_mode, false, 'POST', $forwardData,$rootSpan);
                else
                    $ret=$this->http->returnWithContentType($resp, $preferredType, 200, $forwardData, false, 'POST', $vars,$rootSpan);
            }
            return $ret;
        } else {
            $errorMessage = "Unable to find appropriate response.\n";
            $errorMessage .= $this->common->libxml_display_errors();
            $this->common->mlog($errorMessage . "\n", 'ERROR');
            $rootSpan->log(['message'=>'Generate Error']);
            $res = $this->business->returnGenericError($preferredType, $genericError, $errorMessage, '',$rootSpan);
            //if ('' === $proxy_mode)
            throw new HorusException($res);
        }
    }
}
