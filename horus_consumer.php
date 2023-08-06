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

$headerInt = new Horus_Header();

$loglocation = HorusCommon::getConfValue('logLocation', HorusCommon::DEFAULT_LOG_LOCATION);

$conf = new RdKafka\Conf();

$conf->set('group.id', 'horus');

// Initial list of Kafka brokers
$conf->set('metadata.broker.list', HorusCommon::getConfValue('broker.list', ''));

// Set where to start consuming messages when there is no initial offset in
// offset store or the desired offset is out of range.
// 'earliest': start from the beginning
$conf->set('auto.offset.reset', 'earliest');

// Emit EOF event when reaching the end of a partition
$conf->set('enable.partition.eof', 'true');

$consumer = new RdKafka\KafkaConsumer($conf);

$topics = array();

$roadmaps = json_decode(file_get_contents('conf/horusRoadmap.json'), true);
foreach ($roadmaps['destinations'] as $destination ) {
    $topics[] = $destination['name'];
}

// Subscribe to topics
$consumer->subscribe($topics);
while (true) {
    $message = $consumer->consume(120*1000);
    switch ($message->err) {
        case RD_KAFKA_RESP_ERR_NO_ERROR:
            $headers = $message->headers;
            $tracer = new HorusTracing(
                'WHITE',
                HorusCommon::getPath($_SERVER),
                'Start White Consumer',
                $headers
            );
            $rootSpan = $tracer->getCurrentSpan();
            $span = $tracer->newSpan('Treating message', $rootSpan);
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
                    array($common::TID_HEADER => $headers['businessId']),
                    $message->payload,
                    'POST',
                    $span);
            } catch (Exception $e){
                $common->mlog(
                    'Exception while calling destination '
                    . $headers['destinationUrl']
                    . ' : '
                    . $e->getMessage(),
                    'ERROR');
            }
            $tracer->closeSpan($span);
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

?>