<?php

interface Horus_CurlInterface
{
    public function curl_close($ch);
    public function curl_error($ch);
    public function curl_exec($ch);
    public function curl_getinfo($ch, $opt = 0);
    public function curl_init($url = null);
    public function curl_multi_add_handle($mh, $ch);
    public function curl_multi_close($mh);
    public function curl_multi_exec($mh, &$stillRunning);
    public function curl_multi_getcontent($ch);
    public function curl_multi_init();
    public function curl_multi_remove_handle($mh, $ch);
    public function curl_multi_select($mh, $timeout = 1.0);
    public function curl_setopt($ch, $option, $value);
}
