<?php
    require_once dirname(__DIR__) . '/lib/horus_common.php';

    header("Content-type: application/json");
    echo HorusCommon::horusFileGetContents('conf/injectorParams.json');
