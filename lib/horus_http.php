<?php

use OpenTracing\Formats;
use OpenTracing\GlobalTracer;
use Jaeger\Config;

class HorusHttp
{

    public $common = null;
    public $business_id = '';
    private $tracer = null;
    public const DELETE_TAG = 'TO_DELETE';

    function __construct($business_id, $log_location, $colour, $tracer)
    {
        $this->common = new HorusCommon($business_id, $log_location, $colour);
        $this->business_id = $business_id;

        $this->tracer = $tracer;
    }

    /**
     * function formMultiPart
     * Generate a HTTP MultiPart section
     */
    function formMultiPart($file, $data, $mime_boundary, $eol, $content_type, $extraHeaders = array())
    {
        $rfhprefix = $this->common->cnf[HorusCommon::RFH_PREFIX];
        $mqmdprefix = $this->common->cnf[HorusCommon::MQMD_PREFIX];

        $cc = '';
        $cc .= '--' . $mime_boundary . $eol;
        $cc .= "Content-Disposition: form-data; name=\"$file\"; filename=\"$file\"" . $eol;
        $cc .= 'Content-Type: ' . $content_type . $eol;
        $cc .= 'Content-Transfer-Encoding: base64' . $eol;
        foreach ($extraHeaders as $key => $value)
            if(HorusHttp::DELETE_TAG!==$value)
                $cc .= HorusHttp::formatMQOutHeader($key, $value, $rfhprefix, $mqmdprefix) . $eol;
        $cc .= $eol;
        return $cc . chunk_split(base64_encode($data)) . $eol;
    }

    static function formatMQOutHeader($key, $value, $rfhprefix, $mqmdprefix)
    {
        if (preg_match('/^' . $rfhprefix . '/', $key) || preg_match('/^' . $mqmdprefix . '/', $key))
            if (preg_match('/^' . HorusCommon::ENC_PREFIX . '/', $value))
                return $key . ': ' . $value;
            else
                return $key . ': ' . HorusCommon::ENC_PREFIX . urlencode(base64_encode($key . HorusCommon::ENC_SEP . $value));
        else
            return $key . ': ' . $value;
    }

    function filterMQHeaders($headers, $outformat = 'EXPAND')
    {
        $rfhprefix = $this->common->cnf[HorusCommon::RFH_PREFIX];
        $mqmdprefix = $this->common->cnf[HorusCommon::MQMD_PREFIX];

        $res = array();
        foreach ($headers as $header => $value) {
            if ((preg_match('/' . $mqmdprefix . '/', $header) || preg_match('/' . $rfhprefix . '/', $header)) && (HorusHttp::DELETE_TAG !== $value)) {
                if ('EXPAND' === $outformat) {
                    //error_log('Expand ' . $header . ',' . $value . ' ',3,'/var/log/horus/horus_http.log' );
                    if (preg_match('/^' . HorusCommon::ENC_PREFIX . '/', $value)) {
                        $res[$header] = $value;
                        //error_log('Unchanged ' . $header . '/' . $value . "\n",3,'/var/log/horus/horus_http.log' );

                    } else if (preg_match('/^' . $rfhprefix . '/', $header) || preg_match('/^' . $mqmdprefix . '/', $header)) {
                        $res[$header] = HorusCommon::ENC_PREFIX . urlencode(base64_encode($header . HorusCommon::ENC_SEP . $value));
                        //error_log('Encoded ' . $header . '/' . $res[$header] . "\n",3,'/var/log/horus/horus_http.log' );
                    } else {
                        $res[$header] = $header . ': ' . $value;
                        //error_log('Appended ' . $header . '/' . $res[$header] . "\n",3,'/var/log/horus/horus_http.log' );
                    }
                } else if ('NOTHING' == $outformat)
                    $res[$header] = $value;
                else if ('UNPACK' === $outformat) {
                    //error_log('Unpack ' . $header . ',' . $value . ' ',3,'/var/log/horus/horus_http.log' );

                    if (preg_match('/^' . HorusCommon::ENC_PREFIX . '/', $value)) {

                        $val = base64_decode(urldecode(substr($value, strlen(HorusCommon::ENC_PREFIX))));
                        $tmp = explode(HorusCommon::ENC_SEP, $val);

                        //error_log('Decoded ' . $tmp[0] . '/' . $tmp[1] . "\n",3,'/var/log/horus/horus_http.log' );

                        $res[$tmp[0]] = $tmp[1];
                    } else {
                        //error_log('Exploded ',3,'/var/log/horus/horus_http.log' );
                        $tmp = explode(':', $value);
                        if (count($tmp) > 1) {
                            $key = array_shift($tmp);
                            $val = ltrim(implode(':', $tmp));
                            //error_log('MQ Header : ' . $key . ', ' . $val . "\n", 3, '/var/log/horus/horus_http.log');
                            //error_log($key . '/' . $val . "\n",3,'/var/log/horus/horus_http.log' );
                            $res[$key] = $val;
                        } else {
                            //error_log($header . '/' . $value . "\n",3,'/var/log/horus/horus_http.log' );
                            $res[$header] = $value;
                        }
                    }
                }
            }
        }
        return $res;
    }

