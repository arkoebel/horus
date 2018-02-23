<?php

$pdo = oci_connect('crtarcha','cristal','//opaline:1521/CRTARCH');
if(!$pdo){
    $e = oci_error();
    die('Error : ' . $e['message']);
}

function databaseQuery($conn,$query,$params=array()){
    $response = array();
    $stid = oci_parse($conn,$query);
    foreach($params as $key=>$value)
        oci_bind_by_name($stid,':'.$key,$value);

    $r = oci_execute($stid);
    if(!$r)
        return null;
    while(($row = oci_fetch_array($stid,OCI_ASSOC)))
        $response[] = $row;
    return $response;
}

function databaseValueQuery($conn,$query,$params=array()){
    $stid = oci_parse($conn,$query);
    foreach($params as $key=>$value)
        oci_bind_by_name($stid,':'.$key,$value);

    $r = oci_execute($stid);
    if(!$r)
        return null;
    $row = oci_fetch_array($stid,OCI_NUM);
    return $row[0];
}

