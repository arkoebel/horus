<?php

class HorusSimpleJson
{

    public $common = null;
    public $http = null;
    public $business = null;
    private $businessId = '';
    private $simpleJsonMatches = null;
    private ?HorusTracingInterface $tracer = null;

    public function __construct($businessId, $logLocation, $matches, HorusTracingInterface $tracer)
    {
        $this->common = new HorusCommon($businessId, $logLocation, 'GREEN');
        $this->http = new HorusHttp($businessId, $logLocation, 'GREEN', $tracer);
        $this->business = new HorusBusiness($businessId, $logLocation, 'GREEN', $tracer);
        $this->businessId = $businessId;
        $this->simpleJsonMatches = $matches;

        $this->tracer = $tracer;
    }

    public function selection($input, $proxyMode, $preferredType, $span)
    {

        if ($input === null) {
            $errorMessage = 'JSON Error ' . $this->common->decodeJsonError(json_last_error());
            throw new HorusException(
                $this->business->returnGenericJsonError(
                    $preferredType,
                    'templates/generic_error.json',
                    $errorMessage,
                    '',
                    $span
                    )
                );
        }

        $selected = $this->business->locateJson($this->simpleJsonMatches, $input, $_GET);
        if ($selected == -1) {
            $errorMessage = 'No match found';
            throw new HorusException(
                $this->business->returnGenericJsonError(
                    $preferredType,
                    'templates/generic_error.json',
                    $errorMessage,
                    '',
                    $span
                    )
                );
        } else {
            $this->common->mlog('Selected : ' . $selected, 'INFO');
        }

        $vars = array();
        if ($this->business->findMatch(
            $this->simpleJsonMatches,
            $selected,
            'parameters'
            )!=="") {
            foreach ($this->business->findMatch($this->simpleJsonMatches, $selected, 'parameters') as $param => $path) {
                $vars[$param] = $input[$path];
            }
        }

        $errorTemplate = $this->business->findMatch($this->simpleJsonMatches, $selected, 'errorTemplate');
        $errorTemplate = (($errorTemplate == null) ? 'generic_error.json' : $errorTemplate);
        $errorTemplate = 'templates/' . $errorTemplate;
        if ($this->business->findMatch($this->simpleJsonMatches, $selected, "displayError") === "On") {
            throw new HorusException(
                $this->business->returnGenericJsonError(
                    $preferredType,
                    $errorTemplate,
                    "Requested error",
                    $proxyMode,
                    $span
                ));
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

    public function doInject($reqbody, $proxyMode, $preferredType, $queryParams, $injectSpan)
    {
        $this->tracer->logSpan($injectSpan, 'Inject JSON Lib');
        $input = $this->business->extractSimpleJsonPayload($reqbody);

        try {
            $this->tracer->logSpan($injectSpan, 'Find section');
            $res = $this->selection($input, $proxyMode, $preferredType, $injectSpan);
        } catch (HorusException $e) {
            throw new HorusException($e->getMessage());
        }
        if (''=== $proxyMode && !is_array($res)) {
            return $res;
        }

        $vars = array_merge($res['variables'], $queryParams);

        $eol = "\r\n";
        $mimeBoundary = md5(time());
        $nrep = 0;
        $response = '';
        foreach ($res['templates'] as $template) {
            $respxml = 'templates/' . HorusBusiness::getTemplateName($template, $vars);
            $this->common->mlog("Using template " . $respxml, 'INFO');
            $this->tracer->logSpan($injectSpan, 'Generate template ' . $respxml);

            ob_start();
            include_once $respxml;
            $output = ob_get_contents();
            ob_end_clean();
            $this->tracer->logSpan($injectSpan, 'Generate Output');
            if ($res['multiple']) {
                $response .= $this->http->formMultiPart(
                    $template,
                    $this->http->convertOutData($output, $preferredType, true),
                    $mimeBoundary,
                    $eol,
                    $preferredType
                );
            } else {
                $response = $output;
            }
            $nrep++;
        }
        $outres = null;
        $this->tracer->logSpan($injectSpan, 'Generate Out Queries');
        if ($res['multiple']) {
            $outres = $this->http->returnWithContentType(
                $response . "--" . $mimeBoundary . "--" . $eol . $eol,
                "multipart/form-data; boundary=$mimeBoundary",
                200,
                $proxyMode,
                true,
                'POST',
                array(),
                $injectSpan
            );
        } else {
            $outres = $this->http->returnWithContentType(
                $response,
                $preferredType,
                200,
                $proxyMode,
                true,
                'POST',
                array(),
                $injectSpan
            );
        }
        if ('' === $proxyMode) {
            return $outres;
        }
        $this->tracer->closeSpan($injectSpan);
    }
}
