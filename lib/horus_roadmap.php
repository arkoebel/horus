<?php

require_once 'transforms/transformerInterface.php';
require_once 'mappers/mapperInterface.php';
require_once 'filters/horusFilterInterface.php';

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

    public static function getObjectInstance($interface, $plugin)
    {
        $loadedClasses = get_declared_classes();

        foreach ($loadedClasses as $className) {
            $reflectionClass = new ReflectionClass($className);
            if ($reflectionClass->implementsInterface($interface)) {
                return $reflectionClass->newInstance();
            }
        }
        throw new HorusException("Plugin $plugin doesn't implement interface $interface");
    }

    public function getAllNamespaces(SimpleXMLElement $xml)
    {
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
        try{
            $xml = simplexml_load_string($input);
            if ($xml === false) {
                throw new HorusException('Supplied input isn\'t XML');
            }
            $xp = $xml->xpath($xpath);
            if (($xp !== false) && (is_array($xp) && (sizeof($xp) == 1))) {
                return preg_match('/' . $pattern . '/', (string) $xp[0]);
            }
        }catch(Exception $e){
            $this->common->mlog('Error : ' . $e->getMessage(), 'DEBUG');
        }
        return false;

    }

    private function getFilterSource($selector, $input, $transformed){
        if (array_key_exists('filterAgainst', $selector)){
            return $selector['filterAgainst'] == 'transformed' ? $transformed : $input;
        } else {
            return $input;
        }
    }

    public function findRoadmap($source, $input, $span, $businessId)
    {
        $this->tracer->logSpan($span, 'Find Roadmap');

        foreach ($this->conf['roadmaps'] as $id => $roadmap) {
            $transformed = '';
            $this->common->mlog('Trying roadmap ' . $roadmap['comment'], 'INFO');
            if ((array_key_exists('source', $roadmap)) && ($source !== $roadmap['source'])) {
                $this->common->mlog('Rejected roadmap: wrong source', 'INFO');
            } elseif (array_key_exists('filter', $roadmap)) {
                $matchFilter = true;
                if(array_key_exists('preTransform', $roadmap)){
                    $this->tracer->logSpan($span, 'Transform input for filtering ' . $roadmap['preTransform']);
                    try{
                        $res = $this->http->forwardSingleHttpQuery(
                            $roadmap['preTransform'],
                            array($this->common::TID_HEADER => $businessId),
                            $input,
                            'POST',
                            $span);
                        $transformed = $res['body'];
                    }catch(Exception $e){
                        $this->common->mlog('HTTP Call failed. Error = ' . $e->getMessage(), 'ERROR');
                        $matchFilter = false;
                    }
                }
                if ($matchFilter) {
                    foreach ($roadmap['filter'] as $filter) {

                        if (array_key_exists('namespace', $filter)) {
                            $namespace = $filter['namespace'];
                            $this->common->mlog("Searching input for namespace " . $namespace, 'DEBUG');
                            $namespaceList = $this->getNamespaces(
                                $this->getFilterSource($filter, $input, $transformed)
                            );
                            
                            $found = false;
                            foreach ($namespaceList as $ns) {
                                
                                if (str_contains($ns, $namespace)) {
                                    $this->common->mlog("Input contains namespace " . $namespace, 'DEBUG');
                                    $found = true;
                                } else {
                                    // Nothing to say
                                }
                            }
                            if (!$found) {
                                $this->common->mlog('Failed namespace filter', 'DEBUG');
                                $matchFilter = false;
                                break;
                            }
                        } elseif (array_key_exists('xpathregexp', $filter)) {
                            $this->common->mlog(
                                'Searching input for Xpath regex ' .
                                $filter['xpathregexp']['pattern'],
                                'DEBUG');
                            if (
                                array_key_exists('xpath', $filter['xpathregexp']) &&
                                !$this->getXpathPattern(
                                    $this->getFilterSource($filter, $input, $transformed),
                                    $filter['xpathregexp']['xpath'],
                                    $filter['xpathregexp']['pattern']
                                )) {
                                $matchFilter = false;
                                $this->common->mlog('Failed regex filter', 'DEBUG');
                                break;
                            } else {
                                $this->common->mlog('Regex filter matched', 'DEBUG');
                            }
                        } elseif (array_key_exists('customFilter', $filter)) {
                            $this->common->mlog(
                                "Searching input for custom filter " .
                                $filter['customFilter'],
                                'DEBUG');
                            require_once 'filters/' . $filter['customFilter'];
                            try{
                                $object = HorusRoadmap::getObjectInstance(
                                    'HorusFilterInterface',
                                    $filter['customFilter']
                                );
                                if (!$object->doFilter(
                                        $this->getFilterSource($filter, $input, $transformed),
                                        $source)
                                    ) {
                                    $this->common->mlog('Failed custom filter ' . $filter['customFilter'], 'DEBUG');
                                    $matchFilter = false;
                                    break;
                                } else {
                                    $this->common->mlog('Matched custom filter ' . $filter['customFilter'], 'DEBUG');
                                }
                                unset($object);
                            }catch(Exception $e){
                                $matchFilter = false;
                                break;
                            }
                        } elseif (array_key_exists('regexp', $filter)){
                            $this->common->mlog("Searching input for regexp " . $filter['regexp'], 'DEBUG');
                            if(!preg_match($filter['regexp'], $this->getFilterSource($filter, $input, $transformed))){
                                $matchFilter = false;
                                break;
                            }
                        }
                    }
                }
                if ($matchFilter) {
                    $this->common->mlog('Roadmap ' . $roadmap['comment'] . ' matched', 'INFO');
                    return $id;
                }

            } else {
                $this->common->mlog(
                    'No source, no filter : Roadmap ' .
                    $roadmap['comment'] .
                    ' always matches',
                    'INFO');
                return $id;
            }
        }
        return null;

    }

    private function findDest($dest, $destinations)
    {
        foreach ($destinations as $dd) {
            if ($dd['name'] === $dest) {
                return $dd;
            }
        }
        return null;
    }

    private function putMessage($source, $dest, $data, $businessId, $span)
    {
        $nspan = $this->tracer->newSpan($dest['name'] . ' publish', $span);
        $this->tracer->addAttribute($nspan,'span.kind','PUBLISHER');
        $this->tracer->addAttribute($nspan,'messaging.system','kafka');
        $this->tracer->addAttribute($nspan,'messaging.operation','publish');
        $this->tracer->addAttribute($nspan,'messaging.destination.name',$dest['name']);
        
        $topic = $this->producer->newTopic($dest['name']);
        $headers = $this->tracer->getB3Headers($nspan);
        $headers['businessId'] = $businessId;
        $headers['destinationUrl'] = $dest['url'];
        $headers['source'] = $source;
        $topic->producev(RD_KAFKA_MSG_PARTITIONER_CONSISTENT_RANDOM, 0, $data, $businessId, $headers);
        $this->producer->poll(0);

        for ($flushRetries = 0; $flushRetries < 10; $flushRetries++) {
            $result = $this->producer->flush(10000);
            if (RD_KAFKA_RESP_ERR_NO_ERROR === $result) {
                break;
            }
        }

        if (RD_KAFKA_RESP_ERR_NO_ERROR !== $result) {
            $this->tracer->closeSpan($nspan);
            throw new \RuntimeException('Was unable to flush, messages might be lost!');
        }
        $this->tracer->closeSpan($nspan);
    }

    public function generateParts($source, $input, $mapId, $businessId, $span)
    {
        $this->common->mlog('Generating parts', 'DEBUG');
        $roadmap = $this->conf['roadmaps'][$mapId];

        $nMess = 0;

        $destinations = $this->conf['destinations'];
        if (array_key_exists('customRoadmap', $roadmap)) {
            $this->common->mlog('Applying custom roadmap', 'DEBUG');
            $this->tracer->logSpan($span, 'Applying custom roadmap ' . $roadmap['customRoadmap']);
            require_once 'mappers/' . $roadmap['customRoadmap'];
            try{
                $object = HorusRoadmap::getObjectInstance('HorusMapperInterface', $roadmap['customRoadmap']);
                $steps = $object->doMap($input, $source, $destinations);
                unset($object);
            }catch(Exception $e){
                $this->common->mlog('Error while generating roadmap : ' . $e->getMessage(), 'ERROR');
                throw new HorusException('Unable to generate roadmap : ' . $e->getMessage());
            }
        } else {
            $steps = $roadmap['map'];
        }
        foreach ($steps as $id => $dest) {

            $this->common->mlog(
                'Sending message ' . $id . ' to ' . $dest['destination'] . ' : ' . $dest['comment'], 'INFO');
            $transformed = '';
            $dd = $this->findDest($dest['destination'], $destinations);
            if (array_key_exists('transformUrl', $dest)) {
                $this->common->mlog('Custom transformation ' . $id . ' URL = ' . $dd['url'], 'DEBUG');
                $this->tracer->logSpan($span, 'Transform body ' . $id . ' http call ' . $dd['url']);
                
                try{
                    $res = $this->http->forwardSingleHttpQuery(
                        $dd['url'],
                        array($this->common::TID_HEADER => $businessId),
                        $input,
                        'POST',
                        $span);
                    $transformed = $res['body'];
                }catch(Exception $e){
                    $this->common->mlog('Unable to transform input (URL) : ' . $e->getMessage(), 'ERROR');
                    $transformed = '';
                }

            } elseif (array_key_exists('transform', $dest)) {
                $this->tracer->logSpan($span, 'Transform body ' . $id . ' local call ' . $dest['transform']);
                require_once 'transforms/' . $dest['transform'];
                $object = HorusRoadmap::getObjectInstance('HorusTransformerInterface',  $dest['transform']);
                try{
                    $transformed = $object->doTransform($input);
                    unset($object);
                }catch(Exception $e){
                    $this->common->mlog('Unable to transform input (custom) : ' . $e->getMessage(), 'ERROR');
                    $transformed = '';
                }
            } else {
                $transformed = $input;
            }

            $this->tracer->logSpan($span, 'Put body ' . $id . ' in queue ' . $dd['name']);
            if ($transformed !== '') {
                $this->common->mlog('Sending message to destination ' . $dd['name'], 'INFO');
                    
                $this->putMessage($source, $dd, $transformed, $businessId, $span);
                $nMess++;
            }
            if (array_key_exists('delay', $dest)){
                sleep($dest['delay']);
            }
        }
        return $nMess;
    }

}
