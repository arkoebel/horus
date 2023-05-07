<?php

use OpenTelemetry\Extension\Propagator\B3\B3MultiPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;


class HorusHttp
{


    public $common = null;
    public $businessId = '';
    private $tracer = null;
    public const DELETE_TAG = 'TO_DELETE';
    public const EOL = "\r\n";
    private Horus_CurlInterface $curl;
    private Horus_HeaderInterface $headerInt;

    public function __construct($businessId, $logLocation, $colour, $tracer, Horus_CurlInterface $curl = null)
    {
        $this->common = new HorusCommon($businessId, $logLocation, $colour);
        $this->businessId = $businessId;

        $this->tracer = $tracer;
        if (!is_null($curl)) {
            $this->curl = $curl;
        } else {
            $this->curl = new Horus_Curl();
        }
        $this->headerInt = new Horus_Header();
    }

    public function setCurlImpl($curlImpl)
    {
        $this->curl = $curlImpl;
    }

    public function setHeaderImpl(Horus_HeaderInterface $headerImpl)
    {
        $this->headerInt = $headerImpl;
    }

    public static function getB3Headers($span)
    {
        $propagator = B3MultiPropagator::getInstance();
        $carrier=array();
        $ctx = $span->storeInContext(Context::getCurrent());
        $propagator->inject($carrier, null, $ctx);
        return $carrier;

    }

    /**
     * function formMultiPart
     * Generate a HTTP MultiPart section
     */
    public function formMultiPart($file, $data, $mimeBoundary, $eol, $contentType, $extraHeaders = array())
    {
        $rfhprefix = $this->common->cnf[HorusCommon::RFH_PREFIX];
        $mqmdprefix = $this->common->cnf[HorusCommon::MQMD_PREFIX];

        $cc = '';
        $cc .= '--' . $mimeBoundary . $eol;
        $cc .= "Content-Disposition: form-data; name=\"$file\"; filename=\"$file\"" . $eol;
        $cc .= 'Content-Type: ' . $contentType . $eol;
        $cc .= 'Content-Transfer-Encoding: base64' . $eol;
        foreach ($extraHeaders as $key => $value) {
            if (HorusHttp::DELETE_TAG!==$value) {
                $cc .= HorusHttp::formatMQOutHeader($key, $value, $rfhprefix, $mqmdprefix) . $eol;
            }
        }
        $cc .= $eol;
        return $cc . chunk_split(base64_encode($data)) . $eol;
    }

    public static function rebuildMultipart($files, $boundary, $eol)
    {
        $cc='';
        foreach ($files as $file) {
            $cc .= '--' . $boundary . $eol;
            $content = file_get_contents($file['tmp_name']);
            $contentType = $file['type'];
            $name = $file['name'];
            $cc .= "Content-Disposition: form-data; name=\"$name\"; filename=\"$name\"" . $eol;
            $cc .= 'Content-Type: ' . $contentType . $eol;
            $cc .= 'Content-Transfer-Encoding: base64' . $eol;
            $cc .= $content;
        }
        return $cc . '--' . $boundary . '--';
    }

    public static function formatMQOutHeader($key, $value, $rfhprefix, $mqmdprefix)
    {
        if (preg_match('/^' . $rfhprefix . '/', $key) || preg_match('/^' . $mqmdprefix . '/', $key)) {
            if (preg_match('/^' . HorusCommon::ENC_PREFIX . '/', $value)) {
                return $key . ': ' . $value;
            } else {
                return $key . ': ' .
                    HorusCommon::ENC_PREFIX .
                    urlencode(base64_encode($key . HorusCommon::ENC_SEP . $value));
            }
        } else {
            return $key . ': ' . $value;
        }
    }

