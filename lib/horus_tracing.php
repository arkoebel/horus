<?php

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Contrib\Zipkin\Exporter as ZipkinExporter;
use OpenTelemetry\Extension\Propagator\B3\B3MultiPropagator;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanExporter\LoggerDecorator;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

interface HorusTracingInterface
{
    public function __construct($prefix, $path, $title, $headers);
    public function newSpan($title);
    public function finishSpan();
    public function finishAll();
    public static function getTracer($prefix, $path);
    public static function getStartSpan($tracer, $title, $headers);
    public static function startSpan($tracer, $spanName, $parentSpan = null);
    public static function addAttribute(&$span, $key, $value);
    public static function logSpan(&$span, $log, $bagage = null);
    public static function closeSpan(&$span);
    public static function flush(&$tracer, &$span);
}

class HorusTracingMock implements HorusTracingInterface
{
    private $tracer = null;
    private $lastSpan = null;
    private $spans = array();

    private function searchParent($spans, $child)
    {
        foreach($spans as $leaf) {
            if ($child === $leaf['object']) {
                return $leaf['parent'];
            } else {
                return $this->searchParent($leaf['children'], $child);
            }
        }
    }

    public function __construct($prefix, $path, $title, $headers){
        $this->tracer = HorusTracingMock::getTracer($prefix, $path);
        $this->lastSpan = HorusTracingMock::getStartSpan($this->tracer, $title, $headers);
        $this->spans[] = array("name"=>$title,"parent"=>null, "object"=>$this->lastSpan);
    }

    public function newSpan($title)
    {
        $oldSpan = $this->lastSpan;
        $this->lastSpan = HorusTracingMock::startSpan($this->tracer, $title, $this->lastSpan);
        return $this->lastSpan;
    }

    public function finishSpan()
    {
        HorusTracingMock::closeSpan($this->lastSpan);
        $this->lastSpan='';
    }

    public function finishAll()
    {
        HorusTracingMock::flush($this->tracer, $this->lastSpan);
    }

    public static function getTracer($prefix, $path)
    {
        error_log('Get Tracer ' . $prefix . ' ' . $path);
        return 'tracer.' . $prefix . '-' . $path;
    }
    public static function getStartSpan($tracer, $title, $headers)
    {
        error_log('Get Start Span for ' . $tracer . ' : ' . $title);
        return ('span.' . $title);
    }

    public static function startSpan($tracer, $spanName, $parentSpan = null)
    {
        error_log('Start new Span for ' . $tracer . ' : ' . $spanName);
        return ('span.' . $spanName);

    }
    public static function addAttribute(&$span, $key, $value)
    {
        error_log('Add Span attr ' . $span . ' : ' . $key . ' / ' . $value);
        $span = $span . '.' . $key . '-' . $value;
    }
    public static function logSpan(&$span, $log, $bagage = null)
    {
        error_log('Add Span log ' . $span . ' : ' . $log);
        $span = $span . '.' . $log;
    }
    public static function closeSpan(&$span)
    {
        error_log('Close Span' . $span);
        $span = $span . '.end';
    }
    public static function flush(&$tracer, &$span)
    {
        error_log('Flush tracer ' . $tracer);
        error_log('Flush span ' . $span);
        $span = $span . '.end';
        $tracer = $tracer . '.finish';
    }
}

class HorusTracing implements HorusTracingInterface
{

    private $tracer = null;
    private $lastSpan = null;
    public function __construct($prefix, $path, $title, $headers){
        $this->tracer = HorusTracing::getTracer($prefix, $path);
        $this->lastSpan = HorusTracing::getStartSpan($this->tracer, $title, $headers);

    }

    public function newSpan($title)
    {
        $this->lastSpan = HorusTracing::startSpan($this->tracer, $title, $this->lastSpan);
        return $this->lastSpan;
    }

    public function finishSpan()
    {
        HorusTracing::finishSpan($this->lastSpan);
        $this->lastSpan='';
    }

    public function finishAll()
    {
        HorusTracing::flush($this->tracer, $this->lastSpan);
    }

    public static function getTracer($prefix, $path)
    {
        $cnf = json_decode(file_get_contents('conf/horusConfig.json'), true);

        $transport = PsrTransportFactory::discover()->create($cnf['zipkinUrl'], 'application/json');
        $zipkinExporter = new ZipkinExporter(
            $transport
        );
        $decorator = new LoggerDecorator(
            $zipkinExporter,
            //new SimplePsrFileLogger(__DIR__ . '/../otel.log')
        );

        putenv('OTEL_SERVICE_NAME=' . $prefix . '_' . $path);
        $provider = new TracerProvider(
            new BatchSpanProcessor($decorator, ClockFactory::getDefault()),
            new AlwaysOnSampler()
        );
        return $provider->getTracer($prefix . '_' . $path);
    }

    public static function getStartSpan($tracer, $title, $headers)
    {
        $h = array();
        foreach ($headers as $key => $value) {
            $h[strtolower($key)] = $value;
        }

        $propagator = B3MultiPropagator::getInstance();

        $rootContext = $propagator->extract($h);

        //start a root span
        return  $tracer
                    ->spanBuilder($title)->setParent($rootContext)
                    ->setSpanKind(SpanKind::KIND_SERVER)
                    ->setAttribute('OTEL_SERVICE_NAME', 'toto')
                    ->startSpan();
    }

    public static function startSpan($tracer, $spanName, $parentSpan = null)
    {
        if (is_null($parentSpan)) {
            return $tracer
                ->spanBuilder($spanName)
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->startSpan();
        } else {
            return $tracer
                ->spanBuilder($spanName)
                ->setParent($parentSpan)
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->startSpan();
        }
    }

    public static function addAttribute(&$span, $key, $value)
    {
        $span->setAttribute($key, $value);
    }

    public static function logSpan(&$span, $log, $bagage = null)
    {
        $span->addEvent($log);
    }

    public static function closeSpan(&$span)
    {
        $span->end();
    }

    public static function flush(&$tracer, &$span)
    {
        $span->end();
        $tracer->shutdown();
    }
}