    function unpackHeaders($inHeaders)
    {
        $outHeaders = array();

        foreach ($inHeaders as $header) {
            $i = strpos($header, 'x-horus-');
            if ($i >= 0) {
                $cut = explode(': ', substr($header, $i), 2);
                $kk = $cut[0];
                $outHeaders[$kk] = $cut[1];
            }
        }
        return $outHeaders;
    }

    static function formatOutHeaders($inHeaders, $rfhprefix = 'rfh2-', $mqmdprefix = 'mqmd-')
    {
        $outHeaders = array();
        foreach ($inHeaders as $key => $value) {
            if (HorusHttp::DELETE_TAG !== $value) {
                if (is_int($key)) {
                    if (is_array($value)) {
                        $a = strtolower($value[0]);
                        $b = ltrim(rtrim($value[1]));
                    } else if (mb_strpos($value, ':') !== false) {
                        $elt = explode(':', $value);
                        $key = array_shift($elt);
                        $value = implode(':', $elt);
                        $a = strtolower($key);
                        $b = ltrim(rtrim($value));
                    }
                } else {
                    $a = strtolower($key);
                    $b = ltrim(rtrim($value));
                }
                //error_log('Conv Out Headers 1 = ' . $a . '/' . $b . "\n",3,'/var/log/horus/horus_http.log' );
                if ((preg_match('/^' . $rfhprefix . '/', $a) || preg_match('/^' . $mqmdprefix . '/', $a)) && (preg_match('/^' . HorusCommon::ENC_PREFIX . '/', $b) === false)) {
                    $b = HorusCommon::ENC_PREFIX . base64_encode($a . HorusCommon::ENC_SEP . $b);
                }
                //error_log('Conv Out Headers 2 = ' . $a . '/' . $b . "\n",3,'/var/log/horus/horus_http.log' );
                if(HorusHttp::DELETE_TAG!==$b)
                    $outHeaders[] = $a . ': ' . $b;
            }
        }
        return $outHeaders;
    }


    static function cleanVariables($to_remove, $list)
    {
        $temp = array();
        foreach ($list as $key => $element) {
            if (in_array($key, $to_remove)) {
                //remove
            } else {
                $temp[$key] = urldecode($element);
            }
        }
        return array_merge($temp);
    }

