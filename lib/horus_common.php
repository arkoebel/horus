<?php

class HorusCommon
{
    private $log_location = '';
    private $business_id = '';
    private $colour = '';

    function __construct($business_id, $log_location, $colour = 'GREEN')
    {
        $this->log_location = $log_location;
        $this->business_id = $business_id;
    }

    /**
     * Function echoerror
     * Terminates program, writing an exception message to stdout
     */
    public function echoerror($exception)
    {
        ob_clean();
        die('Error ' . $exception->getMessage());
    }

    /**
     * Function myErrorHandler
     * Custom error handler to use our private logging facilities.
     */
    public function myErrorHandler($errno, $errstr, $errfile, $errline)
    {

        mlog("Error $errno at $errfile ( $errline ) : $errstr", 'ERROR', 'TXT');

        return false;
    }

    /**
     * Function mlog
     * Private logging facilities. Format message into JSON for easy ES integration.
     */
    public function mlog($message, $log_level, $format = 'TXT')
    {

        $alog = array();
        $alog['timestamp'] = HorusCommon::utc_time(5);
        $alog['program'] = $this->colour;
        $alog['log_level'] = $log_level;
        $alog['file'] = $_SERVER["PHP_SELF"];
        $alog['business_id'] = $this->business_id;
        $alog['pid'] = getmypid();
        if ($format === 'TXT') {
            $alog['message'] = $message;
        } else {
            $alog['message'] = HorusCommon::escapeJsonString($message);
        }
        if (is_null($this->log_location))
            error_log(json_encode($alog) . "\n");
        else
            error_log(json_encode($alog) . "\n", 3, $this->log_location);

        if (json_last_error() != 0)
            error_log(json_last_error_msg());
    }

    /**
     * function utc_time
     * Gets back the current date in ISO-8601 format with variable precision on seconds.
     */
    public static function utc_time($precision = 0)
    {
        $time = gettimeofday();

        if (is_int($precision) && $precision >= 0 && $precision <= 6) {
            $total = (string) $time['sec'] . '.' . str_pad((string) $time['usec'], 6, '0', STR_PAD_LEFT);
            $total_rounded = bcadd($total, '0.' . str_repeat('0', $precision) . '5', $precision);
            @list($integer, $fraction) = explode('.', $total_rounded);
            $format = $precision == 0
                ? "Y-m-d\TH:i:s\Z"
                : "Y-m-d\TH:i:s," . $fraction . "\Z";
            return gmdate($format, $integer);
        }

        return false;
    }

    /**
     * function escapeJsonString
     * Escapes a JSON String to integrate inside a JSON value
     */
    public static function escapeJsonString($value)
    {
        //$escapers =     array("\\",   "/",   "\"",   "\n",  "\r",  "\t",  "\x08", "\x0c");
        //$replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
        $escapers = array('"', '/');
        $replacements = array('\"', '\/');
        $result = str_replace($escapers, $replacements, $value);
        return $result;
    }

    /**
     * function libxml_display_error
     * Custom SimpleXML error handler.
     */
    function libxml_display_error($error)
    {
        $return = "";
        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $return .= "Warning $error->code : ";
                break;
            case LIBXML_ERR_ERROR:
                $return .= "Error $error->code : ";
                break;
            case LIBXML_ERR_FATAL:
                $return .= "Fatal Error $error->code : ";
                break;
        }
        $return .= trim($error->message);
        $return .= " on line $error->line\n";

        return $return;
    }

    /**
     * function libxml_display_errors
     * Custom SimpleXML error handler.
     */
    function libxml_display_errors()
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
    function decodeJsonError($errnum)
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
