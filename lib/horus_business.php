<?php

use Flow\JSONPath\JSONPath;

class HorusBusiness
{

    public $common                        = '';
    public $http                          = '';
    private $businessId                   = '';
    public ?HorusTracingInterface $tracer = null;

    public function __construct(
        $businessId,
        $logLocation,
        $colour,
        HorusTracingInterface $tracer,
        ?Horus_CurlInterface $httpImpl = null
    ) {
        $this->businessId = $businessId;
        $this->common     = new HorusCommon($businessId, $logLocation, $colour);
        if (is_null($httpImpl)) {
            $httpImpl = new Horus_Curl();
        }
        $this->http   = new HorusHttp($businessId, $logLocation, $colour, $tracer, $httpImpl);
        $this->tracer = $tracer;
    }

    public function findMatch($matches, $request, $field)
    {
        if (array_key_exists($request, $matches)) {
            if (array_key_exists($field, $matches[$request])) {
                return $matches[$request][$field];
            } else {
                return '';
            }
        } else {
            return '';
        }
    }

    public function findSource($source, $params)
    {
        foreach ($params["RoutingTable"] as $route) {
            if ($route['source'] === $source) {
                return $route;
            }
        }
        return false;
    }

    public function locate($matches, $found, $value, $queryParams = [])
    {
        $selected = -1;

        if (is_null($matches) || ! is_array($matches) || (count($matches) == 0) || is_null($found) || is_null($value)) {
            return $selected;
        }

        foreach ($matches as $id => $match) {
            if (array_key_exists('selector', $match)) {
                if (! array_key_exists($match['selector']['key'], $queryParams)) {
                    $this->common->mlog('Selector : key not found in url params - no match for param line #' . $id . ' (' . $match['comment'] . ')', 'DEBUG');
                    continue;
                }
                if (array_key_exists($match['selector']['key'], $queryParams) && ($match['selector']['value'] !== $queryParams[$match['selector']['key']])) {
                    $this->common->mlog('Selector not matched for param line #' . $id . ' (' . $match['comment'] . ')', 'DEBUG');
                    continue;
                }
            } else {
                //$this->common->mlog('Selector matched for param line #'.$id,'DEBUG');770163
            }
            if (array_key_exists('query', $match) && ($match['query'] === $found)) {
                if (array_key_exists('queryMatch', $match) && $match['queryMatch'] != '') {
                    $mm = $match['queryMatch'];
                    if (preg_match_all('/\$\{(.*?)}/', $mm)) {
                        $mm1 = preg_replace_callback('/\${(.*?)}/', function ($matches) use ($queryParams) {
                            $key = $matches[1]; // The captured key inside the ${}
                            return isset($queryParams[$key]) ? $queryParams[$key] : '${' . $key . '}';
                        }, $mm);
                        if ($mm1 !== $mm) {
                            $this->common->mlog('Current queryMatch: ' . $mm . ' was converted to ' . $mm1, 'DEBUG');
                            $mm = $mm1;
                        }
                    }
                    if (preg_match('/' . $mm . '/', $value) === 1) {
                        $selected = $id;
                        $this->common->mlog('Current match: ' . $match['comment'] . ' ' . $mm, 'DEBUG');
                    } else {
                        $this->common->mlog('QueryMatch failed for param line #' . $id, 'DEBUG');
                        $this->common->mlog($match['comment'] . ' ' . $mm, 'DEBUG');
                    }
                } else {
                    $this->common->mlog('Param line #' . $id . ' could be selected (if last).', 'DEBUG');
                    $this->common->mlog(
                        $match['comment'] . ' ' . (array_key_exists('queryMatch', $match) ? $match['queryMatch'] : ''),
                        'DEBUG'
                    );
                    $selected = $id;
                }
            }
        }
        return $selected;
    }

