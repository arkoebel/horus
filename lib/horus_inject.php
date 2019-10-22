<?php

class HorusInjector
{

    public $common = null;
    public $http = null;
    private $business_id = '';

    function __construct($business_id, $log_location)
    {
        $this->common = new HorusCommon($business_id, $log_location, 'YELLOW');
        $this->http = new HorusHttp($business_id, $log_location, 'YELLOW');
        $this->business_id = $business_id;
    }

    function doInject($reqbody, $proxy_mode)
    {
        $reqparams = json_decode($reqbody, true);
        $template = 'templates/' . $reqparams['template'];
        $vars = array();
        if (array_key_exists('attr',$reqparams)){
            foreach ($reqparams['attr'] as $key => $value)
                $vars[$key] = $value;
        }
        $content = array();
        $this->common->mlog('Received request', 'INFO');
        for ($i = 0; $i < $reqparams['repeat']; $i++) {
            $vars['loop_index'] = $i;
            ob_start();
            include $template;
            $output = ob_get_contents();
            ob_end_clean();
            if ("application/xml" === $reqparams['sourcetype']) {
                $outputxml = new DOMDocument();
                $outputxml->loadXML(preg_replace('/\s*(<[^>]*>)\s*/', '$1', $output));
                $outputxml->formatOutput = false;
                $outputxml->preserveWhiteSpace = false;
                $content[] = $outputxml->saveXML();
                $this->common->mlog("Generated XML Content: " . $outputxml->saveXML(), 'DEBUG', 'TXT', 'YELLOW');
            } else if ("application/json" === $reqparams['sourcetype']) {
                $outputjson = json_decode($output);
                $content[] = json_encode($outputjson);
                $this->common->mlog('Generated JSON Content: "' . json_encode($outputjson) . '"', 'DEBUG', 'JSON', 'YELLOW');
            } else {
                $content[] = $output;
                $this->common->mlog("Generated TEXT Content: " . $output, 'DEBUG', 'TXT', 'YELLOW');
            }
        }
        $convert = false;
        if (("application/xml" === $reqparams['sourcetype']) && ("application/json" === $reqparams['destinationcontent'])) {
            $this->common->mlog("=== Conversion XML -> JSON ===", 'DEBUG', 'TXT', 'YELLOW');
            $convert = true;
        }
        $this->common->mlog('Generated all data', 'INFO', 'TXT', 'YELLOW');
        return $this->http->returnArrayWithContentType($content, $reqparams['destinationcontent'], 200, $proxy_mode, false, null, !$convert);
    }
}
