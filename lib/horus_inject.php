<?php

class HorusInjector
{

    public $common = null;
    public $http = null;
    private $businessId = '';
    private ?HorusTracingInterface $tracer = null;

    public function __construct($businessId, $logLocation, HorusTracingInterface $tracer)
    {
        $this->common = new HorusCommon($businessId, $logLocation, 'YELLOW');
        $this->http = new HorusHttp($businessId, $logLocation, 'YELLOW', $tracer);
        $this->businessId = $businessId;

        $this->tracer = $tracer;
    }

    public function doInject($reqbody, $proxyMode, $rootSpan=null)
    {
        $reqparams = json_decode($reqbody, true);
        $vars = array();
        if (array_key_exists('attr', $reqparams)) {
            foreach ($reqparams['attr'] as $key => $value) {
                $vars[$key] = $value;
            }
        }
        $content = array();
        $this->common->mlog('Received request', 'INFO');
        for ($i = 0; $i < $reqparams['repeat']; $i++) {
            $lineSpan = $this->tracer->newSpan('Handle message ' . $i, $rootSpan);
            $vars['loop_index'] = $i;
            $template = 'templates/' . HorusBusiness::getTemplateName($reqparams['template'], $vars);
            $this->common->mlog("Using template " . $template, 'INFO');

            $this->tracer->logSpan($lineSpan, 'Start template generation');
            ob_start();
            include_once $template;
            $output = ob_get_contents();
            ob_end_clean();
            $this->tracer->logSpan($lineSpan, 'End template generation');
            if ("application/xml" === $reqparams['sourcetype']) {
                $outputxml = new DOMDocument();
                $outputxml->loadXML(preg_replace('/\s*(<[^>]*>)\s*/', '$1', $output));
                $outputxml->formatOutput = false;
                $outputxml->preserveWhiteSpace = false;
                $content[] = $outputxml->saveXML();
                $this->tracer->logSpan($lineSpan, 'XML Output formatting');
                $this->common->mlog("Generated XML Content: " . $outputxml->saveXML(), 'DEBUG', 'TXT', 'YELLOW');
            } elseif ("application/json" === $reqparams['sourcetype']) {
                $outputjson = json_decode($output);
                $content[] = json_encode($outputjson);
                $this->tracer->logSpan($lineSpan, 'JSON Output formatting');
                $this->common->mlog(
                    'Generated JSON Content: "' . json_encode($outputjson) . '"',
                    'DEBUG',
                    'JSON',
                    'YELLOW'
                );
            } else {
                $content[] = $output;
                $this->common->mlog("Generated TEXT Content: " . $output, 'DEBUG', 'TXT', 'YELLOW');
            }
            $this->tracer->closeSpan($lineSpan);
        }
        $convert = false;
        if ((HorusCommon::XML_CT === $reqparams['sourcetype']) &&
            (HorusCommon::JS_CT === $reqparams['destinationcontent'])) {
            $this->common->mlog("=== Conversion XML -> JSON ===", 'DEBUG', 'TXT', 'YELLOW');
            $convert = true;
        }
        $this->common->mlog('Generated all data', 'INFO', 'TXT', 'YELLOW');
        return $this
            ->http
            ->returnArrayWithContentType(
                $content,
                $reqparams['destinationcontent'],
                200,
                $proxyMode,
                false,
                !$convert,
                'POST',
                $rootSpan
            );
    }
}
