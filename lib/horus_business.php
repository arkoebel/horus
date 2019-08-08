<?php

class HorusBusiness
{

    private $common = '';

    function __construct($business_id, $log_location, $colour)
    {
        $this->common = new HorusCommon($business_id, $log_location, $colour);
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

    public function locate($matches, $found, $value)
    {
        $selected = -1;

        if (is_null($matches))
            return $selected;
        if (!is_array($matches))
            return $selected;
        if (count($matches) == 0)
            return $selected;
        if (is_null($found) || is_null($value))
            return $selected;

        foreach ($matches as $id => $match) {
            if ($match['query'] === $found) {
                if (array_key_exists('queryMatch', $match) && $match['queryMatch'] != '') {
                    if (preg_match('/' . $match['queryMatch'] . '/', $value) === 1) {
                        $selected = $id;
                    } else {
                        //echo('not found' . "\n");
                    }
                } else
                    $selected = $id;
            }
        }
        return $selected;
    }

    function locateJson($matches, $input, $queryParams = array())
    {
        $selected = -1;
        if (is_null($input))
            return $selected;
        if (is_null($matches))
            return $selected;
        if (is_array($matches) && count($matches) == 0)
            return $selected;
        if (is_array($input) && count($input) == 0)
            return $selected;

        if (is_null($queryParams))
            $queryParams = array();
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
                returnGenericError($errorFormat, $errorTemplate, 'JSON Malformed : ' . decodeJsonError(json_last_error()));
            } else {
                if ($json['payload'] != null)
                    return $json['payload'];
                else
                    returnGenericError($content_type, $errorTemplate, 'Empty JSON Payload');
            }
        } else
            return $body;
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

        returnWithContentType($errorOutput, $format, 400, $forward);
    }

    function returnGenericJsonError($format, $template, $errorMessage, $forward = '')
    {

        mlog("Error JSON being generated. Cause: $errorMessage", 'INFO');
        ob_start();
        include $template;
        $errorOutput = ob_get_contents();
        ob_end_clean();

        mlog($errorOutput, 'DEBUG', 'JSON');

        returnWithContentType($errorOutput, $format, 400, $forward, true, true);
    }
}