    /**
     * function returnArrayWithContentType
     * Send a set of http queries to the same destination
     */
    function returnArrayWithContentType($data, $content_type, $status, $forward = '', $exitafter = true, $no_conversion = false, $method = 'POST', $rootSpan = null, $rfhprefix = 'rfh-', $mqmdprefix = 'mqmd-')
    {

        $injectSpan = $this->tracer->startSpan('Http Call Lib for Arrays', ['child_of' => $rootSpan]);

        if ($no_conversion === FALSE) {
            $this->common->mlog('Forced conversion', 'DEBUG');
        }
        $this->setHttpReturnCode($status);

        if (is_null($forward)) {
            $forward = '';
        }

        if ($forward !== '') {
            $headers = array('Content-type' => $content_type, 'Accept' => 'application/json', 'Expect' => '', 'X-Business-Id' => $this->business_id);
            $queries = array();
            foreach ($data as $content) {
                $ct = $this->convertOutData($content, $content_type, $no_conversion);
                $query = array('url' => $forward, 'method' => $method, 'headers' => $headers, 'data' => $ct);
                $queries[] = $query;
            }

            $result = $this->forwardHttpQueries($queries, $injectSpan, $rfhprefix, $mqmdprefix);
            $responses = array();
            $json = false;

            foreach ($result as $content) {
                if (stripos($content['response_headers']['content-Type'], 'json') > 0) {
                    $json = true;
                    $responses[] = json_decode($content['response_data'], true);
                } else {
                    $responses[] = $content['response_data'];
                }
            }

            if ($json) {
                echo json_encode($responses);
            } else {
                echo implode("\n", $responses);
            }
            $injectSpan->finish();
        } else {
            header('Content-type: ' . $content_type);
            $ret = '';
            foreach ($data as $content) {
                $ret .= $this->convertOutData($content, $content_type, $no_conversion) . "\n";
            }
            $injectSpan->finish();
            return $ret;
        }

        if ($exitafter === true) {
            exit;
        }
    }

    /**
     * function returnWithContentType
     * Sends a http query to the next step or returns response
     */
    function returnWithContentType($data, $content_type, $status, $forward = '', $no_conversion = false, $method = 'POST', $returnHeaders = array(), $rootSpan = null, $rfhprefix = 'rfh-', $mqmdprefix = 'mqmd-')
    {

        $injectSpan = $this->tracer->startSpan('Http call lib', ['child_of' => $rootSpan]);

        if ($no_conversion === 'FALSE') {
            $this->common->mlog('Forced conversion to JSON', 'DEBUG');
        }

        //$this->setHttpReturnCode($status);

        if (is_null($forward)) {
            $forward = '';
        }

        $data = $this->convertOutData($data, $content_type, $no_conversion);
        $this->common->mlog('Sending back data', 'DEBUG', 'TXT');
        $this->common->mlog($data, 'DEBUG', 'TXT');

        if ($forward !== '') {
            //$injectSpan->log(['message'=>'Send HTTP Queries to destination']);
            //$injectSpan->setTag('destination',$forward);

            $headers = array('Content-Type' => $content_type, 'Accept' => 'application/json', 'Expect' => '', 'X-Business-Id' => $this->business_id);
            $mqheaders = $this->filterMQHeaders($returnHeaders, 'EXPAND');

            if (preg_match('/multipart/', $content_type) !== false)
                $headersoff = array_merge($mqheaders, $headers);
            else
                $headersoff = $headers;

            $query = array('url' => $forward, 'method' => $method, 'headers' => $headersoff, 'data' => is_array($data) ? $data[0] : $data);
            $queries = array($query);

            $result = $this->forwardHttpQueries($queries, $injectSpan, $rfhprefix, $mqmdprefix);
            header("Content-type: " . $result[0]['response_headers']['Content-Type']);
            foreach ($returnHeaders as $rh) {
                header($rh);
            }

            $injectSpan->finish();
            return $result[0]['response_data'] . "\n";
        } else {
            $injectSpan->log(['message' => 'Return to sender']);
            $this->setHttpReturnCode($status);
            header("Content-Type: $content_type");
            if (null !== $returnHeaders && is_array($returnHeaders)) {
                foreach ($returnHeaders as $rh) {
                    header($rh);
                }
            }
            $injectSpan->finish();
            return $data;
        }
    }

    /**
     * function convertOutData
     * Formats data into encoded json if necessary
     */
    function convertOutData($data, $content_type, $no_conversion = false)
    {
        if (!$no_conversion && ($content_type == 'application/json')) {
            $this->common->mlog("Forced Conversion for $content_type", 'DEBUG');
            $dataJSON = array('payload' => $data);
            $this->common->mlog(json_encode($dataJSON), 'DEBUG', 'JSON');
            return json_encode($dataJSON);
        } else {
            return $data;
        }
    }

