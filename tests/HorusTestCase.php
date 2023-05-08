<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

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
    public static function setUpBeforeClass()
    {
        self::$tracerProvider = HorusCommon::getTracerProvider('Test', 'mytest');
        self::$tracer = HorusCommon::getTracer(self::$tracerProvider, 'Test', 'mytest');
        self::$rootSpan = HorusCommon::getStartSpan(self::$tracer, array(), 'Start Test');
    }

    /**
     * After we're done testing, restore the header function.
     */
    public static function tearDownAfterClass()
    {


    }

    /**
     * Set up our subject under test and global header state.
     */
    protected function setUp()
    {
        $this->http = new HorusHttp('testHorusHttp', 'php://stdout', 'GREEN', self::$tracer);
        self::$mockheaders = array();
        self::$curls = array();
        self::$curlCounter = 0;
    }
}
