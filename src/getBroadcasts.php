<?php

require_once dirname(__DIR__) . '/lib/horus_common.php';

$liste = glob(HorusCommon::horusPath('templates/broadcast_*.xml'));
$newlst = array();
foreach ($liste as $item) {
    $newlst[]=array('name'=>basename($item));
}
echo json_encode($newlst);
