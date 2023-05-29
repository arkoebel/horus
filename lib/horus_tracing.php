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

use OpenTelemetry\Context\Context;

interface HorusTracingInterface
{
    public function __construct($prefix, $path, $title, $headers);
    public function newSpan($title, $parent=null);
    public function finishSpan();
    public function finishAll();
    public function setAttribute($key, $value);
    public function log($log);
    public function getCurrentSpan();
    public function getParentSpan();
    public function getTracer($prefix, $path);
    public function addAttribute(&$span, $key, $value);
    public function logSpan(&$span, $log, $bagage = null);
    public function closeSpan(&$span);
    public function flush(&$tracer, &$span);
    public function getB3Headers($span);
}

class HorusTracingMock implements HorusTracingInterface
{
    private $tracer = null;
    private $lastSpan;
    private  ?HorusNodeList $tree;

    public function __construct($prefix, $path, $title, $headers)
    {

        $this->tree = new HorusNodeList('root');
        $this->tracer = $this->getTracer($prefix, $path);

        $new = $this->getStartSpan($this->tracer, $title, $headers);
        $this->tree->addChild(new HorusNodeList($new));
        $this->lastSpan = $new;
    }

    public function newSpan($title, $parent = null)
    {

        if (is_null($parent)) {
            $current = $this->tree->searchValueInTree($this->lastSpan, $this->tree->root());
            $span = $this->startSpan($this->tracer, $title, $this->lastSpan);
        } else {
            $current = $this->tree->searchValueInTree($parent, $this->tree->root());
            $span = $this->startSpan($this->tracer, $title, $parent);
        }
       
        if (!is_null($current)) {
            $current->addChild(new HorusNodeList($span));
        } else {
            $this->tree->root()->addChild(new HorusNodeList(($span)));
        }
        $this->lastSpan = $span;

        return $this->lastSpan;
    }

    public function finishSpan()
    {

        $obj = $this->tree->searchValueInTree($this->lastSpan, $this->tree->root());

        $this->closeSpan($this->lastSpan);
        if (!is_null($obj)) {
            $this->lastSpan = $obj->getParent()->getValue();
        }
    }

    public function finishAll()
    {
        $this->flush($this->tracer, $this->lastSpan);

        $this->printTree();
        $this->lastSpan = null;
    }

    public function setAttribute($key, $value)
    {
        $obj = $this->tree->searchValueInTree($this->lastSpan, $this->tree->root());
        $this->addAttribute($obj, $key, $value);
    }

    public function log($log)
    {
        $obj = $this->tree->searchValueInTree($this->lastSpan, $this->tree->root());
        $this->logSpan($obj, $log, null);
    }

    public function getCurrentSpan()
    {
        return $this->lastSpan;
    }

    public function getParentSpan()
    {
        return $this->tree->searchValueInTree($this->lastSpan, $this->tree->root())->getParent()->getValue();
    }

    public function printTree()
    {
        $root = $this->tree->root();
        $this->printTreeLevel($root, 1);
    }

    private function printTreeLevel(HorusNodeList $element, $level)
    {
        $sp = '                    ';
        $cc = $element->getChildren();
        if (empty($cc)) {
            error_log(substr($sp, 1, $level * 2) . $element->getValue());
        } else {
            error_log(substr($sp, 1, $level * 2) . $element->getValue());
            foreach ($cc as $child) {
                $this->printTreeLevel($child, $level + 1);
            }
        }
    }

    public function getTracer($prefix, $path)
    {
        error_log('Get Tracer ' . $prefix . ' ' . $path);
        return 'tracer.' . $prefix . '-' . $path;
    }
    private function getStartSpan($tracer, $title, $headers)
    {
        error_log('Get Start Span for ' . $tracer . ' : ' . $title);
        return ('span.' . $title);
    }

    private function startSpan($tracer, $spanName, $parentSpan = null)
    {
        error_log('Start new Span for ' . $tracer . ' : ' . $spanName . ' as child of ' . $parentSpan);
        return 'span.' . $spanName;
    }
    public function addAttribute(&$span, $key, $value)
    {
        error_log('Add Span attr ' . $span . ' : ' . $key . ' / ' . $value);
        $span = $span . '.' . $key . '-' . $value;
    }
    public function logSpan(&$span, $log, $bagage = null)
    {
        error_log('Add Span log ' . $span . ' : ' . $log);
        $span = $span . '.' . $log;
    }
    public function closeSpan(&$span)
    {
        error_log('Close Span ' . $span);
        $span = $span . '.end';
    }
    public function flush(&$tracer, &$span)
    {
        error_log('Flush tracer ' . $tracer);
        error_log('Flush span ' . $span);
        $span = $span . '.end';
        $tracer = $tracer . '.finish';
    }

    public function getB3Headers($span)
    {
        return array();
    }
}

class HorusTracing implements HorusTracingInterface
{

    private $tracer = null;
    private $tracerProvider;
    private $lastSpan;
    private ?HorusNodeList $tree;
    public function __construct($prefix, $path, $title, $headers)
    {

        $this->tracer = $this->getTracer($prefix, $path);

        $new = $this->getStartSpan($this->tracer, $title, $headers);
        $this->tree = new HorusNodeList($new);
        $this->lastSpan = $new;
    }

    public function newSpan($title, $parent = null)
    {
        if (is_null($parent)) {
            $this->lastSpan = $this->startSpan($this->tracer, $title, $this->lastSpan);
        } else {
            $this->lastSpan = $this->startSpan($this->tracer, $title, $parent);
        }
        return $this->lastSpan;
    }

    public function finishSpan()
    {
        $this->finishSpan($this->lastSpan);
        $this->lastSpan = '';
    }

