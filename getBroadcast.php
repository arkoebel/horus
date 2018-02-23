<?php 
    $file = 'templates/' . $_GET['name'];
    #if($file==null)
    #    $file='templates/broadcast_camt053.xml';
    $contents = file_get_contents($file);
    $xml = simplexml_load_string($contents);
    $resp = (string)$xml->ResponseType;
    $attr=array();
    foreach ($xml->QueryParams->Param as $param){
        $attr[(string)$param->attributes()] = (string)$param;
    }
   echo json_encode(array('resp'=>$resp,'attr'=>$attr));
