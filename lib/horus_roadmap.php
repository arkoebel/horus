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
        // we can also set the buffering time, so we dispatch asap:
        $kafkaCnf->set('queue.buffering.max.ms', 1);
        // Finally, we can say to only wait for messages before sending to Kafka.
        //In a lightweight / custom app, you may only be sending one message, so get it out there straight away
        $kafkaCnf->set('queue.buffering.max.messages', 10);

        // Set the delivery message function in case there is an error:
        // This is just a callback so you can easily do something like ($this, 'myFunctionName') as the callback
        $kafkaCnf->setDrMsgCb(function (RdKafka\Producer $kafka, RdKafka\Message $message) {
            if ($message->err) {
            // message permanently failed to be delivered
            // Do something with this knowledge
            throw new \RuntimeException('Was unable to flush, messages might be lost!');
            } else {
            // message successfully delivered
            }
        });
 
        $this->producer = new RdKafka\Producer($kafkaCnf);

    }

    public static function getObjectInstance($interface, $plugin)
    {
        $loadedClasses = get_declared_classes();

        foreach ($loadedClasses as $className) {
            $reflectionClass = new ReflectionClass($className);
            if (str_ends_with($reflectionClass->getFileName(), $plugin)
                    && $reflectionClass->implementsInterface($interface)) {
                return $reflectionClass->newInstance();
            }
        }
        throw new HorusException("Plugin $plugin doesn't implement interface $interface");
    }

    public function testNamespaceFilter($namespace, $input, $negate)
    {
        $this->common->mlog("Searching input for namespace " . $namespace, 'DEBUG');
        $namespaceList = $this->getNamespaces($input);
        $found = false;
        foreach ($namespaceList as $ns) {
            if (str_contains($ns, $namespace)) {
                $this->common->mlog("Input contains namespace " . $namespace, 'DEBUG');
                $found = true;
            } else {
                // Nothing to say
            }
        }

        if(!$found){
            $this->common->mlog('Failed namespace filter', 'DEBUG');
        }

        if($negate==='true'){
            $found = !$found;
        }
        
        return !$found;
    }

    public function testXpathRegexpFilter($xpath, $input, $negate){
        $this->common->mlog(
            'Searching input for Xpath regex ' .
            $xpath['pattern'] . ' at node ' . $xpath['xpath'],
            'DEBUG');
        $rr = true;
        if (array_key_exists('xpath', $xpath))
        {
            if($this->getXpathPattern(
                $input,
                $xpath['xpath'],
                $xpath['pattern']
            )){
                error_log('regex ' . $xpath['pattern'] . ' match');
                $rr = false;
            }else{
                error_log('regex ' . $xpath['pattern'] . ' no match');
                $rr = true;
            }
        }

        if($rr){
            $this->common->mlog('Failed regex filter', 'DEBUG');
        }
        if($negate==='true'){
            $rr = !$rr;
        }
        return $rr;
    }

    public function testCustomFilter($filter, $input, $negate, $source, $headers, $queryparams)
    {
        $this->common->mlog(
            "Searching input for custom filter " .
            $filter,
            'DEBUG');
        require_once 'filters/' . $filter;
        $object = HorusRoadmap::getObjectInstance('HorusFilterInterface',$filter);
        $rr = !$object->doFilter($input, $source, $headers, $queryparams);
        unset($object);

        if (!$rr){
            $this->common->mlog('Failed custom filter ' . $filter, 'DEBUG');
        }
        if($negate==='true'){
            $rr = !$rr;
        }
        return $rr;
    }

    public function testRegexpFilter($regexp, $input, $negate){
        $this->common->mlog("Searching input for regexp " . $regexp, 'DEBUG');
        $rr = !preg_match($regexp, $input);
        if($negate==='true'){
            $rr = !$rr;
        }
        return $rr;
    }

    public function testJsonKeyExists($key, $input, $negate){
        $this->common->mlog("Searching JSON Key " . $key, 'DEBUG');
        $json = json_decode($input, true);
        if ($json === false ){
            $this->common->mlog("Input isn't JSON ", 'DEBUG');
            return true;
        }
        $rr = !array_key_exists($key, $json);

        if($negate==='true'){
            $rr = !$rr;
        }
        return $rr;
    }

    public function testJsonRegexp($pattern, $input, $negate, $key){
        $this->common->mlog("Searching JSON pattern " . $pattern . ' in ' . $key, 'DEBUG');
        $json = json_decode($input, true);
        if ($json === false ){
            $this->common->mlog("Input isn't JSON ", 'DEBUG');
            return true;
        }
        $rr = array_key_exists($key, $json) && ! preg_match($pattern, $json[$key]);

        if($negate==='true'){
            $rr = !$rr;
        }
        return $rr;
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

    public function findRoadmap($source, $input, $span, $businessId, $headers, $queryparams)
    {
        $this->tracer->logSpan($span, 'Find Roadmap');

        foreach ($this->conf['roadmaps'] as $id => $roadmap) {
            $transformed = '';
            $this->common->mlog('Trying roadmap ' . $roadmap['comment'], 'INFO');
            if ((array_key_exists('source', $roadmap)) && ($source !== $roadmap['source'])) {
                $this->common->mlog('Rejected roadmap: wrong source', 'INFO');
            } elseif (array_key_exists('filter', $roadmap)) {
                $matchFilter = true;
                if(array_key_exists('preTransformUrl', $roadmap)){
                    $this->tracer->logSpan(
                        $span,
                        'Transform input for filtering with URL ' . $roadmap['preTransformUrl']
                    );
                    $url = HorusCommon::formatQueryString($roadmap['preTransformUrl'], $queryparams, true);
                    try{
                        $res = $this->http->forwardSingleHttpQuery(
                            $url,
                            array_merge(array($this->common::TID_HEADER => $businessId), $headers),
                            $input,
                            'POST',
                            $span);
                        $transformed = $res['body'];
                    }catch(Exception $e){
                        $this->common->mlog('HTTP Call failed. Error = ' . $e->getMessage(), 'ERROR');
                        $matchFilter = false;
                    }
                }elseif (array_key_exists('preTransform', $roadmap)){
                    $this->tracer->logSpan(
                        $span,
                        'Transform input for filtering with custom file ' . $roadmap['preTransform']);
                        require_once 'transforms/' . $roadmap['preTransform'];
                        $object = HorusRoadmap::getObjectInstance(
                                'HorusTransformerInterface',
                                $roadmap['preTransform']
                            );
                        try{
                            $transformed = $object->doTransform($input, $headers, $queryparams);
                            unset($object);
                        }catch(Exception $e){
                            $this->common->mlog('Unable to transform input (custom) : ' . $e->getMessage(), 'ERROR');
                            $transformed = '';
                        }
                }
                if ($matchFilter) {
                    foreach ($roadmap['filter'] as $filter) {
                        $selectedInput = $this->getFilterSource($filter, $input, $transformed);
                        $negate = array_key_exists('negate', $filter) ? $filter['negate'] : 'false';
                        $rr = false;

                        if (array_key_exists('namespace', $filter)) {
                            $rr = $this->testNameSpaceFilter($filter['namespace'], $selectedInput, $negate);
                        } elseif (array_key_exists('xpathregexp', $filter)) {
                            $rr = $this->testXpathRegexpFilter(
                                $filter['xpathregexp'], $selectedInput, $negate);
                        } elseif (array_key_exists('customFilter', $filter)) {
                            try{
                                $rr = $this->testCustomFilter(
                                    $filter['customFilter'], 
                                    $selectedInput,
                                    $negate,
                                    $source,
                                    $headers,
                                    $queryparams);
                            }catch(Exception $e){
                                $rr = true;
                            }
                        } elseif (array_key_exists('jsonkeyexists', $filter)){
                            $rr = $this->testJsonKeyExists($filter['jsonkeyexists'], $selectedInput, $negate);
                        } elseif (array_key_exists('jsonpattern', $filter)){
                            $rr = $this->testJsonRegexp(
                                $filter['jsonpattern'], $selectedInput, $negate, $filter['jsonkey']);
                        } elseif (array_key_exists('regexp', $filter)){
                            $rr = $this->testRegexpFilter($filter['regexp'], $selectedInput, $negate);
                        }

                        if ($rr)
                        {
                            $matchFilter = false;
                            break;
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

    private function putMessage($source, $dest, $data, $businessId, $span, $inheaders, $queryparams)
    {
        $nspan = $this->tracer->newSpan($dest['name'] . ' publish', $span);
        $this->tracer->addAttribute($nspan,'span.kind','PUBLISHER');
        $this->tracer->addAttribute($nspan,'messaging.system','kafka');
        $this->tracer->addAttribute($nspan,'messaging.operation','publish');
        $this->tracer->addAttribute($nspan,'messaging.destination.name',$dest['name']);

        // Set the topic configuration:
        $topicConfig = new RdKafka\TopicConf();
        $topicConfig->set('message.timeout.ms', 1000);
        
        $topic = $this->producer->newTopic($dest['name'], $topicConfig);
        $headers = $this->tracer->getB3Headers($nspan);
        $headers['businessId'] = $businessId;
        $headers['destinationUrl'] = HorusCommon::formatQueryString($dest['url'], $queryparams, true);
        $headers['source'] = $source;
        $headers['httpheaders'] = HorusCommon::implodeAssArray($inheaders,'##','||');
        $topic->producev(RD_KAFKA_MSG_PARTITIONER_CONSISTENT_RANDOM, 0, $data, $businessId, $headers);
        $this->producer->poll(-1);

        /*
        for ($flushRetries = 0; $flushRetries < 10; $flushRetries++) {
            $result = $this->producer->flush(10000);
            if (RD_KAFKA_RESP_ERR_NO_ERROR === $result) {
                break;
            }
        }*/

        /*if (RD_KAFKA_RESP_ERR_NO_ERROR !== $result) {
            $this->tracer->closeSpan($nspan);
            throw new \RuntimeException('Was unable to flush, messages might be lost!');
        }*/
        $this->tracer->closeSpan($nspan);
    }

    public function generateParts($source, $input, $mapId, $businessId, $span, $headers, $queryparams)
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
                $steps = $object->doMap($input, $source, $destinations, $headers, $queryparams);
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
                $url = HorusCommon::formatQueryString($dest['transformUrl'], $queryparams, true);
                $this->common->mlog('Custom transformation ' . $id . ' URL = ' . $url, 'DEBUG');
                $this->tracer->logSpan($span, 'Transform body ' . $id . ' http call ' . $url);
                
                try{
                    $res = $this->http->forwardSingleHttpQuery(
                        $url,
                        array_merge(array($this->common::TID_HEADER => $businessId), $headers),
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
                error_log(' To transform for ' . $dest['transform'] . ' : ' . $input);
                require_once 'transforms/' . $dest['transform'];
                $object = HorusRoadmap::getObjectInstance('HorusTransformerInterface',  $dest['transform']);
                try{
                    $transformed = $object->doTransform($input, $headers, $queryparams);
                    error_log(' Transformed : ' . $transformed);
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

                if(array_key_exists('destTransformScript', $dd)){
                    require_once 'transforms/' . $dd['destTransformScript'];
                    $object = HorusRoadmap::getObjectInstance('HorusTransformerInterface',  $dd['destTransformScript']);
                    try{
                        $transformed1 = $object->doTransform($transformed, $headers, $queryparams);
                        unset($object);
                    }catch(Exception $e){
                        $this->common->mlog(
                            'Unable to transform for destination (custom) : ' . $e->getMessage(),
                            'ERROR'
                        );
                        $transformed1 = '';
                    }
                    $transformed = $transformed1;
                }
                    
                $this->putMessage($source, $dd, $transformed, $businessId, $span, $headers, $queryparams);
                $nMess++;
            }
            if (array_key_exists('delay', $dest)){
                sleep($dest['delay']);
            }
        }
        return $nMess;
    }

}
