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
    public static $rootSpan;
    public static HorusTracingInterface $tracing;

    public static function setUpBeforeClass(): void
    {

        self::$tracing = new HorusTracingMock('test', 'mytest', 'operation', array());
        self::$rootSpan = self::$tracing->getCurrentSpan();
    }

    /**
     * After we're done testing, restore the header function.
     */
    public static function tearDownAfterClass(): void
    {


    }

    /**
     * Set up our subject under test and global header state.
     */
    protected function setUp(): void
    {
        $this->http = new HorusHttp('testHorusHttp', 'php://stdout', 'GREEN', self::$tracing);
        self::$mockheaders = array();
        self::$curls = array();
        self::$curlCounter = 0;
    }
}
