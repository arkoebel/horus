<?php

class HorusSimpleJson
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

    function selection($input, $content_type, $proxy_mode, $preferredType)
    {

        if ($input === null) {
            $error_message = 'JSON Error ' . decodeJsonError(json_last_error());
            $this->http->returnGenericJsonError($preferredType, 'templates/generic_error.json', $error_message, $proxy_mode);
        }

        $selected = $this->business->locateJson($this->simpleJsonMatches, $input, $_GET);
        if ($selected == -1) {
            $error_message = 'No match found';
            $this->business->returnGenericJsonError($preferredType, 'templates/generic_error.json', $error_message, $proxy_mode);
        } else {
            $this->common->mlog('Selected : ' . $selected, 'INFO');
        }

        $vars = array();
        foreach ($this->business->findMatch($this->simpleJsonMatches, $selected, 'parameters') as $param => $path) {
            $vars[$param] = $input[$path];
        }

        $errorTemplate = $this->business->findMatch($this->simpleJsonMatches, $selected, 'errorTemplate');
        $errorTemplate = (($errorTemplate == null) ? 'generic_error.json' : $errorTemplate);
        $errorTemplate = 'templates/' . $errorTemplate;
        if ($this->business->findMatch($this->simpleJsonMatches, $selected, "displayError") === "On") {
            $this->business->returnGenericJsonError($preferredType, $errorTemplate, "Requested error", $proxy_mode);
        }
        $response = '';
        $multiple = false;
        if (!is_array($this->business->findMatch($this->simpleJsonMatches, $selected, "responseTemplate"))) {
            $templates = array($this->business->findMatch($this->simpleJsonMatches, $selected, "responseTemplate"));
            $formats = array($this->business->findMatch($this->simpleJsonMatches, $selected, "responseFormat"));
        } else {
            $templates = $this->business->findMatch($this->simpleJsonMatches, $selected, "responseTemplate");
            $formats = $this->business->findMatch($this->simpleJsonMatches, $selected, "responseFormat");
            $multiple = true;
        }

        return array('templates' => $templates, 'formats' => $formats, 'variables' => $vars, 'multiple' => $multiple);
    }

    function doInject($reqbody, $content_type, $proxy_mode, $simpleJsonMatches, $preferredType, $queryParams)
    {
        $input = extractSimpleJsonPayload($reqbody);

        $res = selection($input, $content_type, $proxy_mode, $preferredType);
        $vars = array_merge($res['variables'], $queryParams);

        $eol = "\r\n";
        $mime_boundary = md5(time());
        $nrep = 0;
        foreach ($res['templates'] as $template) {
            $respxml = 'templates/' . $template;
            ob_start();
            include $respxml;
            $output = ob_get_contents();
            ob_end_clean();
            if ($res['multiple'])
                $response .= $this->http->formMultiPart($template, convertOutData($output, $preferredType, true), $mime_boundary, $eol, $preferredType);
            else
                $response = $output;
            $nrep++;
        }
        if ($multiple) {
            $this->http->returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol, "multipart/form-data; boundary=$mime_boundary", 200, $proxy_mode, true, true);
        } else {
            $this->http->returnWithContentType($response, $preferredType, 200, $proxy_mode, true, true);
        }
    }
}
