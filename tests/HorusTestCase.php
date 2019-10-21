<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
//use HorusCommon;
require_once('lib/horus_http.php');
require_once('lib/horus_common.php');

class HorusTestCase extends TestCase
{

    public static $mockheaders;
    public static $curls;
    protected $http;

    /**
     * Use runkit to create a new header function.
     */
    public static function setUpBeforeClass()
    {
        if (!extension_loaded('runkit7')) {
            error_log("No Extension");
            return;
        }

        // First backup the real header function so we can restore it.
        runkit_function_rename('header', 'header_old');

        // Now, create a new header function that makes things testable.
        runkit_function_add(
            'header',
            '$string,$replace=true,$http_response_code=null',
            'HorusTestCase::$mockheaders[] = array($string,$replace,$http_response_code);'
        );

        runkit_function_rename('curl_multi_init', 'curl_multi_init_old');
        runkit_function_rename('curl_init', 'curl_init_old');
        runkit_function_rename('curl_setopt', 'curl_setopt_old');
        runkit_function_rename('curl_multi_add_handle', 'curl_multi_add_handle_old');
        runkit_function_rename('curl_multi_exec', 'curl_multi_exec_old');
        runkit_function_rename('curl_multi_select', 'curl_multi_select_old');
        runkit_function_rename('curl_error', 'curl_error_old');
        runkit_function_rename('curl_getinfo', 'curl_getinfo_old');
        runkit_function_rename('curl_multi_getcontent', 'curl_multi_getcontent_old');
        runkit_function_rename('curl_multi_remove_handle', 'curl_multi_remove_handle_old');
        runkit_function_rename('curl_close', 'curl_close_old');
        runkit_function_rename('curl_multi_close', 'curl_multi_close_old');
        runkit_function_add(
            'curl_multi_init',
            '',
            'return HorusTestCase::$curls;'
        );
        runkit_function_add(
            'curl_init',
            '$url = null',
            'HorusTestCase::$curls[count(HorusTestCase::$curls)-1][\'url\'] = $url;return array_keys(HorusTestCase::$curls)[count(HorusTestCase::$curls)-1];'
        );

        runkit_function_add(
            'curl_setopt',
            '$ch , $option , $value',
            'HorusTestCase::$curls[$ch][\'options\'][$option] = $value; return true;'
        );
        
        runkit_function_add(
            'curl_multi_add_handle',
            '$mh , $ch',
            'return 0;'
        );

        runkit_function_add(
            'curl_multi_exec',
            '$mh , &$still_running',
            'return 0;'
        );

        runkit_function_add(
            'curl_multi_select',
            '$mh, $timeout = 1.0 ',
            'sleep(1); return 0;'
        );

        runkit_function_add(
            'curl_error',
            '$ch',
            'if (array_key_exists(\'errorMessage\',HorusTestCase::$curls[$ch])){ return HorusTestCase::$curls[$ch][\'errorMessage\'];} else return \'\';'
        );

        runkit_function_add(
            'curl_getinfo',
            '$ch, $opt',
            'if (isset($opt))
                return HorusTestCase::$curls[$ch][\'returnHeaders\'][$opt];
            else
                return HorusTestCase::$curls[$ch][\'returnHeaders\'];'
        );
        
        runkit_function_add(
            'curl_multi_getcontent',
            '$ch',
            'return HorusTestCase::$curls[$ch][\'data\'];'
        );
        
        runkit_function_add(
            'curl_multi_remove_handle',
            '$mh,$ch',
            'return 0;' 
        );
        
        runkit_function_add(
            'curl_close',
            '$ch',
            'return 0;'
        );

        runkit_function_add(
            'curl_multi_close',
            '$mh',
            'return;'
        );
        
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
        runkit_function_remove('header');
        runkit_function_remove('curl_multi_init');
        runkit_function_remove('curl_init');
        runkit_function_remove('curl_setopt');
        runkit_function_remove('curl_multi_add_handle');
        runkit_function_remove('curl_multi_exec');
        runkit_function_remove('curl_multi_select');
        runkit_function_remove('curl_error');
        runkit_function_remove('curl_getinfo');
        runkit_function_remove('curl_multi_getcontent');
        runkit_function_remove('curl_multi_remove_handle');
        runkit_function_remove('curl_close');
        runkit_function_remove('curl_multi_close');


        // Move our backup to restore header to its original glory.
        runkit_function_rename('header_old', 'header');
        runkit_function_rename('curl_multi_init_old', 'curl_multi_init');
        runkit_function_rename('curl_init_old', 'curl_init');
        runkit_function_rename('curl_setopt_old', 'curl_setopt');
        runkit_function_rename('curl_multi_add_handle_old', 'curl_multi_add_handle');
        runkit_function_rename('curl_multi_exec_old', 'curl_multi_exec');
        runkit_function_rename('curl_multi_select_old', 'curl_multi_select');
        runkit_function_rename('curl_error_old', 'curl_error');
        runkit_function_rename('curl_getinfo_old', 'curl_getinfo');
        runkit_function_rename('curl_multi_getcontent_old', 'curl_multi_getcontent');
        runkit_function_rename('curl_multi_remove_handle_old', 'curl_multi_remove_handle');
        runkit_function_rename('curl_close_old', 'curl_close');
        runkit_function_rename('curl_multi_close_old', 'curl_multi_close');
    }

    /**
     * Set up our subject under test and global header state.
     */
    protected function setUp()
    {
        $this->http = new HorusHttp('testHorusHttp', null, 'GREEN');
        self::$mockheaders = array();
        self::$curls = array();
    }
}