    public function filterMQHeaders($headers, $outformat = 'EXPAND')
    {
        $rfhprefix = $this->common->cnf[HorusCommon::RFH_PREFIX];
        $mqmdprefix = $this->common->cnf[HorusCommon::MQMD_PREFIX];

        $res = array();
        foreach ($headers as $header => $value) {
            if ((preg_match('/' . $mqmdprefix . '/', $header) ||
                preg_match('/' . $rfhprefix . '/', $header)) &&
                (HorusHttp::DELETE_TAG !== $value)) {
                if ('EXPAND' === $outformat) {
                    if (preg_match('/^' . HorusCommon::ENC_PREFIX . '/', $value)) {
                        $res[$header] = $value;
                    } elseif (
                            preg_match('/^' . $rfhprefix . '/', $header) ||
                            preg_match('/^' . $mqmdprefix . '/', $header)) {
                        $res[$header] = HorusCommon::ENC_PREFIX .
                            urlencode(base64_encode($header . HorusCommon::ENC_SEP . $value));
                    } else {
                        $res[$header] = $header . ': ' . $value;
                    }
                } elseif ('NOTHING' == $outformat) {
                    $res[$header] = $value;
                } elseif ('UNPACK' === $outformat) {
                    if (preg_match('/^' . HorusCommon::ENC_PREFIX . '/', $value)) {

                        $val = base64_decode(urldecode(substr($value, strlen(HorusCommon::ENC_PREFIX))));
                        $tmp = explode(HorusCommon::ENC_SEP, $val);

                        $res[$tmp[0]] = $tmp[1];
                    } else {
                        $tmp = explode(':', $value);
                        if (count($tmp) > 1) {
                            $key = array_shift($tmp);
                            $val = ltrim(implode(':', $tmp));
                            $res[$key] = $val;
                        } else {
                            $res[$header] = $value;
                        }
                    }
                }
            }
        }
        return $res;
    }

    public function unpackHeaders($inHeaders)
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

