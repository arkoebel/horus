<?php

class HorusXml
{
    private $common = null;
    private $http = null;
    private $business = null;
    private $business_id = '';
    private $simpleJsonMatches = null;

    function __construct($business_id, $log_location)
    {
        $this->common = new HorusCommon($business_id, $log_location, 'GREEN');
        $this->http = new HorusHttp($business_id, $log_location, 'GREEN');
        $this->business = new HorusBusiness($business_id, $log_location, 'GREEN');
        $this->business_id = $business_id;
    }

    function doInject($reqbody, $content_type, $proxy_mode, $matches, $preferredType, $queryParams, $genericError)
    {
        $input = $this->business->extractPayload($content_type, $reqbody, $genericError, $preferredType);
        libxml_use_internal_errors(true);
        $query = simplexml_load_string($input);
        if ($query === FALSE) {
            $errorMessage = "Input XML not properly formatted.\n";
            $errorMessage .= libxml_display_errors();
            returnGenericError($preferredType, $genericError, $errorMessage, $proxy_mode);
        }


        $namespaces = $query->getDocNamespaces();
        $query->registerXPathNamespace('u', $namespaces[""]);

        $mnamespaces = explode(':', $namespaces[""]);

        $namespace = array_pop($mnamespaces);
        //echo $namespace . "\n";
        $domelement = dom_import_simplexml($query);
        $domdoc = $domelement->ownerDocument;

        $valid = false;
        $selectedXsd = "";
        foreach (scandir('../xsd') as $schema) {
            if (!(strpos($schema, $namespace) === false)) {
                libxml_use_internal_errors(true);
                if ($domdoc->schemaValidate('../xsd/' . $schema)) {
                    $valid = true;
                    $selectedXsd = $schema;
                } else {
                    //echo "Not validated with : $schema\n";
                }
            } else {
                //echo "skipping $schema\n";
            }
        }
        $this->common->mlog("schema=$selectedXsd\n", 'DEBUG');
        if ($valid) {
            $selected = locate($matches, $selectedXsd, $input);
            if ($selected == -1) {
                $errorMessage = "Found match, but filtered out\n";
                $errorMessage .= "XSD = $selectedXsd";
                mlog($errorMessage . "\n", 'INFO');
                returnGenericError($preferredType, $genericError, $errorMessage, $proxy_mode);
            }
            $vars = array();
            mlog("Match comment : " . findMatch($matches, $selected, "comment") . "\n", 'INFO');
            foreach (findMatch($matches, $selected, "parameters") as $param => $path) {
                $query->registerXPathNamespace('u', $namespaces[""]);
                $rr = ($query->xpath($path));
                if (!($rr === FALSE)) {
                    $rr0 = $rr[0];
                    if ($rr0->count() != 0) {
                        $vars[$param] = $rr0->asXml();
                    } else {
                        $vars[$param] = (string) $rr0;
                    }
                }
            }
            $vars = array_merge($vars, $_GET);
            mlog("Variables: " . print_r($vars, true) . "\n", 'INFO');
            mlog("Selected template : " . findMatch($matches, $selected, "responseTemplate") . "\n", 'INFO');

            $errorTemplate = findMatch($matches, $selected, "errorTemplate");
            $errorTemplate = (($errorTemplate == null) ? $genericError : $errorTemplate);
            $errorTemplate = 'templates/' . $errorTemplate;
            if (findMatch($matches, $selected, "displayError") === "On") {
                //echo trim(preg_replace('/\s+/', ' ', $errorOutput));
                returnGenericError($preferredType, $errorTemplate, "Requested error", $proxy_mode);
            }
            $response = '';
            $multiple = false;
            if (!is_array(findMatch($matches, $selected, "responseTemplate"))) {
                $templates = array(findMatch($matches, $selected, "responseTemplate"));
                $formats = array(findMatch($matches, $selected, "responseFormat"));
                $forwardparams = array(findMatch($matches, $selected, "destParameters"));
            } else {
                $templates = findMatch($matches, $selected, "responseTemplate");
                $formats = findMatch($matches, $selected, "responseFormat");
                $forwardparams = findMatch($matches, $selected, "destParameters");
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
                    mlog($errorMessage . "\n", 'ERROR');
                    returnGenericError($preferredType, $errorTemplate, $errorMessage, $proxy_mode);
                }
                $outputxml->formatOutput = false;
                $outputxml->preserveWhiteSpace = false;
                if ($multiple)
                    $response .= formMultiPart($template, convertOutData($outputxml->saveXML(), $preferredType), $mime_boundary, $eoli, $preferredType);
                else
                    $response = $outputxml->saveXML();
                $nrep++;
            }

            if ($forwardparams !== null && $forwardparams != "" && count($forwardparams[0]) > 0) {
                mlog('params forward : ' . print_r($forwardparams, true), 'INFO');
                $fwd_params = array();
                if (is_array($forwardparams[0])) {
                    foreach ($forwardparams[0] as $forwardparam) {
                        $key = urlencode($forwardparam['key']);
                        $value = urlencode($forwardparam['value']);
                        $fwd_params[] = $key . '=' . $value;
                    }
                    mlog('query out : ' . print_r($fwd_params, true), 'INFO');
                    if (stripos($proxy_mode, '?') === FALSE)
                        $proxy_mode .= '?';
                    else
                        $proxy_mode .= '&';
                    $proxy_mode .= implode('&', $fwd_params);
                }
            }

            if ($multiple) {
                returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol, "multipart/form-data; boundary=$mime_boundary", 200);
            } else {
                returnWithContentType($response, $preferredType, 200, $proxy_mode);
            }
        } else {
            $errorMessage = "Unable to find appropriate response.\n";
            $errorMessage .= libxml_display_errors();
            mlog($errorMessage . "\n", 'ERROR');
            returnGenericError($preferredType, $genericError, $errorMessage, $proxy_mode);
        }
    }
}
