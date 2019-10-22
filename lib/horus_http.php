<?php
class HorusHttp
{

    public $common = null;
    public $business_id = '';

    function __construct($business_id, $log_location, $colour)
    {
        $this->common = new HorusCommon($business_id, $log_location, $colour);
        $this->business_id = $business_id;
    }

    /**
     * function formMultiPart
     * Generate a HTTP MultiPart section
     */
    function formMultiPart($file, $data, $mime_boundary, $eol, $content_type)
    {
        $cc = '';
        $cc .= '--' . $mime_boundary . $eol;
        $cc .= "Content-Disposition: form-data; name=\"$file\"; filename=\"$file\"" . $eol;
        $cc .= 'Content-Type: ' . $content_type . $eol;
        $cc .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $cc .= chunk_split(base64_encode($data)) . $eol;

        return $cc;
    }

    /**
     * function returnArrayWithContentType
     * Send a set of http queries to the same destination
     */
    function returnArrayWithContentType($data, $content_type, $status, $forward = '', $exitafter = true, $mytime, $no_conversion = false, $method = 'POST')
    {

        if ($no_conversion === FALSE)
            $this->common->mlog('Forced conversion', 'DEBUG');
        $this->setHttpReturnCode($status);

        if (is_null($forward))
            $forward = '';

        if ($forward !== '') {
            $headers = array('Content-type' => $content_type, 'Accept' => 'application/json', 'Expect' => '', 'X-Business-Id' => $this->business_id);
            $queries = array();
            foreach ($data as $i => $content) {
                $ct = $this->convertOutData($content, $content_type, $no_conversion);
                $query = array('url' => $forward, 'method' => $method, 'headers' => $headers, 'data' => $ct);
                $queries[] = $query;
            }

            $result = $this->forwardHttpQueries($queries);
            $responses = array();

            foreach ($result as $i => $content) {
                if (stripos($content['response_headers']['content-Type'], 'json') > 0) {
                    $json = true;
                    $responses[] = json_decode($content['response_data'], true);
                } else
                    $responses[] = $content['response_data'];
            }

            if ($json) {
                echo json_encode($responses);
            } else {
                echo implode("\n", $responses);
            }
        }else{
            header('Content-type: ' . $content_type);
            $ret = '';
            foreach ($data as $i => $content) {
                $ret .= $this->convertOutData($content, $content_type, $no_conversion) . "\n";
            }
            return $ret;
        }

        if ($exitafter === true)
            exit;
    }

    /**
     * function returnWithContentType
     * Sends a http query to the next step or returns response
     */
    function returnWithContentType($data, $content_type, $status, $forward = '', $exitafter = true, $no_conversion = false, $method = 'POST')
    {

        if ($no_conversion === 'FALSE')
            $this->common->mlog('Conversion forced', 'DEBUG');

        $this->setHttpReturnCode($status);

        if (is_null($forward))
            $forward = '';

        $data = $this->convertOutData($data, $content_type, $no_conversion);

        if ($forward !== '') {

            $headers = array('Content-type' => $content_type, 'Accept' => 'application/json', 'Expect' => '', 'X-Business-Id' => $this->business_id);
            $query = array('url' => $forward, 'method' => $method, 'headers' => $headers, 'data' => $data);
            $queries = array($query);

            $result = $this->forwardHttpQueries($queries);
            header("Content-type: " . $result[0]['response_headers']['Content-type']);
            return $result[0]['response_data'] . "\n";
        } else {
            header("Content-type: $content_type");

            return $this->convertOutData($data, $content_type, $no_conversion);
        }
        if ($exitafter === true)
            exit;
    }

    /**
     * function convertOutData
     * Formats data into encoded json if necessary
     */
    function convertOutData($data, $content_type, $no_conversion = false)
    {
        $this->common->mlog("Data to send", 'INFO');
        if (!$no_conversion && ($content_type == 'application/json')) {
            $this->common->mlog("Forced Conversion for $content_type", 'DEBUG');
            $dataJSON = array('payload' => $data);
            $this->common->mlog(json_encode($dataJSON), 'DEBUG', 'JSON');
            return json_encode($dataJSON);
        } else {
            $this->common->mlog($data, 'DEBUG', 'TXT');
            return $data;
        }
    }

    /**
     * function setReturnType
     * Determines return Content-Type
     */
    function setReturnType($accept, $default)
    {

        if (is_null($accept) || ($accept == ''))
            return $default;
        else {
            $types = explode(',', $accept);
            foreach ($types as $type) {
                if (stripos($type, 'application/xml') !== FALSE)
                    return 'application/xml';
                else if (stripos($type, 'application/json') !== FALSE)
                    return 'application/json';
            }
            $this->returnWithContentType('Supported return types are only application/xml and application/json', 'text/plain', 400);
        }
    }

