<?php

class HorusBusiness
{

    public $common = '';
    public $http = '';
    private $business_id = '';

    function __construct($business_id, $log_location, $colour)
    {
        $this->business_id = $business_id;
        $this->common = new HorusCommon($business_id, $log_location, $colour);
        $this->http = new HorusHttp($business_id, $log_location, $colour);
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

    public function locate($matches, $found, $value)
    {
        $selected = -1;

        if (is_null($matches) || !is_array($matches) || (count($matches) == 0) || is_null($found) || is_null($value)) {
            return $selected;
        }

        foreach ($matches as $id => $match) {
            if ($match['query'] === $found) {
                if (array_key_exists('queryMatch', $match) && $match['queryMatch'] != '') {
                    if (preg_match('/' . $match['queryMatch'] . '/', $value) === 1) {
                        $selected = $id;
                    } else {
                        $this->common->mlog('QueryMatch failed for param line #' . $id, 'DEBUG');
                    }
                } else {
                    $this->common->mlog('Param line #' . $id . ' could be selected (if last).', 'DEBUG');
                    $selected = $id;
                }
            }
        }
        return $selected;
    }

    function locateJson($matches, $input, $queryParams = array())
    {
        $selected = -1;
        if (is_null($input) || is_null($matches) || (is_array($matches) && count($matches) == 0) || (is_array($input) && count($input) == 0)) {
            return $selected;
        }

        if (is_null($queryParams)){
            $queryParams = array();
        }

        foreach ($matches as $id => $match) {
            if (array_key_exists($match['query']['key'], $input)) {
                if (array_key_exists('queryKey', $match['query'])) {
                    if (array_key_exists($match['query']['queryKey'], $queryParams) && $match['query']['queryValue'] === $queryParams[$match['query']['queryKey']]) {
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

    function extractPayload($content_type, $body, $errorTemplate, $errorFormat)
    {
        if ($content_type == "application/json") {
            $json = json_decode($body, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                $this->returnGenericError($errorFormat, $errorTemplate, 'JSON Malformed : ' . decodeJsonError(json_last_error()));
            } else {
                if (array_key_exists('payload',$json) && $json['payload'] != null){
                    return $json['payload'];
                }else{
                    $this->returnGenericError($content_type, $errorTemplate, 'Empty JSON Payload');
                }
            }
        } else {
            return $body;
        }
    }

    function extractSimpleJsonPayload($body)
    {
        return json_decode($body, true);
    }

    function returnGenericError($format, $template, $errorMessage, $forward = '')
    {

        $this->common->mlog("Error being generated. Cause: $errorMessage", 'INFO');
        ob_start();
        include $template;
        $errorOutput = ob_get_contents();
        ob_end_clean();

        $ret = $this->http->returnWithContentType($errorOutput, $format, 400, $forward);
        if ('' === $forward){
            return $ret;
        }
    }

    function returnGenericJsonError($format, $template, $errorMessage, $forward = '')
    {

        $this->common->mlog("Error JSON being generated. Cause: $errorMessage", 'INFO');
        ob_start();
        include $template;
        $errorOutput = ob_get_contents();
        ob_end_clean();

        $this->common->mlog($errorOutput, 'DEBUG', 'JSON');

        return $this->http->returnWithContentType($errorOutput, $format, 400, $forward, true);
      
    }

    function transformGetParams($inParams){
        $outParams = array();
        foreach ($inParams as $key=>$value){
            $outParams[] = array('key'=>$key,'value'=>$value);
        }
        return $outParams;
    }

    public static function getTemplateName($template,$variables){
        preg_match_all('/\$\{([A-z0-9_\-]*)\}/',$template,$list);
        if(count($list)==0){
            return $template;
        }else{
            $tmpl = $template;
            foreach($list[1] as $item){
                if(array_key_exists($item,$variables)){
                    $tmpl = preg_replace('/\$\{' . $item . '\}/',$variables[$item],$tmpl);
                }else{
                    $tmpl = preg_replace('/\$\{' . $item . '\}/','',$tmpl);
                }
            }
            return $tmpl;
        }
    }

    public function performRouting($route, $content_type, $accept, $data, $queryParams = array())
    {
        if (is_null($route) || $route === false) {
            $this->common->mlog('No route found with provided source value', 'WARNING');
            throw new HorusException('Route not found', 400);
        }

        $followOnError = array_key_exists('followOnError', $route) ? $route['followOnError'] : true;
        $this->common->mlog("FollowOnError $followOnError", "INFO");
        $globalParams = array_key_exists('parameters', $route) ? $route['parameters'] : array();
        $globalParams = array_merge($this->transformGetParams($queryParams),$globalParams);

        $responses = array();

        $ii = 0;

        foreach ($route['destinations'] as $destination) {
            $ii++;

            $this->common->mlog("Destination : $ii " . $destination['comment'] . "\n", "INFO");


            $destParams = array_key_exists('destParameters', $destination) ? $destination['destParameters'] : array();
            $proxyParams = array_key_exists('proxyParameters', $destination) ? $destination['proxyParameters'] : array();

            $destinationUrl = HorusCommon::formatQueryString($destination['destination'], array_merge($globalParams, $destParams), TRUE);
            $proxyUrl = array_key_exists('proxy', $destination) ? HorusCommon::formatQueryString($destination['proxy'], array_merge($globalParams, $proxyParams), TRUE) : '';

            $this->common->mlog("Send http request to " . $proxyUrl . "\n", 'DEBUG');
            $this->common->mlog("Final destination : " . $destinationUrl . "\n", 'DEBUG');
            $this->common->mlog("Content-type: " . $content_type . ", Accept: " . $accept, 'DEBUG');

            $headers = array('Content-type: ' . $content_type, 'Accept: ' . $accept, 'Expect: ', 'X-Business-Id: ' . $this->business_id);

            if (!array_key_exists('proxy', $destination)) {
                $dest_url = $destinationUrl;
            } else {
                $dest_url = $proxyUrl;
                $headers[] = 'X_DESTINATION_URL: ' . $destinationUrl;
            }

            try {
                $response = $this->http->forwardSingleHttpQuery($dest_url, $headers, $data, 'POST');
            } catch (HorusException $e) {
                $response = json_encode(array("error" => $e->getMessage()));
                if (!$followOnError){
                    throw new HorusException('Flow interrupted after error ' . $e->getMessage(), 503);
                }
            }

            $responses[] = $response;

            if (array_key_exists('delayafter', $destination)) {
                $this->common->mlog('Waiting ' . $destination['delayafter'] . 'sec for next destination', 'INFO');
                sleep($destination['delayafter']);
            }
        }
        return $responses;
    }
}
