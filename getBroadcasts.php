<?php

$liste = glob('templates/broadcast_*.xml');
$newlst = array();
foreach($liste as $item){
    $newlst[]=array('name'=>preg_replace('/templates\//','',$item));
}
echo(json_encode($newlst));