    /**
     * function extractHeader
     * Extracts a specific Http Header value
     */
    static function extractHeader($header)
    {
        if (function_exists('apache_request_headers')) {
            $request_headers = apache_request_headers();
        } else {
            $request_headers = $_SERVER;
        }

        $conv_header = 'HTTP_' . strtoupper(preg_replace('/-/', '_', $header));

        if (array_key_exists($conv_header, $request_headers)) {
            return $request_headers[$conv_header];
        } else {
            if (array_key_exists(strtoupper($header), $request_headers)) {
                return $request_headers[strtolower($header)];
            } else {
                return '';
            }
        }
    }

    /**
     * function formatHeaders
     * Generates an array of http header values suitable for curl
     */
    function formatHeaders($headers)
    {
        $out_headers = array();

        foreach ($headers as $header => $value)
            $out_headers[] = $header . ': ' . $value;

        return $out_headers;
    }

    /**
     * array
     *      method 
     *      url
     *      headers
     *          url
     *          Accept
     *          Except
     *          Content-type
     *          X-Business-Id
     *          ...
     *      data
     *      response_code
     *      response_data
     */
    function forwardHttpQueries($queries)
    {
       
        if (is_null($queries) || !is_array($queries) || count($queries) == 0)
            return new Exception('No query to forward');

        $mh = curl_multi_init();
        $ch = array();

        foreach ($queries as $id => $query) {
            if (!array_key_exists('method', $query) || !array_key_exists('url', $query)) {
                $query['response_code'] = '400';
                $query['response_data'] = 'Invalid query : method and/or url missing';
                $this->common->mlog('Invalid url/method : method=' . $query['method'] . ', url=' . $query['url'], 'WARNING');
                break;
            }
            $this->common->mlog('Generate Curl call for ' . $query['method'] . ' ' . $query['url'], 'INFO');
            $ch[$id] = curl_init($query['url']);
       
            curl_setopt($ch[$id], CURLOPT_RETURNTRANSFER, 1);
            if ($query['method'] !== 'GET')
                curl_setopt($ch[$id], CURLOPT_POST, TRUE);
            if (array_key_exists('headers', $query) && (count($query['headers']) != 0))
                curl_setopt($ch[$id], CURLOPT_HTTPHEADER, $this->formatHeaders($query['headers']));
            if ($query['method'] !== 'GET')
                curl_setopt($ch[$id], CURLOPT_POSTFIELDS, $query['data']);
            curl_setopt($ch[$id], CURLOPT_SSL_VERIFYPEER, False);
            curl_setopt($ch[$id], CURLOPT_VERBOSE, True);
            curl_setopt($ch[$id], CURLOPT_HEADER, True);
            curl_setopt($ch[$id], CURLINFO_HEADER_OUT, True);
            curl_multi_add_handle($mh, $ch[$id]);
        }

        $this->common->mlog('Sending out curl calls', 'INFO');

        $running = NULL;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 10);
        } while ($running > 0);

        $this->common->mlog('Got all curl responses', 'INFO');

        foreach ($ch as $i => $handle) {

            $curlError = curl_error($handle);
            $content_length = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
            $queries[$i]['response_code'] = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $this->common->mlog("Curl #$i response code: " . $queries[$i]['response_code'], 'INFO');

            if ($curlError == "") {
                $bbody = curl_multi_getcontent($handle);
                $bheader = explode("\n", substr($bbody, 0, $content_length));
                $response_headers = array();
                foreach ($bheader as $header) {
                    $exp = preg_split("/\:\s/", $header);
                    if (count($exp) > 1)
                        $response_headers[$exp[0]] = $exp[1];
                }
                $queries[$i]['response_headers'] = $response_headers;
                $this->common->mlog("Curl #$i headers: " . print_r($queries[$i]['response_headers'], true), 'DEBUG');
                $queries[$i]['response_data'] = substr($bbody, $content_length);
                $this->common->mlog("Curl #$i response body: \n" . $queries[$i]['response_data'] . "\n", 'DEBUG');
            } else {
                $this->common->mlog("Curl $i returned error: $curlError ", "INFO");
                $this->common->mlog(var_dump(curl_getinfo($ch[$i]), true), 'INFO');
                $queries[$i]['response_data'] = "Error loop $i $curlError\n";
            }
            curl_multi_remove_handle($mh, $handle);
            curl_close($handle);

            curl_multi_close($mh);
        }

        return $queries;
    }

    function setHttpReturnCode($status)
    {
        switch ($status) {
            case 200:
                header("HTTP/1.1 200 OK", TRUE, 200);
                break;
            case 400:
                header("HTTP/1.1 400 MALFORMED URL", TRUE, 400);
                break;
            case 404:
                header("HTTP/1.1 404 NOT FOUND", TRUE, 404);
                break;
            case 500:
                header("HTTP/1.1 500 SERVER ERROR", TRUE, 500);
                break;
        }
    }
}
