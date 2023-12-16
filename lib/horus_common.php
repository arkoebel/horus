<?php

//should use require_once 'vendor/autoload.php';

class HorusCommon
{
    private $logLocation = '';
    private $businessId = '';
    private $colour = '';
    public $cnf = array();
    public const RFH_PREFIX = 'rfh2Prefix';
    public const MQMD_PREFIX = 'mqmdPrefix';
    public const ENC_PREFIX = 'B64PRF-';
    public const ENC_SEP = '#!#';
    public const DEFAULT_LOG_LOCATION = '/var/log/horus/horus_http.log';
    public const HORUS_CONFIG = 'conf/horusConfig.json';
    public const QUERY_PARAM_CUTOFF = 80;
    public const XML_CT = 'application/xml';
    public const JS_CT = 'application/json';
    public const TID_HEADER = 'X-Business-Id';
    public const DEST_HEADER = 'X_destination_url';
    public const HTTP_200_RETURN = 'HTTP/1.1 200 OK';
    public const HTTP_500_RETURN = 'HTTP/1.1 500 SERVER ERROR';

    public function __construct($businessId, $logLocation, $colour = 'GREEN')
    {
        $this->logLocation = $logLocation;
        $this->businessId = $businessId;
        $this->colour = $colour;
        $this->cnf = json_decode(file_get_contents(HorusCommon::HORUS_CONFIG), true);
    }


    public static function implodeAssArray(array $input, string $fieldSep, string $lineSep): string
    {
        $temp = array();
        foreach($input as $key=>$val){
            $temp[] = $key . $fieldSep . $val;
        }
        return implode($lineSep, $temp);
    }

    public static function explodeAssArray(string $input, string $fieldSep, string $lineSep): array
    {
        $temp = explode($lineSep, $input);
        $output = array();
        foreach($temp as $line){
            $vv = explode($fieldSep, $line);
            $output[$vv[0]] = $vv[1];
        }
        return $output;
    }

    public static function getConfValue($key, $default = null){
        $cnf = json_decode(file_get_contents(HorusCommon::HORUS_CONFIG), true);
        if (array_key_exists($key, $cnf)) {
            return $cnf[$key];
        } else {
            return $default;
        }
    }

    public static function getHttpHeaders()
    {
        if (function_exists('apache_request_headers')) {
            return apache_request_headers();
        } else {
            return array();
        }
    }

    public static function getPath($vars)
    {
        $patharray = explode('/', $vars['SCRIPT_FILENAME']);
        array_pop($patharray);
        return array_pop($patharray);
    }

    /**
     * Function myErrorHandler
     * Custom error handler to use our private logging facilities.
     */
    public function myErrorHandler($errno, $errstr, $errfile, $errline)
    {

        $this->mlog("Error $errno at $errfile ( $errline ) : $errstr", 'ERROR', 'TXT');

        return false;
    }

    /**
     * Function mlog
     * Private logging facilities. Format message into JSON for easy ES integration.
     */
    public function mlog($message, $logLevel, $format = 'TXT')
    {
        HorusCommon::logger($message, $logLevel, $format, $this->colour, $this->businessId, $this->logLocation);
    }

    /**
     * Function logger
     * Private logging facilities for use in static functions. Format message into JSON for easy ES integration.
     */
    public static function logger(
       $message,
       $logLevel,
       $format = 'TXT',
       $colour = 'BLANK',
       $businessId = '123',
       $logLocation = HorusCommon::DEFAULT_LOG_LOCATION
    )
    {

        $alog = array();
        $alog['timestamp'] = HorusCommon::utcTime(5);
        $alog['program'] = $colour;
        $alog['log_level'] = $logLevel;
        $alog['file'] = $_SERVER["PHP_SELF"];
        $alog['business_id'] = $businessId;
        $alog['pid'] = getmypid();
        if ($format === 'TXT') {
            $alog['message'] = $message;
        } else {
            $alog['message'] = HorusCommon::escapeJsonString($message);
        }
        if (is_null($logLocation)) {
            error_log(json_encode($alog) . "\n");
        } else {
            error_log(json_encode($alog) . "\n", 3, $logLocation);
        }

        if (json_last_error() != 0) {
            error_log(json_last_error_msg());
        }
    }

