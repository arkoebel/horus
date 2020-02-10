<?php

class HorusXml
{
    public $common = null;
    public $http = null;
    public $business = null;
    public $business_id = '';

    function __construct($business_id, $log_location, $colour = 'GREEN')
    {
        $this->common = new HorusCommon($business_id, $log_location, $colour);
        $this->http = new HorusHttp($business_id, $log_location, $colour);
        $this->business = new HorusBusiness($business_id, $log_location, $colour);
        $this->business_id = $business_id;
    }

    function findSchema($query, $defaultNamespace = '')
    {

        $namespaces = $query->getDocNamespaces();
        if (count($namespaces) === 0) {
            $namespaces[''] = $defaultNamespace;
        }
        $mnamespaces = explode(':', $namespaces[""]);
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

        if(is_null($parameters)||!is_array($parameters)||(count($parameters)==0)){
            return $vars;
        }

        foreach ($parameters as $param => $path) {
            $vars[$param] = $this->getXpathVariable($query, $path);
        }

        return $vars;
    }

    function getXpathVariable($xml, $xpath)
    {
        $rr = ($xml->xpath($xpath));
        if (($rr !== FALSE) && (count($rr) > 0)) {
            $rr0 = $rr[0];
            if ($rr0->count() != 0) {
                return $rr0->asXml();
            } else {
                return (string) $rr0;
            }
        } else {
            return '';
        }
    }

    function getResponses($templates, $vars, $formats, $preferredType, $errorTemplate)
    {
        $response = array();
        $nrep = 0;

        foreach ($templates as $template) {
            $respxml = 'templates/' . $template;
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
                throw new HorusException($this->business->returnGenericError($preferredType, $errorTemplate, $errorMessage, ''));
            } else {
                $outputxml->formatOutput = false;
                $outputxml->preserveWhiteSpace = false;
                $response[] = $this->http->convertOutData($outputxml->saveXML(), $preferredType);
            }
            $nrep++;
        }

        return $response;
    }

    function formOutQuery($forwardparams, $proxy_mode)
    {
        $url = $proxy_mode;
        if ($url !== '' && $forwardparams !== null && $forwardparams != "" && is_array($forwardparams) && (count($forwardparams) == 1) && count($forwardparams[0]) > 0) {
            $this->common->mlog('params forward : ' . print_r($forwardparams, true), 'INFO');
            $fwd_params = array();
            if (is_array($forwardparams[0])) {
                foreach ($forwardparams[0] as $forwardparam) {
                    $key = urlencode($forwardparam['key']);
                    $value = urlencode($forwardparam['value']);
                    $fwd_params[] = $key . '=' . $value;
                }
                $this->common->mlog('query out : ' . print_r($fwd_params, true), 'INFO');
                if (stripos($proxy_mode, '?') === FALSE) {
                    $url .= '?';
                } else {
                    $url .= '&';
                }
                $url .= implode('&', $fwd_params);
            }
        }
        return $url;
    }

    function searchNameSpace($elementName, $xml)
    {
        $namespace = '';
        foreach ($xml->Children() as $element => $fragment) {
            if ($element === $elementName) {
                $ns = $fragment->getNamespaces();
                if (is_array($ns) && array_key_exists('', $ns)) {
                    return $ns[''];
                }
            }
        }
        return $namespace;
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

    function doInject($reqbody, $content_type, $proxy_mode, $matches, $preferredType, $queryParams, $genericError, $defaultNamespace = '')
    {
        $input = $this->business->extractPayload($content_type, $reqbody, $genericError, $preferredType);
        libxml_use_internal_errors(true);
        $query = simplexml_load_string($input);
        if ($query === FALSE) {
            $errorMessage = "Input XML not properly formatted.\n";
            $errorMessage .= $this->common->libxml_display_errors();
            $ret = $this->business->returnGenericError($preferredType, $genericError, $errorMessage, '');

            throw new HorusException($ret);
        }


        $namespaces = $query->getDocNamespaces();
        $query->registerXPathNamespace('u', $namespaces[""]);

        $selectedXsd = $this->findSchema($query, $defaultNamespace);

        if ('' !== $selectedXsd) {
            $selected = $this->business->locate($matches, $selectedXsd, $input);
            if ($selected == -1) {
                $errorMessage = "Found match, but filtered out\n";
                $errorMessage .= "XSD = $selectedXsd";
                $this->common->mlog($errorMessage . "\n", 'INFO');
                throw new HorusException($this->business->returnGenericError($preferredType, $genericError, $errorMessage, ''));
            }
            $this->registerExtraNamespaces($query, $this->business->findMatch($matches, $selected, "extraNamespaces"));
            $vars = $this->getVariables($query, $matches, $selected);

            $this->common->mlog("Match comment : " . $this->business->findMatch($matches, $selected, "comment") . "\n", 'INFO');

            $vars = array_merge($queryParams, $vars);
            $this->common->mlog("Variables: " . print_r($vars, true) . "\n", 'INFO');

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
                throw new HorusException($this->business->returnGenericError($preferredType, $errorTemplate, "Requested error", ''));
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
                $resp = $this->getResponses($templates, $vars, $formats, $preferredType, $errorTemplate);
            } catch (HorusException $e) {
                throw new HorusException($e->getMessage());
            }
            $url = $this->formOutQuery($forwardparams, $proxy_mode);


            if ($multiple) {
                $response = '';
                foreach ($resp as $i => $r) {
                    $response .= $this->http->formMultiPart("response_$i", $r, $mime_boundary, $eol, $preferredType);
                }
                return $this->http->returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol, "multipart/form-data; boundary=$mime_boundary", 200, $url);
            } else {
                return $this->http->returnWithContentType($resp, $preferredType, 200, $url);
            }
        } else {
            $errorMessage = "Unable to find appropriate response.\n";
            $errorMessage .= $this->common->libxml_display_errors();
            $this->common->mlog($errorMessage . "\n", 'ERROR');
            $res = $this->business->returnGenericError($preferredType, $genericError, $errorMessage, '');
            //if ('' === $proxy_mode)
            throw new HorusException($res);
        }
    }
}
