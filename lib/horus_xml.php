<?php

class HorusXml
{
    private $common = null;
    private $http = null;
    private $business = null;
    private $business_id = '';
    private $matches = null;

    function __construct($business_id, $log_location)
    {
        $this->common = new HorusCommon($business_id, $log_location, 'GREEN');
        $this->http = new HorusHttp($business_id, $log_location, 'GREEN');
        $this->business = new HorusBusiness($business_id, $log_location, 'GREEN');
        $this->business_id = $business_id;
        
    }

    function findSchema($query){

        $namespaces = $query->getDocNamespaces();
        $mnamespaces = explode(':', $namespaces[""]);
        $namespace = array_pop($mnamespaces);

        $domelement = dom_import_simplexml($query);
        $domdoc = $domelement->ownerDocument;

        $selectedXsd = '';

        foreach (scandir('xsd') as $schema) {
            if (!(strpos($schema, $namespace) === false)) {
                libxml_use_internal_errors(true);
                if ($domdoc->schemaValidate('xsd/' . $schema)) {
                    $selectedXsd = $schema;
                } else {
                    //$this->common->mlog("Validation errors with $schema : " . $this->common->libxml_display_errors(),'INFO');
                }
            } else {
                //echo "skipping $schema\n";
            }
        }
        if (''=== $selectedXsd)
            $this->common->mlog("Failed to find appropriate XML Schema.",'INFO');
        else
            $this->common->mlog("schema=$selectedXsd\n", 'DEBUG');

        return $selectedXsd;
    }

    function getVariables($query,$matches,$selected){
        $vars = array();
 
        foreach ($this->business->findMatch($matches, $selected, "parameters") as $param => $path) {
                
            $rr = ($query->xpath($path));
            if (($rr !== FALSE)&&(count($rr)>0)) {
                $rr0 = $rr[0];
                if ($rr0->count() != 0) {
                    $vars[$param] = $rr0->asXml();
                } else {
                    $vars[$param] = (string) $rr0;
                }
            }else{
                $vars[$param] = '';
            }
        }

        return $vars;
    }

    function doInject($reqbody, $content_type, $proxy_mode, $matches, $preferredType, $queryParams, $genericError)
    {
        $input = $this->business->extractPayload($content_type, $reqbody, $genericError, $preferredType);
        libxml_use_internal_errors(true);
        $query = simplexml_load_string($input);
        if ($query === FALSE) {
            $errorMessage = "Input XML not properly formatted.\n";
            $errorMessage .= $this->common->libxml_display_errors();
            $ret = $this->business->returnGenericError($preferredType, $genericError, $errorMessage, $proxy_mode);
            if ('' === $proxy_mode)
                return $ret;
        }


        $namespaces = $query->getDocNamespaces();
        $query->registerXPathNamespace('u', $namespaces[""]);

        //echo $namespace . "\n";
        

        $selectedXsd = $this->findSchema($query);
        
        if (''!==$selectedXsd) {
            $selected = locate($matches, $selectedXsd, $input);
            if ($selected == -1) {
                $errorMessage = "Found match, but filtered out\n";
                $errorMessage .= "XSD = $selectedXsd";
                $this->common->mlog($errorMessage . "\n", 'INFO');
                $this->business->returnGenericError($preferredType, $genericError, $errorMessage, $proxy_mode);
            }
            $vars = $this->getVariables($query,$matches,$selected);

            $this->common->mlog("Match comment : " . $this->business->findMatch($matches, $selected, "comment") . "\n", 'INFO');
            
            $vars = array_merge($vars, $_GET);
            $this->common->log("Variables: " . print_r($vars, true) . "\n", 'INFO');
            $this->common->mlog("Selected template : " . $this->business->findMatch($matches, $selected, "responseTemplate") . "\n", 'INFO');

            $errorTemplate = $this->business->findMatch($matches, $selected, "errorTemplate");
            $errorTemplate = (($errorTemplate == null) ? $genericError : $errorTemplate);
            $errorTemplate = 'templates/' . $errorTemplate;
            if ($this->business->findMatch($matches, $selected, "displayError") === "On") {
                //echo trim(preg_replace('/\s+/', ' ', $errorOutput));
                $this->business->returnGenericError($preferredType, $errorTemplate, "Requested error", $proxy_mode);
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
                $forwardparams = $this->business->findMatch($matches, $selected, "destParameters");
                $multiple = true;
            }
            $eol = "\r\n";
            $mime_boundary = md5(time());
            $nrep = 0;
            foreach ($templates as $template) {
                $respxml = 'templates/' . $template;
                ob_start();
                include $respxml;
                $output = ob_get_contents();
                ob_end_clean();
                $outputxml = new DOMDocument();
                $outputxml->loadXML(preg_replace('/\s*(<[^>]*>)\s*/', '$1', $output));
                // $outputxml->loadXML($output);
                // die(print_r($output,true));
                if (!($outputxml->schemaValidate('xsd/' . $formats[$nrep]))) {
                    $errorMessage = "Could not validate output with " . $formats[$nrep] . "\n";
                    $errorMessage .= libxml_display_errors();
                    $this->common->mlog($errorMessage . "\n", 'ERROR');
                    $this->business->returnGenericError($preferredType, $errorTemplate, $errorMessage, $proxy_mode);
                }
                $outputxml->formatOutput = false;
                $outputxml->preserveWhiteSpace = false;
                if ($multiple)
                    $response .= $this->http->formMultiPart($template, convertOutData($outputxml->saveXML(), $preferredType), $mime_boundary, $eoli, $preferredType);
                else
                    $response = $outputxml->saveXML();
                $nrep++;
            }

            if ($forwardparams !== null && $forwardparams != "" && count($forwardparams[0]) > 0) {
                $this->common->mlog('params forward : ' . print_r($forwardparams, true), 'INFO');
                $fwd_params = array();
                if (is_array($forwardparams[0])) {
                    foreach ($forwardparams[0] as $forwardparam) {
                        $key = urlencode($forwardparam['key']);
                        $value = urlencode($forwardparam['value']);
                        $fwd_params[] = $key . '=' . $value;
                    }
                    $this->common->mlog('query out : ' . print_r($fwd_params, true), 'INFO');
                    if (stripos($proxy_mode, '?') === FALSE)
                        $proxy_mode .= '?';
                    else
                        $proxy_mode .= '&';
                    $proxy_mode .= implode('&', $fwd_params);
                }
            }

            if ($multiple) {
                $this->business->returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol, "multipart/form-data; boundary=$mime_boundary", 200);
            } else {
                $this->business->returnWithContentType($response, $preferredType, 200, $proxy_mode);
            }
        } else {
            $errorMessage = "Unable to find appropriate response.\n";
            $errorMessage .= $this->common->libxml_display_errors();
            $this->common->mlog($errorMessage . "\n", 'ERROR');
            $res = $this->business->returnGenericError($preferredType, $genericError, $errorMessage, $proxy_mode);
            if (''===$proxy_mode)
                return $res;
        }
    }
}