    public function finishAll()
    {
        $this->flush($this->tracer, $this->lastSpan);
    }

    public function setAttribute($key, $value)
    {
        $obj = $this->tree->searchValueInTree($this->lastSpan, $this->tree->root());
        $this->addAttribute($obj, $key, $value);
    }

    public function log($log)
    {
        $obj = $this->tree->searchValueInTree($this->lastSpan, $this->tree->root());
        $this->logSpan($obj, $log, null);
    }

    public function getCurrentSpan()
    {
        return $this->lastSpan;
    }

    public function getParentSpan()
    {
        return $this->tree->searchValueInTree($this->lastSpan, $this->tree->root())->getParent()->getValue();
    }

    public function getTracer($prefix, $path)
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
        $this->tracerProvider = new TracerProvider(
            new BatchSpanProcessor($decorator, ClockFactory::getDefault()),
            new AlwaysOnSampler()
        );
        return $this->tracerProvider->getTracer($prefix . '_' . $path);
    }

    private function getStartSpan($tracer, $title, $headers)
    {
        $h = array();
        foreach ($headers as $key => $value) {
            $h[strtolower($key)] = $value;
        }

        $propagator = B3MultiPropagator::getInstance();

        $rootContext = $propagator->extract($h);

        //start a root span
        return $tracer
            ->spanBuilder($title)->setParent($rootContext)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('OTEL_SERVICE_NAME', 'toto')
            ->startSpan();
    }

    private function startSpan($tracer, $spanName, $parentSpan = null)
    {
        if (is_null($parentSpan)) {
            return $tracer
                ->spanBuilder($spanName)
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->startSpan();
        } else {
            $ctx = $parentSpan->storeInContext(Context::getCurrent());
            return $tracer
                ->spanBuilder($spanName)
                ->setParent($ctx)
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->startSpan();
        }
    }

    public function addAttribute(&$span, $key, $value)
    {
        $span->setAttribute($key, $value);
    }

    public function logSpan(&$span, $log, $bagage = null)
    {
        $span->addEvent($log);
    }

    public function closeSpan(&$span)
    {
        $span->end();
    }

    public function flush(&$tracer, &$span)
    {
        $span->end();
        $this->tracerProvider->shutdown();
    }

    public function getB3Headers($span)
    {
        $propagator = B3MultiPropagator::getInstance();
        $carrier=array();
        $ctx = $span->storeInContext(Context::getCurrent());
        $propagator->inject($carrier, null, $ctx);
        return $carrier;

    }
}

class HorusNodeList
{
    private $value;
    private ?HorusNodeList $parent = null;
    private array $children = [];

    public function __construct(
        $value = null,
        array $children = []
    ) {
        $this->setValue($value);

        if ([] === $children) {
            return;
        }

        $this->setChildren($children);
    }

    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function addChild(HorusNodeList $child): mixed
    {
        $child->setParent($this);
        $this->children[] = $child;

        return $this;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function setChildren(array $children): mixed
    {
        foreach ($this->getChildren() as $child) {
            $child->setParent(null);
        }

        $this->children = [];

        foreach ($children as $child) {
            $this->addChild($child);
        }

        return $this;
    }

    public function setParent(?HorusNodeList $parent = null): void
    {
        $this->parent = $parent;
    }

    public function getParent(): mixed
    {
        return $this->parent;
    }

    public function getAncestors(): array
    {
        $parents = [];
        $node = $this;

        while (($parent = $node->getParent()) instanceof HorusNodeList) {
            \array_unshift($parents, $parent);
            $node = $parent;
        }

        return $parents;
    }

    public function getAncestorsAndSelf(): array
    {
        return \array_merge($this->getAncestors(), [$this]);
    }

    public function getNeighbors(): array
    {
        if (null === $this->parent) {
            return [];
        }

        $neighbors = $this->parent->getChildren();
        $that = $this;

        return \array_values(\array_filter($neighbors, static function (HorusNodeList $node) use ($that): bool {
            return $node !== $that;
        }));
    }

    public function getNeighborsAndSelf(): array
    {
        if (null === $this->parent) {
            return [
                $this,
            ];
        }

        return $this->parent->getChildren();
    }

    public function isRoot(): bool
    {
        return null === $this->parent;
    }

    public function isChild(): bool
    {
        return null !== $this->parent;
    }

    public function isLeaf(): bool
    {
        return [] === $this->children;
    }

    public function root(): mixed
    {
        $node = $this;

        while (($parent = $node->getParent()) instanceof HorusNodeList) {
            $node = $parent;
        }

        return $node;
    }

    /**
     * Return the distance from the current node to the root.
     *
     * Warning, can be expensive, since each descendant is visited
     */
    public function getDepth(): int
    {
        if ($this->isRoot()) {
            return 0;
        }

        return $this->getParent()->getDepth() + 1;
    }

    /**
     * Return the height of the tree whose root is this node.
     */
    public function getHeight(): int
    {
        if ($this->isLeaf()) {
            return 0;
        }

        $heights = [];

        foreach ($this->getChildren() as $child) {
            $heights[] = $child->getHeight();
        }

        return \max($heights) + 1;
    }

    public function getSize(): int
    {
        $size = 1;

        foreach ($this->getChildren() as $child) {
            $size += $child->getSize();
        }

        return $size;
    }

    public function searchValueInTree($value, HorusNodeList $tree)
    {
        // Check if the current node's value matches the search value
        if ($tree->getValue() === $value) {
            return $tree;
        }

        // Recursively search in the children nodes
        $children = $tree->getChildren();
        if ($children !== null) {
            foreach ($children as $child) {
                $result = $this->searchValueInTree($value, $child);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        // Value not found in this subtree
        return null;
    }
}
