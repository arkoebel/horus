<?php

use OpenTracing\Formats;
use OpenTracing\GlobalTracer;
use Jaeger\Config;

class HorusInjector
{

    public $common = null;
    public $http = null;
    private $business_id = '';
    private $tracer = null;

    function __construct($business_id, $log_location, $tracer)
    {
        $this->common = new HorusCommon($business_id, $log_location, 'YELLOW');
        $this->http = new HorusHttp($business_id, $log_location, 'YELLOW',$tracer);
        $this->business_id = $business_id;

        $this->tracer = $tracer;
    }

    function doInject($reqbody, $proxy_mode,$rootSpan=null)
    {
        $reqparams = json_decode($reqbody, true);
        $vars = array();
        if (array_key_exists('attr', $reqparams)) {
            foreach ($reqparams['attr'] as $key => $value){
                $vars[$key] = $value;
            }
        }
        $content = array();
        $this->common->mlog('Received request', 'INFO');
        for ($i = 0; $i < $reqparams['repeat']; $i++) {
            $lineSpan = $this->tracer->startSpan('Handle message ' . $i,['child_of'=>$rootSpan]);
            $vars['loop_index'] = $i;
            $template = 'templates/' . HorusBusiness::getTemplateName($reqparams['template'],$vars);
            $this->common->mlog("Using template " . $template, 'INFO');

            $lineSpan->log(['message'=>'Start template generation']);
            ob_start();
            include $template;
            $output = ob_get_contents();
            ob_end_clean();
            $lineSpan->log(['message'=>'End template generation']);
            if ("application/xml" === $reqparams['sourcetype']) {
                $outputxml = new DOMDocument();
                $outputxml->loadXML(preg_replace('/\s*(<[^>]*>)\s*/', '$1', $output));
                $outputxml->formatOutput = false;
                $outputxml->preserveWhiteSpace = false;
                $content[] = $outputxml->saveXML();
                $lineSpan->log(['message'=>'XML Output formatting']);
                $this->common->mlog("Generated XML Content: " . $outputxml->saveXML(), 'DEBUG', 'TXT', 'YELLOW');
            } else if ("application/json" === $reqparams['sourcetype']) {
                $outputjson = json_decode($output);
                $content[] = json_encode($outputjson);
                $lineSpan->log(['message'=>'JSON Output formatting']);
                $this->common->mlog('Generated JSON Content: "' . json_encode($outputjson) . '"', 'DEBUG', 'JSON', 'YELLOW');
            } else {
                $content[] = $output;
                $this->common->mlog("Generated TEXT Content: " . $output, 'DEBUG', 'TXT', 'YELLOW');
            }
            $lineSpan->finish();
        }
        $convert = false;
        if (("application/xml" === $reqparams['sourcetype']) && ("application/json" === $reqparams['destinationcontent'])) {
            $this->common->mlog("=== Conversion XML -> JSON ===", 'DEBUG', 'TXT', 'YELLOW');
            $convert = true;
        }
        $this->common->mlog('Generated all data', 'INFO', 'TXT', 'YELLOW');
        return $this->http->returnArrayWithContentType($content, $reqparams['destinationcontent'], 200, $proxy_mode, false, !$convert,'POST',$rootSpan);
    }
}