    public static function formatOutHeaders($inHeaders, $rfhprefix = 'rfh2-', $mqmdprefix = 'mqmd-')
    {
        $outHeaders = array();
        foreach ($inHeaders as $key => $value) {
            if (HorusHttp::DELETE_TAG !== $value) {
                if (is_int($key)) {
                    if (is_array($value)) {
                        $a = strtolower($value[0]);
                        $b = ltrim(rtrim($value[1]));
                    } elseif (mb_strpos($value, ':') !== false) {
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
                if ((preg_match('/^' . $rfhprefix . '/', $a) ||
                        preg_match('/^' . $mqmdprefix . '/', $a)) &&
                        (preg_match('/^' . HorusCommon::ENC_PREFIX . '/', $b) === false)) {
                    $b = HorusCommon::ENC_PREFIX . base64_encode($a . HorusCommon::ENC_SEP . $b);
                }
                if (HorusHttp::DELETE_TAG!==$b) {
                    $outHeaders[] = $a . ': ' . $b;
                }
            }
        }
        return $outHeaders;
    }


    public static function cleanVariables($toRemove, $list)
    {
        $temp = array();
        foreach ($list as $key => $element) {
            if (in_array($key, $toRemove)) {
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
    public function returnArrayWithContentType(
        $data,
        $contentType,
        $status,
        $forward = '',
        $exitafter = true,
        $noConversion = false,
        $method = 'POST',
        $rootSpan = null,
        $rfhprefix = 'rfh-',
        $mqmdprefix = 'mqmd-'
        )
    {

        $injectSpan = $rootSpan;
        $injectSpan->addEvent('Http Call Lib for Arrays');

        if ($noConversion === false) {
            $this->common->mlog('Forced conversion', 'DEBUG');
        }
        $this->setHttpReturnCode($status);

        if (is_null($forward)) {
            $forward = '';
        }

        if ($forward !== '') {
            $headers = array(
                'Content-type' => $contentType,
                'Accept' => HorusCommon::JS_CT,
                'Expect' => '',
                'X-Business-Id' => $this->businessId);
            $queries = array();
            foreach ($data as $content) {
                $ct = $this->convertOutData($content, $contentType, $noConversion);
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
            $injectSpan->end();
        } else {
            $this->headerInt->sendHeader('Content-type: ' . $contentType);
            $ret = '';
            foreach ($data as $content) {
                $ret .= $this->convertOutData($content, $contentType, $noConversion) . "\n";
            }
            $injectSpan->end();
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
    public function returnWithContentType(
        $data,
        $contentType,
        $status,
        $forward = '',
        $noConversion = false,
        $method = 'POST',
        $returnHeaders = array(),
        $rootSpan = null,
        $rfhprefix = 'rfh-',
        $mqmdprefix = 'mqmd-'
        )
    {

        $injectSpan = $rootSpan;
        $injectSpan->addEvent('Http Call Lib');

        if ($noConversion === 'FALSE') {
            $this->common->mlog('Forced conversion to JSON', 'DEBUG');
        }

        if (is_null($forward)) {
            $forward = '';
        }

        $data = $this->convertOutData($data, $contentType, $noConversion);
        $this->common->mlog('Sending back data', 'DEBUG', 'TXT');
        $this->common->mlog($data, 'DEBUG', 'TXT');

        if ($forward !== '') {


            $headers = array(
                'Content-Type' => $contentType,
                'Accept' =>HorusCommon::JS_CT,
                'Expect' => '',
                'X-Business-Id' => $this->businessId);
            $mqheaders = $this->filterMQHeaders($returnHeaders, 'EXPAND');

            if (preg_match('/multipart/', $contentType) !== false) {
                $headersoff = array_merge($mqheaders, $headers);
            } else {
                $headersoff = $headers;
            }

            $query = array(
                'url' => $forward,
                'method' => $method,
                'headers' => $headersoff,
                'data' => is_array($data) ? $data[0] : $data);

            $queries = array($query);

            $result = $this->forwardHttpQueries($queries, $injectSpan, $rfhprefix, $mqmdprefix);
            $this->headerInt->sendHeader("Content-type: " . $result[0]['response_headers']['Content-Type']);
            foreach ($returnHeaders as $rh) {
                $this->headerInt->sendHeader($rh);
            }

            $injectSpan->end();
            return $result[0]['response_data'] . "\n";
        } else {
            $injectSpan->addEvent('Return to sender');
            $this->setHttpReturnCode($status);
            $this->headerInt->sendHeader("Content-Type: $contentType");
            if (null !== $returnHeaders && is_array($returnHeaders)) {
                foreach ($returnHeaders as $rh) {
                    $this->headerInt->sendHeader($rh);
                }
            }
            $injectSpan->end();
            return $data;
        }
    }

    /**
     * function convertOutData
     * Formats data into encoded json if necessary
     */
    public function convertOutData($data, $contentType, $noConversion = false)
    {
        if (!$noConversion && (substr($contentType, 0, 16) == HorusCommon::JS_CT)) {
            $this->common->mlog("Forced Conversion for $contentType", 'DEBUG');
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
    public function setReturnType($accept, $default)
    {

        if (is_null($accept) || ($accept == '')) {
            return $default;
        } else {
            $types = explode(',', $accept);
            foreach ($types as $type) {
                if (stripos($type, HorusCommon::XML_CT) !== false) {
                    return HorusCommon::XML_CT;
                } elseif (stripos($type, HorusCommon::JS_CT) !== false) {
                    return HorusCommon::JS_CT;
                }
            }
            $this->returnWithContentType(
                'Supported return types are only application/xml and application/json',
                'text/plain',
                400
            );
        }
    }

    /**
     * function extractHeader
     * Extracts a specific Http Header value
     */
    public static function extractHeader($header, $alternate=null)
    {
        if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (array_key_exists($header, $requestHeaders)) {
                return $requestHeaders[$header];
            } elseif (($alternate!==null) && array_key_exists($alternate, $requestHeaders)) {
                return $requestHeaders[$alternate];
            }
        } else {
            $requestHeaders = $_SERVER;
        }

        $convHeader = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        if (array_key_exists($convHeader, $requestHeaders)) {
            return $requestHeaders[$convHeader];
        } else {
            if (array_key_exists(strtoupper($header), $requestHeaders)) {
                return $requestHeaders[strtoupper($header)];
            } else {
                return '';
            }
        }
    }

    /**
     * function formatHeaders
     * Generates an array of http header values suitable for curl
     */
    public function formatHeaders($headers)
    {
        $outHeaders = array();

        foreach ($headers as $header => $value) {
            if (HorusHttp::DELETE_TAG!==$value) {
                $outHeaders[] = $header . ': ' . $value;
            }
        }

        return $outHeaders;
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
    public function forwardHttpQueries($queries, $rootSpan, $rfhprefix, $mqmdprefix)
    {

        if (is_null($queries) || !is_array($queries) || empty($queries)) {
            return new Exception('No query to forward');
        }

        $mh = $this->curl->curl_multi_init();
        $ch = array();

        $span = array();

        $ctx = $rootSpan->storeInContext(Context::getCurrent());

        foreach ($queries as $id => $query) {
            
            $span[$id] = $this
                ->tracer->spanBuilder('Send Query ' . $id)
                ->setParent($ctx)
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->startSpan();

            if (!array_key_exists('method', $query) || !array_key_exists('url', $query)) {
                $query['response_code'] = '400';
                $query['response_data'] = 'Invalid query : method and/or url missing';
                $this->common->mlog(
                    'Invalid url/method : method=' . $query['method'] . ', url=' . $query['url'],
                    'WARNING'
                );
                break;
            }
            $span[$id]->setAttribute('path', $query['url']);
            $span[$id]->setAttribute('method', $query['method']);
            $query['headers'] = HorusHttp::getB3Headers($span[$id]);
            $this->common->mlog('Generate Curl call for ' . $query['method'] . ' ' . $query['url'], 'INFO');
            $ch[$id] = $this->curl->curl_init($query['url']);

            $span[$id]->addEvent('Prepare Curl');

            $this->curl->curl_setopt($ch[$id], CURLOPT_RETURNTRANSFER, 1);
            if ($query['method'] !== 'GET') {
                $this->curl->curl_setopt($ch[$id], CURLOPT_POST, true);
                $this->curl->curl_setopt($ch[$id], CURLOPT_POSTFIELDS, $query['data']);
            }
            if (array_key_exists('headers', $query) && (count($query['headers']) != 0)) {
                $this->curl->curl_setopt(
                    $ch[$id],
                    CURLOPT_HTTPHEADER,
                    HorusHttp::formatOutHeaders($query['headers'], $rfhprefix, $mqmdprefix)
                );
                $this->common->mlog(
                    'Actual headers passed to the next query : ' . print_r(
                        HorusHttp::formatOutHeaders(
                            $query['headers'],
                            $rfhprefix,
                            $mqmdprefix
                        ),
                        true
                    ),
                    'DEBUG'
                );
            }

            $this->curl->curl_setopt($ch[$id], CURLOPT_SSL_VERIFYPEER, false);
            $this->curl->curl_setopt($ch[$id], CURLOPT_VERBOSE, true);
            $this->curl->curl_setopt($ch[$id], CURLOPT_HEADER, true);
            $this->curl->curl_setopt($ch[$id], CURLINFO_HEADER_OUT, true);
            $this->curl->curl_multi_add_handle($mh, $ch[$id]);
        }

        $this->common->mlog('Sending out curl calls', 'INFO');

        $rootSpan->addEvent('Send multiple HTTP queries');
        $running = null;
        do {
            $this->curl->curl_multi_exec($mh, $running);
            $this->curl->curl_multi_select($mh, 10);
        } while ($running > 0);

        $rootSpan->addEvent('Received all HTTP responses');
        $this->common->mlog('Got all curl responses', 'INFO');
        foreach ($ch as $i => $handle) {
            $curlError = $this->curl->curl_error($handle);
            $contentLength = $this->curl->curl_getinfo($handle, CURLINFO_HEADER_SIZE);
            $queries[$i]['response_code'] = $this->curl->curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $span[$i]->setAttribute('ResponseCode', $queries[$i]['response_code']);
            $this->common->mlog("Curl #$i response code: " . $queries[$i]['response_code'], 'INFO');

            if (("" == $curlError)||(CURLE_OK === $curlError)) {
                $bbody = $this->curl->curl_multi_getcontent($handle);
                $bheader = explode("\n", substr($bbody, 0, $contentLength));
                $responseHeaders = array();
                foreach ($bheader as $header) {
                    $exp = preg_split("/\:\s/", $header);
                    if (count($exp) > 1) {
                        $responseHeaders[$exp[0]] = $exp[1];
                    }
                }
                $queries[$i]['response_headers'] = $responseHeaders;
                $this->common->mlog("Curl #$i headers: " . print_r($queries[$i]['response_headers'], true), 'DEBUG');
                $queries[$i]['response_data'] = substr($bbody, $contentLength);
                $this->common->mlog("Curl #$i response body: \n" . $queries[$i]['response_data'] . "\n", 'DEBUG');
            } else {
                $this->common->mlog("Curl $i returned error: $curlError ", "INFO");
                $this->common->mlog(print_r($this->curl->curl_getinfo($ch[$i]), true), 'INFO');
                $queries[$i]['response_data'] = "Error loop $i $curlError\n";
            }
            $this->curl->curl_multi_remove_handle($mh, $handle);
            $this->curl->curl_close($handle);

            $span[$i]->end();
        }
        $this->curl->curl_multi_close($mh);
        return $queries;
    }


    public function forwardSingleHttpQuery(
        $destUrl,
        $headers,
        $data,
        $method = 'POST',
        $span = null,
        $rfhprefix = 'rfh2-',
        $mqmdprefix = 'mqmd-'
        )
    {

        $currentSpan = $span;
        $currentSpan->addEvent('Forward Http Query');
        $headers = array_merge($headers, HorusHttp::getB3Headers($currentSpan));

        $handle = $this->curl->curl_init($destUrl);
        $headersout = array();
        $this->curl->curl_setopt($handle, CURLOPT_URL, $destUrl);
        $this->curl->curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        if ('POST' === $method) {
            $this->curl->curl_setopt($handle, CURLOPT_POST, true);
        } else {
            $this->curl->curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        }
        $this->curl->curl_setopt(
            $handle,
            CURLOPT_HTTPHEADER,
            HorusHttp::formatOutHeaders($headers, $rfhprefix, $mqmdprefix));
        $this->curl->curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        $this->curl->curl_setopt(
            $handle,
            CURLOPT_HEADERFUNCTION,
            function ($crl, $header) use (&$headersout) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) { // ignore invalid headers
                    return $len;
                }
                $headersout[strtolower(trim($header[0]))] = trim($header[1]);

                return $len;
            }
        );

        $this->common->mlog($method . ' ' . $destUrl . "\n" . implode("\n", $headers) . "\n\n", 'DEBUG');
        $response = $this->curl->curl_exec($handle);
        $responseCode = $this->curl->curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $currentSpan->setAttribute('Return Code', $responseCode);
        if (200 !== $responseCode) {
            $this->common->mlog(
                'Request to ' .
                $destUrl .
                ' produced error ' .
                $this->curl->curl_getinfo($handle, CURLINFO_HTTP_CODE),
                'ERROR'
            );
            $this->common->mlog('Call stack was : ' . print_r($this->curl->curl_getinfo($handle), true), 'DEBUG');
            $this->common->mlog('Error response : ' . $response, 'DEBUG');
            $currentSpan->end();
            throw new HorusException('HTTP Error ' . $responseCode . ' for ' . $destUrl);
        } else {
            $this->common->mlog("Query result was $responseCode \n", 'DEBUG');
            $this->common->mlog('Return Headers : ' . implode("\n", $headersout) . "\n", 'DEBUG');
            $currentSpan->end();
        }

        return array('body' => $response, 'headers' => $headersout);
    }

    public function setHttpReturnCode($status)
    {
        switch ($status) {
            case 200:
                $this->headerInt->sendHeader("HTTP/1.1 200 OK", true, 200);
                break;
            case 400:
                $this->headerInt->sendHeader("HTTP/1.1 400 MALFORMED URL", true, 400);
                break;
            case 404:
                $this->headerInt->sendHeader("HTTP/1.1 404 NOT FOUND", true, 404);
                break;
            case 500:
                $this->headerInt->sendHeader("HTTP/1.1 500 SERVER ERROR", true, 500);
                break;
            default:
                $this->headerInt->sendHeader("HTTP/1.1 500 SERVER ERROR", true, 500);
        }
    }

    public static function formatQueryString($url, $params, $exclude = array())
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
                if ($i !== false) {
                    $kk = substr($key, $i + 8);
                }
                if (strlen(urlencode($value)) < HorusCommon::QUERY_PARAM_CUTOFF) {
                    $pp[$kk] = $value;
                } else {
                    //log error_log('QQQ4 dropped ' . $value . "\n");
                }
            }
        }

        foreach ($pp as $key => $value) {
            if (HorusHttp::DELETE_TAG!==$value) {
                $res .= '&' . urlencode($key) . '=' . urlencode($value);
            }
        }

        if (!strpos($url, '?')) {
            if (strlen($res) > 0) {
                return $url . '?' . substr($res, 1);
            } else {
                return $url;
            }
        } else {
            return $url . $res;
        }
    }
}
