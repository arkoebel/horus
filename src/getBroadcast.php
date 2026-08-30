<?php
    require_once dirname(__DIR__) . '/lib/horus_common.php';

    $contents = HorusCommon::horusFileGetContents('templates/' . $_GET['name']);
    $xml = simplexml_load_string($contents);
    $resp = (string)$xml->ResponseType;
    $attr=array();
    foreach ($xml->QueryParams->Param as $param) {
        $attr[(string)$param->attributes()] = (string)$param;
    }
   echo json_encode(array('resp'=>$resp,'attr'=>$attr));
