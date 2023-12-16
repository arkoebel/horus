<?php

require_once 'lib/horus_http.php';
require_once 'lib/horus_common.php';
require_once 'lib/horus_business.php';
require_once 'lib/horus_inject.php';
require_once 'lib/horus_simplejson.php';
require_once 'lib/horus_xml.php';
require_once 'lib/horus_exception.php';
require_once 'lib/horus_curlInterface.php';
require_once 'lib/horus_curl.php';
require_once 'lib/horus_utils.php';
require_once 'lib/horus_tracing.php';
require_once 'lib/horus_roadmap.php';
require_once 'vendor/autoload.php';

const CONSUMER_GROUP='horus';

$headerInt = new Horus_Header();

$loglocation = HorusCommon::getConfValue('logLocation', HorusCommon::DEFAULT_LOG_LOCATION);

$conf = new RdKafka\Conf();

$conf->set('group.id', CONSUMER_GROUP);

// Initial list of Kafka brokers
$conf->set('metadata.broker.list', HorusCommon::getConfValue('broker.list', ''));

// Set where to start consuming messages when there is no initial offset in
// offset store or the desired offset is out of range.
// 'earliest': start from the beginning
$conf->set('auto.offset.reset', 'earliest');

// Emit EOF event when reaching the end of a partition
$conf->set('enable.partition.eof', 'true');

$consumer = new RdKafka\KafkaConsumer($conf);

//error_log($argv[1]);
//$topics = array($argv[1]);

$topics = array();
$roadmaps = json_decode(file_get_contents('conf/horusRoadmap.json'), true);
foreach ($roadmaps['destinations'] as $destination ) {
    $topics[] = $destination['name'];
}

// Subscribe to topics
$consumer->subscribe($topics);
while (true) {
    $message = $consumer->consume(5*1000);
    switch ($message->err) {
        
        case RD_KAFKA_RESP_ERR_NO_ERROR:
            $headers = $message->headers;
            $pwd = explode('/',$_SERVER['PWD']);
            $tracer = new HorusTracing(
                'WHITE_CONSUMER',
                array_pop($pwd),
                $message->topic_name . ' consume',
                $headers
            );

            $rootSpan = $tracer->getCurrentSpan();
            $span = $tracer->newSpan($message->topic_name . ' process', $rootSpan);
            $tracer->addAttribute($span,'span.kind','CONSUMER');
            $tracer->addAttribute($span,'messaging.system','kafka');
            $tracer->addAttribute($span,'messaging.operation','process');
            $tracer->addAttribute($span,'messaging.destination.name',$message->topic_name);
            $tracer->addAttribute($span,'messaging.kafka.consumer.group',CONSUMER_GROUP);
            $tracer->addAttribute($span,'messaging.kafka.partition',$message->partition);
            $tracer->addAttribute($span,'messaging.kafka.message.offset',$message->offset);
            $tracer->addAttribute($span,'destination',$headers['destinationUrl']);
            $tracer->addAttribute($span,'businessId',$headers['businessId']);
            $tracer->addAttribute($span,'source',$headers['source']);
            
            $common = new HorusCommon($headers['businessId'], $loglocation, 'WHITE');
            $common->mlog(
                'Got new message for destination '
                . $message->topic_name
                . ' sending to '
                . $headers['destinationUrl'],
                'INFO');
            $common->mlog('Message : ' . $message->payload, 'DEBUG');
            $http = new HorusHttp($headers['businessId'], $loglocation, 'WHITE', $tracer);
            try{
                $res = $http->forwardSingleHttpQuery(
                    $headers['destinationUrl'],
                    array_merge(array(
                            $common::TID_HEADER => $headers['businessId']),
                            HorusCommon::explodeAssArray($headers['httpheaders'],'##','||')
                            ,array('Expect: ', 'Content-Length: ' . strlen($message->payload))
                        ),
                    $message->payload,
                    'POST',
                    $span);
                $tracer->addAttribute($span,'destination',$headers['destinationUrl']);
            
            } catch (Exception $e){
                $common->mlog(
                    'Exception while calling destination '
                    . $headers['destinationUrl']
                    . ' : '
                    . $e->getMessage(),
                    'ERROR');
            }
            $consumer->commit($message);
            $tracer->closeSpan($span);
            $tracer->forceFlush();
            $tracer->finishAll();
            $http = null;
            $common = null;
            break;
        case RD_KAFKA_RESP_ERR__PARTITION_EOF:
            echo "No more messages; will wait for more\n";
            break;
        case RD_KAFKA_RESP_ERR__TIMED_OUT:
            echo "Timed out\n";
            break;
        default:
            throw new \Exception($message->errstr(), $message->err);
            break;
    }
}
