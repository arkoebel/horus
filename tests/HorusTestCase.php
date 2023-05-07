<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;

require_once "lib/horus_utils.php";
require_once('lib/horus_curlInterface.php');
require_once('lib/horus_curl.php');
require_once('lib/horus_http.php');
require_once('lib/horus_common.php');
require_once('vendor/autoload.php');


class HorusTestCase extends TestCase
{

    public static $mockheaders;
    public static $curls;
    public static $curlCounter;
    protected $http;
    public static $tracerProvider;
    public static $tracer;
    public static $rootSpan;

    /**
     * Use runkit to create a new header function.
     */
    public static function setUpBeforeClass(){
        self::$tracerProvider = HorusCommon::getTracerProvider('Test','mytest');
        self::$tracer = HorusCommon::getTracer(self::$tracerProvider,'Test','mytest');
        self::$rootSpan = HorusCommon::getStartSpan(self::$tracer,array(),'Start Test');

        if (!extension_loaded('runkit7')) {
            error_log("No Extension");
            return;
        }

        // First backup the real header function so we can restore it.
        //runkit_function_rename('header', 'header_old');

        // Now, create a new header function that makes things testable.
        //runkit_function_add(
        //    'header',
        //    '$string,$replace=true,$http_response_code=null',
        //    'HorusTestCase::$mockheaders[] = array($string,$replace,$http_response_code);'
        //);

        //runkit_function_add('apache_request_headers','','return HorusTestCase::$mockheaders;');
        
    }

    /**
     * After we're done testing, restore the header function.
     */
    public static function tearDownAfterClass()
    {
        if (!extension_loaded('runkit7')) {
            return;
        }
        // Get rid of our new header function.
        //runkit_function_remove('header');

        // Move our backup to restore header to its original glory.
        //runkit_function_rename('header_old', 'header');

    }

    /**
     * Set up our subject under test and global header state.
     */
    protected function setUp()
    {
        $this->http = new HorusHttp('testHorusHttp', 'php://stdout', 'GREEN',self::$tracer);
        self::$mockheaders = array();
        self::$curls = array();
        self::$curlCounter = 0;
    }
}