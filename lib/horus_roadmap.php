<?php

class HorusRoadmap
{

    public $common = '';
    public $http = '';
    public $conf = array();
    private $businessId = '';
    public ?HorusTracingInterface $tracer = null;
    private $producer = null;

    public function __construct(
        $businessId,
        $logLocation,
        $colour,
        HorusTracingInterface $tracer,
        Horus_CurlInterface $httpImpl = null
    ) {
        $this->businessId = $businessId;
        $this->common = new HorusCommon($businessId, $logLocation, $colour);
        if (is_null($httpImpl)) {
            $httpImpl = new Horus_Curl();
        }
        $this->http = new HorusHttp($businessId, $logLocation, $colour, $tracer, $httpImpl);
        $this->tracer = $tracer;
        $this->conf = json_decode(file_get_contents('conf/horusRoadmap.json'), true);
        if ($this->conf === null) {
            $this->common->mlog('Invalid configuration file', 'FATAL');
            throw new HorusException('Invalid configuration file');
        }
        $kafkaCnf = new RdKafka\Conf();
        $kafkaCnf->set('metadata.broker.list', $this->common->cnf['broker.list']);
        $this->producer = new RdKafka\Producer($kafkaCnf);

    }

    function getAllNamespaces(SimpleXMLElement $xml) {
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $namespaces = array();
    
        // Extract namespaces from the root element
        $rootNamespace = $dom->documentElement->lookupNamespaceUri(null);
        if ($rootNamespace && $rootNamespace !== "http://www.w3.org/XML/1998/namespace") {
            $namespaces[] = $rootNamespace;
        }
    
        // Extract namespaces from all elements and attributes
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//@* | //namespace::*', $dom) as $node) {
            $namespace = $node->nodeValue;
            if ($namespace !== "http://www.w3.org/XML/1998/namespace" && !in_array($namespace, $namespaces)) {
                $namespaces[] = $namespace;
            }
        }
    
        return $namespaces;
    }    
    

    private function getNamespaces($input)
    {
        $xml = simplexml_load_string($input);
        if ($xml === false) {
            throw new HorusException('Supplied input isn\'t XML');
        }
        
        return $this->getAllNamespaces($xml);
    }

    private function getXpathPattern($input, $xpath, $pattern)
    {
        $xml = simplexml_load_string($input);
        if ($xml === false) {
            throw new HorusException('Supplied input isn\'t XML');
        }
        $xp = $xml->xpath($xpath);
        if (($xp !== false) && (is_array($xp) && (sizeof($xp) == 1))) {
            return preg_match($pattern, (string) $xp[0]);
        }

        return false;

    }

    public function findRoadmap($source, $input, $span)
    {
        $this->tracer->logSpan($span, 'Find Roadmap');

        foreach ($this->conf['roadmaps'] as $id => $roadmap) {
            if ((array_key_exists('source', $roadmap)) && ($source !== $roadmap['source'])) {
                break;
            }

            if (array_key_exists('filter', $roadmap)) {
                $matchFilter = true;
                foreach ($roadmap['filter'] as $filter) {
                    if (array_key_exists('namespace', $filter)) {
                        $namespace = $filter['namespace'];
                        $this->common->mlog("Searching input for " . $namespace, 'DEBUG');
                        $namespaceList = $this->getNamespaces($input);
                        error_log(print_r($namespaceList, true));
                        $found = false;
                        foreach ($namespaceList as $ns) {
                            error_log(' ' . $ns );
                            if (str_contains($ns, $namespace)) {
                                $this->common->mlog("Input contains " . $namespace, 'DEBUG');
                                $found = true;
                            }
                        }
                        if (!$found) {
                            $matchFilter = false;
                            break;
                        }
                    } elseif (array_key_exists('regex', $filter)) {
                        if (
                            array_key_exists('xpath', $filter['regex']) &&
                            !$this->getXpathPattern($input, $filter['regex']['xpath'], $filter['regex']['pattern'])) {
                                $matchFilter = false;
                                break;
                        }
                    }
                }
                if($matchFilter) {
                    return $id;
                }
            }
        }
        return null;

    }

    private function findDest($dest, $destinations) {
        foreach ($destinations as $id => $dd){
            if ($dd['name'] === $dest) {
                return $dd;
            }
        }
        return null;
    }

    private function putMessage($dest, $data, $businessId, $span ){

        $producer = new RdKafka\Producer($this->conf);
        $topic = $producer->newTopic($dest['name']);
        $headers = $this->tracer->getB3Headers($span);
        $headers['businessId'] = $businessId;
        $headers['destinationUrl'] = $dest['url'];
        $topic->producev(RD_KAFKA_PARTITION_UA, 0, $data, $businessId, $headers);
        $producer->poll(0);


        for ($flushRetries = 0; $flushRetries < 10; $flushRetries++) {
            $result = $producer->flush(10000);
            if (RD_KAFKA_RESP_ERR_NO_ERROR === $result) {
                break;
            }
        }

        if (RD_KAFKA_RESP_ERR_NO_ERROR !== $result) {
            throw new \RuntimeException('Was unable to flush, messages might be lost!');
        }
    }
    
    public function generateParts($input, $mapId, $businessId, $span){
        $roadmap = $this->conf['roadmaps'][$mapId];
        error_log('roadmap : ' . print_r($roadmap,true));
        $destinations = $this->conf['destinations'];
        foreach($roadmap['map'] as $id => $dest){

            error_log('id=' . $id . "\n");
            error_log('dest=' . print_r($dest,true) . "\n");
            $this->common->mlog(
                'Sending message ' . $id  . ' to ' . $dest['destination'] . ' : ' . $dest['comment'], 'INFO');
            $transformed = '';
            $dd = $this->findDest($dest['destination'], $destinations);
            if(array_key_exists('transformUrl',$dest)) {
                $transformed = $this->http->forwardSingleHttpQuery(
                    $dd['url'],
                    array($this->common::TID_HEADER => $businessId),
                    $input,
                    'POST',
                    $span);
            } elseif (array_key_exists('transform', $dest)) {
                $toEvaluate = $input;
                include_once 'transforms/' . $dest['transform'];
                $transformed = $evaluated;
            } else {
                $transformed = $input;
            }

            $this->putMessage($dd, $transformed, $businessId, $span);
        }
    }

}