    /**
     * function setReturnType
     * Determines return Content-Type
     */
    function setReturnType($accept, $default)
    {

        if (is_null($accept) || ($accept == '')) {
            return $default;
        } else {
            $types = explode(',', $accept);
            foreach ($types as $type) {
                if (stripos($type, 'application/xml') !== FALSE) {
                    return 'application/xml';
                } elseif (stripos($type, 'application/json') !== FALSE) {
                    return 'application/json';
                }
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
            if (array_key_exists($header, $request_headers)) {
                return $request_headers[$header];
            }
        } else {
            $request_headers = $_SERVER;
        }

        $conv_header = 'HTTP_' . strtoupper(preg_replace('/-/', '_', $header));
        if (array_key_exists($conv_header, $request_headers)) {
            return $request_headers[$conv_header];
        } else {
            if (array_key_exists(strtoupper($header), $request_headers)) {
                return $request_headers[strtoupper($header)];
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

        foreach ($headers as $header => $value) {
            if(HorusHttp::DELETE_TAG!==$value)
                $out_headers[] = $header . ': ' . $value;
        }

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
    function forwardHttpQueries($queries, $rootSpan, $rfhprefix, $mqmdprefix)
    {

        if (is_null($queries) || !is_array($queries) || count($queries) == 0) {
            return new Exception('No query to forward');
        }

        $mh = curl_multi_init();
        $ch = array();

        $span = array();

        foreach ($queries as $id => $query) {
            $span[$id] = $this->tracer->startSpan('Send Query ' . $id, ['child_of' => $rootSpan]);

            if (!array_key_exists('method', $query) || !array_key_exists('url', $query)) {
                $query['response_code'] = '400';
                $query['response_data'] = 'Invalid query : method and/or url missing';
                $this->common->mlog('Invalid url/method : method=' . $query['method'] . ', url=' . $query['url'], 'WARNING');
                break;
            }
            $span[$id]->setTag('path', $query['url']);
            $span[$id]->setTag('method', $query['method']);
            $this->tracer->inject($span[$id]->spanContext, Formats\TEXT_MAP, $query['headers']);

            $this->common->mlog('Generate Curl call for ' . $query['method'] . ' ' . $query['url'], 'INFO');
            $ch[$id] = curl_init($query['url']);

            $span[$id]->log(['message' => 'Prepare Curl']);

            curl_setopt($ch[$id], CURLOPT_RETURNTRANSFER, 1);
            if ($query['method'] !== 'GET') {
                curl_setopt($ch[$id], CURLOPT_POST, TRUE);
                curl_setopt($ch[$id], CURLOPT_POSTFIELDS, $query['data']);
            }
            if (array_key_exists('headers', $query) && (count($query['headers']) != 0)) {
                curl_setopt($ch[$id], CURLOPT_HTTPHEADER, HorusHttp::formatOutHeaders($query['headers'], $rfhprefix, $mqmdprefix));
                $this->common->mlog('Actual headers passed to the next query : ' . print_r(HorusHttp::formatOutHeaders($query['headers'], $rfhprefix, $mqmdprefix), true), 'DEBUG');
                //curl_setopt($ch[$id], CURLOPT_HTTPHEADER, $this->formatHeaders($query['headers']));
            }

            curl_setopt($ch[$id], CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch[$id], CURLOPT_VERBOSE, true);
            curl_setopt($ch[$id], CURLOPT_HEADER, true);
            curl_setopt($ch[$id], CURLINFO_HEADER_OUT, true);
            curl_multi_add_handle($mh, $ch[$id]);
        }

        $this->common->mlog('Sending out curl calls', 'INFO');

        $rootSpan->log(['message' => 'Send multiple HTTP queries']);
        $running = NULL;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 10);
        } while ($running > 0);

        $rootSpan->log(['message' => 'Received all HTTP responses']);
        $this->common->mlog('Got all curl responses', 'INFO');
        foreach ($ch as $i => $handle) {
            $curlError = curl_error($handle);
            $content_length = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
            $queries[$i]['response_code'] = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $span[$i]->setTag('ResponseCode', $queries[$i]['response_code']);
            $this->common->mlog("Curl #$i response code: " . $queries[$i]['response_code'], 'INFO');

            if ($curlError == "") {
                $bbody = curl_multi_getcontent($handle);
                $bheader = explode("\n", substr($bbody, 0, $content_length));
                $response_headers = array();
                foreach ($bheader as $header) {
                    $exp = preg_split("/\:\s/", $header);
                    if (count($exp) > 1) {
                        $response_headers[$exp[0]] = $exp[1];
                    }
                }
                $queries[$i]['response_headers'] = $response_headers;
                $this->common->mlog("Curl #$i headers: " . print_r($queries[$i]['response_headers'], true), 'DEBUG');
                $queries[$i]['response_data'] = substr($bbody, $content_length);
                $this->common->mlog("Curl #$i response body: \n" . $queries[$i]['response_data'] . "\n", 'DEBUG');
            } else {
                $this->common->mlog("Curl $i returned error: $curlError ", "INFO");
                $this->common->mlog(print_r(curl_getinfo($ch[$i]), true), 'INFO');
                $queries[$i]['response_data'] = "Error loop $i $curlError\n";
            }
            curl_multi_remove_handle($mh, $handle);
            curl_close($handle);

            curl_multi_close($mh);
            $span[$i]->finish();
        }

        return $queries;
    }


    function forwardSingleHttpQuery($dest_url, $headers, $data, $method = 'POST', $span, $rfhprefix = 'rfh2-', $mqmdprefix = 'mqmd-')
    {
        $currentSpan = $this->tracer->startSpan('Forward Http query', ['child_of' => $span]);
        $this->tracer->inject($currentSpan->spanContext, Formats\TEXT_MAP, $headers);

        $handle = curl_init($dest_url);
        $headersout = array();
        curl_setopt($handle, CURLOPT_URL, $dest_url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        if ('POST' === $method) {
            curl_setopt($handle, CURLOPT_POST, TRUE);
        } else {
            curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        }
        curl_setopt($handle, CURLOPT_HTTPHEADER, HorusHttp::formatOutHeaders($headers, $rfhprefix, $mqmdprefix));
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        curl_setopt(
            $handle,
            CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headersout) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;

                $headersout[strtolower(trim($header[0]))] = trim($header[1]);

                return $len;
            }
        );

        $this->common->mlog($method . ' ' . $dest_url . "\n" . implode("\n", $headers) . "\n\n", 'DEBUG');
        $response = curl_exec($handle);
        $response_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $currentSpan->setTag('Return Code', $response_code);
        if (200 !== $response_code) {
            $this->common->mlog('Request to ' . $dest_url . ' produced error ' . curl_getinfo($handle, CURLINFO_HTTP_CODE), 'ERROR');
            $this->common->mlog('Call stack was : ' . curl_getinfo($handle), 'DEBUG');
            $currentSpan->finish();
            throw new HorusException('HTTP Error ' . $response_code . ' for ' . $dest_url);
        } else {
            $this->common->mlog("Query result was $response_code \n", 'DEBUG');
            $this->common->mlog('Return Headers : ' . implode("\n", $headersout) . "\n", 'DEBUG');
            $currentSpan->finish();
        }

        return array('body' => $response, 'headers' => $headersout);
    }

    public function setHttpReturnCode($status)
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
            default:
                header("HTTP/1.1 500 SERVER ERROR", TRUE, 500);
        }
    }

    static function formatQueryString($url, $params, $exclude = array())
    {
        $res = '';
        $pp = array();
        foreach ($params as $k1 => $v1) {
            if (is_int($k1) && is_array($v1) && array_key_exists('key', $v1) && array_key_exists('value', $v1)) {
                $key = $v1['key'];
                $value = $v1['value'];
            } else {
                $key = $k1;
                $value = $v1;
            }
            if (!in_array($key, $exclude, true)) {
                $i = strpos($key, 'x-horus-');
                $kk = $key;
                if ($i !== FALSE)
                    $kk = substr($key, $i + 8);
                if (strlen(urlencode($value)) < 50) {
                    $pp[$kk] = $value;
                } else {
                    //error_log('QQQ4 dropped ' . $value . "\n");
                }
            }
        }

        foreach ($pp as $key => $value)
            if(HorusHttp::DELETE_TAG!==$value)
                $res .= '&' . urlencode($key) . '=' . urlencode($value);

        if (!strpos($url, '?')) {
            if (strlen($res) > 0)
                return $url . '?' . substr($res, 1);
            else
                return $url;
        } else {
            return $url . $res;
        }
    }
}
