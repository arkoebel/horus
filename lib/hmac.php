<?php

require_once 'diff.php';
// {1:F01SWHQBEBBAXXX0013009909}{2:I103SWHQBEBBXXXXN}{4:
// :20:A4A4790000009908
// :23B:CRED
// :32A:130818EUR43811,50
// :33B:EUR43811,50
// :50F:DRLC/BR/Issue Authority/ABCD12345671/Raskolnikov Dostojevski2/Red Square 13/RU/12345 MOSCOW
// :52A:BRBSBRD1SPA
// :53B:/C
// :57A:BTBKTNTT
// :59:/11111111111
// XXXXXXXXXXXXXXXXXXXXXXXXXXX
// POLSKA
// :70:CONTRACT 05/01BESCHLAEGE
// :71A:OUR
// -}{5:{PDE:}}


$input = "{1:F01SWHQBEBBAXXX0013009909}{2:I103SWHQBEBBXXXXN}{4:\r\n:20:A4A4790000009908\r\n:23B:CRED\r\n:32A:130818EUR43811,50\r\n:33B:EUR43811,50\r\n:50F:DRLC/BR/Issue Authority/ABCD1234567\r\n1/Raskolnikov Dostojevski\r\n2/Red Square 1\r\n3/RU/12345 MOSCOW\r\n:52A:BRBSBRD1SPA\r\n:53B:/C\r\n:57A:BTBKTNTT\r\n:59:/11111111111\r\nXXXXXXXXXXXXXXXXXXXXXXXXXXX\r\nPOLSKA\r\n:70:CONTRACT 05/01\r\nBESCHLAEGE\r\n:71A:OUR\r\n-}{5:{PDE:}}";

$key = 'Abcd1234abcd1234Abcd1234abcd1234';

function sign($input,$key){
    $pos = strpos($input,"{S:\r\n{");
    $tosign = $input;
    if($pos>0){
        $tosign = substr($input,0,$pos);
    }

    $res = hash_hmac('sha256', $tosign, $key, true);

    return $tosign . "{S:\r\n{MDG:" . strtoupper(bin2hex($res)) . "}}";
}


$xx = sign($input,$key);

//echo $xx . "\n";
//echo sign($xx,$key) . "\n";

$opcodes = FineDiff::getDiffOpcodes($input, $xx);

echo FineDiff::renderDiffToHTMLFromOpcodes($xx, $opcodes);