    /**
     * function utcTime
     * Gets back the current date in ISO-8601 format with variable precision on seconds.
     */
    public static function utcTime($precision = 0)
    {
        $time = gettimeofday();

        if (is_int($precision) && $precision >= 0 && $precision <= 6) {
            $total = (string) $time['sec'] . '.' . str_pad((string) $time['usec'], 6, '0', STR_PAD_LEFT);
            $totalRounded = bcadd($total, '0.' . str_repeat('0', $precision) . '5', $precision);
            @list($integer, $fraction) = explode('.', $totalRounded);
            $format = $precision == 0
            ? "Y-m-d\TH:i:s\Z"
            : "Y-m-d\TH:i:s," . $fraction . "\Z";
            return gmdate($format, $integer);
        }

        return false;
    }

    public static function getNewBusinessId()
    {

        $data = random_bytes(16);

        $data[6] = chr(ord($data[6])&0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8])&0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * function escapeJsonString
     * Escapes a JSON String to integrate inside a JSON value
     */
    public static function escapeJsonString($value)
    {
        $escapers = array('"', '/');
        $replacements = array('\"', '\/');
        return str_replace($escapers, $replacements, $value);
    }

    public static function formatQueryString($baseUrl, $params, $wholeUrl = false)
    {
        if (is_null($params)) {
            if (true === $wholeUrl) {
                return $baseUrl;
            } else {
                return '';
            }
        }

        $query = '';
        $converted = array();
        // Cycles all parameters; key duplicates are set as the last encountered value
        foreach ($params as $id => $param) {
                if ((is_array($param)) && (array_key_exists('key', $param)) && (array_key_exists('value', $param))) {
                    $converted[$param['key']] = $param['value'];
                } elseif (
                        (is_array($param))
                        && (array_key_exists('key', $param))
                        && (array_key_exists('phpvalue', $param)))
                    {
                    try {
                        ob_start();
                        eval($param['phpvalue']);
                        $converted[$param['key']] = urlencode(ob_get_contents());
                        ob_end_clean();
                    } catch (\Throwable $th) {
                        // Do nothing
                    }

                }elseif (!is_array($param)) {
                    $converted[$id] = $param;
                }
        }

        ksort($converted);

        foreach ($converted as $key => $value) {
            if (strlen(urlencode($value)) < HorusCommon::QUERY_PARAM_CUTOFF) {
                $query .= '&' . urlencode($key) . '=' . urlencode($value);
            }
        }
        if ((stripos($baseUrl, '?') === false) && ($query !== '')) {
            $query = '?' . substr($query, 1);
        }

        return ($wholeUrl) ? $baseUrl . $query : $query;
    }

    /**
     * function libxml_display_error
     * Custom SimpleXML error handler.
     */
    public function libxml_display_error($error)
    {
        $ret = "";
        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $ret .= "Warning $error->code : ";
                break;
            case LIBXML_ERR_ERROR:
                $ret .= "Error $error->code : ";
                break;
            case LIBXML_ERR_FATAL:
                $ret .= "Fatal Error $error->code : ";
                break;
            default:
                $ret .= "Unknown Error $error->code : ";
                break;
        }
        return $ret . trim($error->message) . " on line $error->line\n";

    }

    /**
     * function libxml_display_errors
     * Custom SimpleXML error handler.
     */
    public function libxml_display_errors()
    {
        $ret = "";
        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            $ret .= $this->libxml_display_error($error);
        }
        libxml_clear_errors();
        return $ret;
    }

    /**
     * function decodeJsonError
     * Convert json unmarshalling errors into something human-readable.
     */
    public function decodeJsonError($errnum)
    {
        switch ($errnum) {
            case JSON_ERROR_NONE:
                $message = 'No errors';
                break;
            case JSON_ERROR_DEPTH:
                $message = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $message = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $message = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $message = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $message = 'Unknown error';
                break;
        }

        return $message;
    }
}