    public function locateJson($matches, $input, $queryParams = [])
    {
        $selected = -1;
        if (is_null($input) ||
            is_null($matches) ||
            (is_array($matches) && count($matches) == 0) ||
            (is_array($input) && empty($input))
        ) {
            return $selected;
        }

        if (is_null($queryParams)) {
            $queryParams = [];
        }

        foreach ($matches as $id => $match) {
            if (array_key_exists('jsonpath', $match['query'])) {
                if (
                    array_key_exists('queryKey', $match['query']) &&
                    array_key_exists('queryValue', $match['query']) &&
                    !(
                        array_key_exists($match['query']['queryKey'], $queryParams) &&
                        ($match['query']['queryValue'] === $queryParams[$match['query']['queryKey']])
                    )
                ) {
                    $this->common->mlog($id . ': query param present, but didn\'t match', 'DEBUG');
                    continue;
                } else {
                    array_key_exists('queryKey', $match) ? 
                        $this->common->mlog($id . ': query param ' . $match['query']['queryKey'] . ' matched', 'DEBUG')
                        :  $this->common->mlog($id . ': no query param to match', 'DEBUG');
                }
                if (array_key_exists('value', $match['query']) || array_key_exists('queryMatch', $match)) {
                    $rr = (new JSONPath($input))->find($match['query']['jsonpath'])->getData();
                    if (is_array($rr) && (count($rr) > 0)) {
                        $this->common->mlog('jsonpath found ' . count($rr) . ' elements', 'DEBUG');
                        if(is_array($rr[0]))
                            $loop = $rr[0];
                        else
                            $loop = $rr;
                        foreach ($loop as $tries) {
                            if (
                                (
                                    array_key_exists('queryMatch', $match)
                                    && (preg_match('/' . $match['queryMatch'] . '/', $tries))
                                ) || (
                                    array_key_exists('value', $match['query'])
                                    && ($tries === $match['query']['value'])
                                )
                            ) {
                                $this->common->mlog(
                                    $id . ': jsonpath '
                                    . $match['query']['jsonpath'] . ' element ' . $tries . ' matched '
                                    . (array_key_exists('queryMatch', $match) ? $match['queryMatch'] : $match['query']['value'])
                                    , 'DEBUG');
                                $selected = $id;
                            }
                        }
                    }
                }
            } elseif (array_key_exists($match['query']['key'], $input)) {
                if (array_key_exists('queryKey', $match['query'])) {
                    if (
                        array_key_exists($match['query']['queryKey'], $queryParams) &&
                        $match['query']['queryValue'] === $queryParams[$match['query']['queryKey']]) {
                        $this->common->mlog($id . ': trying -- Matched query param', 'DEBUG');
                        if ($input[$match['query']['key']] === $match['query']['value']) {
                            if (array_key_exists('queryMatch', $match) && $match['queryMatch'] != '') {
                                if (preg_match('/' . $match['queryMatch'] . '/', json_encode($input)) === 1) {
                                    $this->common->mlog($id . ': matched -- querymatch, query param', 'DEBUG');
                                    $selected = $id;
                                }
                            } else {
                                $this->common->mlog($id . ': matched -- no query match, query param', 'DEBUG');
                                $selected = $id;
                            }
                        }
                    } else {
                        $this->common->mlog($id . ': trying -- Query param wasn\'t a match', 'DEBUG');
                    }
                } else {
                    if ($input[$match['query']['key']] === $match['query']['value']) {
                        if (array_key_exists('queryMatch', $match) && $match['queryMatch'] != '') {
                            if (preg_match('/' . $match['queryMatch'] . '/', json_encode($input)) === 1) {
                                $this->common->mlog($id . ': matched -- querymatch, no query param', 'DEBUG');
                                $selected = $id;
                            }
                        } else {
                            $this->common->mlog($id . ': matched -- no query match', 'DEBUG');
                            $selected = $id;
                        }
                    }
                }
            }
        }

        return $selected;
    }

    public function extractPayload($contentType, $body, $errorTemplate, $errorFormat, $span)
    {
        if (substr($contentType, 0, 16) == "application/json") {
            $json = json_decode($body, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                $this->returnGenericError(
                    $errorFormat,
                    $errorTemplate,
                    'JSON Malformed : ' . $this->common->decodeJsonError(json_last_error()),
                    '',
                    $span
                );
            } else {
                if (array_key_exists('payload', $json) && $json['payload'] != null) {
                    return $json['payload'];
                } else {
                    $this->returnGenericError($contentType, $errorTemplate, 'Empty JSON Payload', '', $span);
                }
            }
        } else {
            return $body;
        }
    }

    public function extractSimpleJsonPayload($body)
    {
        return json_decode($body, true);
    }

    public function returnGenericError($format, $template, $errorMessage, $forward = '', $span = null)
    {

        $this->common->mlog("Error being generated. Cause: $errorMessage", 'INFO');
        ob_start();
        include_once $template;
        $errorOutput = ob_get_contents();
        ob_end_clean();

        $ret = $this->http->returnWithContentType($errorOutput, $format, 400, $forward, true, 'POST', [], $span);
        if ('' === $forward) {
            return $ret;
        }
    }

