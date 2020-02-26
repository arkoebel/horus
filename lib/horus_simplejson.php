<?php

class HorusSimpleJson
{

    public $common = null;
    public $http = null;
    public $business = null;
    private $business_id = '';
    private $simpleJsonMatches = null;

    function __construct($business_id, $log_location, $matches) 
    {
        $this->common = new HorusCommon($business_id, $log_location, 'GREEN');
        $this->http = new HorusHttp($business_id, $log_location, 'GREEN');
        $this->business = new HorusBusiness($business_id, $log_location, 'GREEN');
        $this->business_id = $business_id;
        $this->simpleJsonMatches = $matches;
    }

    function selection($input, $proxy_mode, $preferredType)
    {

        if ($input === null) {
            $error_message = 'JSON Error ' . $this->common->decodeJsonError(json_last_error());
            throw new HorusException($this->business->returnGenericJsonError($preferredType, 'templates/generic_error.json', $error_message,''));
        }

        $selected = $this->business->locateJson($this->simpleJsonMatches, $input, $_GET);
        if ($selected == -1) {
            $error_message = 'No match found';
            throw new HorusException($this->business->returnGenericJsonError($preferredType, 'templates/generic_error.json', $error_message, ''));
        } else {
            $this->common->mlog('Selected : ' . $selected, 'INFO');
        }

        $vars = array();
        if(!($this->business->findMatch($this->simpleJsonMatches, $selected, 'parameters')==="")){
            foreach ($this->business->findMatch($this->simpleJsonMatches, $selected, 'parameters') as $param => $path) {
                $vars[$param] = $input[$path];
            }
        }

        $errorTemplate = $this->business->findMatch($this->simpleJsonMatches, $selected, 'errorTemplate');
        $errorTemplate = (($errorTemplate == null) ? 'generic_error.json' : $errorTemplate);
        $errorTemplate = 'templates/' . $errorTemplate;
        if ($this->business->findMatch($this->simpleJsonMatches, $selected, "displayError") === "On") {
            throw new HorusException($this->business->returnGenericJsonError($preferredType, $errorTemplate, "Requested error", $proxy_mode));
        }
        
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

    function doInject($reqbody, $proxy_mode, $preferredType, $queryParams)
    {
        $input = $this->business->extractSimpleJsonPayload($reqbody);

        try{
            $res = $this->selection($input, $proxy_mode, $preferredType);
        }catch(HorusException $e){
            throw new HorusException($e->getMessage());
        }
        if (''=== $proxy_mode && !is_array($res)){
            return $res;
        }

        $vars = array_merge($res['variables'], $queryParams);

        $eol = "\r\n";
        $mime_boundary = md5(time());
        $nrep = 0;
        $response = '';
        foreach ($res['templates'] as $template) {
            $respxml = 'templates/' . HorusBusiness::getTemplateName($template,$vars);
            $this->common->mlog("Using template " . $respxml, 'INFO');

            ob_start();
            include $respxml;
            $output = ob_get_contents();
            ob_end_clean();
            if ($res['multiple']){
                $response .= $this->http->formMultiPart($template, $this->http->convertOutData($output, $preferredType, true), $mime_boundary, $eol, $preferredType);
            }else{
                $response = $output;
            }
            $nrep++;
        }
        $outres = null;
        if ($res['multiple']) {
            $outres = $this->http->returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol, "multipart/form-data; boundary=$mime_boundary", 200, $proxy_mode, true);
        } else {
            $outres = $this->http->returnWithContentType($response, $preferredType, 200, $proxy_mode, true);
        }
        if('' === $proxy_mode){
            return $outres;
        }
    }
}