    public function returnGenericJsonError($format, $template, $errorMessage, $forward = '', $span = null)
    {

        $this->common->mlog("Error JSON being generated. Cause: $errorMessage", 'INFO');
        ob_start();
        include_once $template;
        $errorOutput = ob_get_contents();
        ob_end_clean();

        $this->common->mlog($errorOutput, 'DEBUG', 'JSON');
        return $this->http->returnWithContentType($errorOutput, $format, 400, $forward, true, 'POST', [], $span);
    }

    public function transformGetParams($inParams)
    {
        $outParams = [];
        foreach ($inParams as $key => $value) {
            $outParams[] = ['key' => $key, 'value' => $value];
        }
        return $outParams;
    }

    public static function getTemplateName($template, $variables)
    {
        preg_match_all('/\$\{([A-z0-9_\-]*)\}/', $template, $list);
        if (empty($list)) {
            return $template;
        } else {
            $tmpl = $template;
            foreach ($list[1] as $item) {
                if (array_key_exists($item, $variables)) {
                    $tmpl = preg_replace('/\$\{' . $item . '\}/', $variables[$item], $tmpl);
                } else {
                    $tmpl = preg_replace('/\$\{' . $item . '\}/', '', $tmpl);
                }
            }
            return $tmpl;
        }
    }

    public function performRouting($route, $contentType, $accept, $data, $queryParams = [], $span = null)
    {
        if (is_null($route) || $route === false) {
            $this->common->mlog('No route found with provided source value', 'WARNING');
            throw new HorusException('Route not found', 400);
        }

        $followOnError = array_key_exists('followOnError', $route) ? $route['followOnError'] : true;
        $this->common->mlog("FollowOnError $followOnError", "INFO");
        $globalParams = array_key_exists('parameters', $route) ? $route['parameters'] : [];
        $globalParams = array_merge($this->transformGetParams($queryParams), $globalParams);
        $responses    = [];

        $ii = 0;

        foreach ($route['destinations'] as $destination) {
            $ii++;

            $routeSpan = $this->tracer->newSpan('Destination ' . $destination['comment']);
            $this->common->mlog("Destination : $ii " . $destination['comment'] . "\n", "INFO");

            $destParams = array_key_exists('destParameters', $destination)
            ? $destination['destParameters']
            : [];
            $proxyParams = array_key_exists('proxyParameters', $destination)
            ? $destination['proxyParameters']
            : [];

            $destinationUrl = HorusCommon::formatQueryString(
                $destination['destination'],
                array_merge($globalParams, $destParams),
                true);
            $proxyUrl = array_key_exists('proxy', $destination)
            ? HorusCommon::formatQueryString($destination['proxy'], array_merge($globalParams, $proxyParams), true)
            : '';

            $this->common->mlog("Send http request to " . $proxyUrl . "\n", 'DEBUG');
            $this->common->mlog("Final destination : " . $destinationUrl . "\n", 'DEBUG');
            $this->common->mlog("Content-type: " . $contentType . ", Accept: " . $accept, 'DEBUG');

            $this->tracer->addAttribute($routeSpan, 'destination', $destinationUrl);
            $this->tracer->addAttribute($routeSpan, 'proxy', $proxyUrl);
            $this->tracer->addAttribute($routeSpan, 'content-type', $contentType);
            $this->tracer->addAttribute($routeSpan, 'accept', $accept);

            $headers = [
                'Content-type: ' . $contentType,
                'Accept: ' . $accept,
                'Expect: ',
                'X-Business-Id: ' . $this->businessId,
            ];

            if (! array_key_exists('proxy', $destination)) {
                $destUrl = $destinationUrl;
            } else {
                $destUrl   = $proxyUrl;
                $headers[] = 'X_DESTINATION_URL: ' . $destinationUrl;
            }

            try {
                $response = $this->http->forwardSingleHttpQuery($destUrl, $headers, $data, 'POST', $routeSpan);
            } catch (HorusException $e) {
                $response = json_encode(["error" => $e->getMessage()]);
                if (! $followOnError) {
                    $this->tracer->closeSpan($routeSpan);
                    throw new HorusException('Flow interrupted after error ' . $e->getMessage(), 503);
                }
            }

            $responses[] = $response;

            if (array_key_exists('delayafter', $destination)) {
                $this->tracer->logSpan($routeSpan, 'Start delay ' . $destination['delayafter'] . 's');
                $this->common->mlog('Waiting ' . $destination['delayafter'] . 'sec for next destination', 'INFO');
                //sleep($destination['delayafter']);
                $this->tracer->logSpan($routeSpan, 'End delay');
            }
            $this->tracer->closeSpan($routeSpan);
        }
        return $responses;
    }
}
