<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<style type="text/css">
body {margin:0;border:0;padding:0;font:11pt sans-serif}
body > h1 {margin:0 0 0.5em 0;font:2em sans-serif;background-color:#def}
body > div {padding:2px}
p {margin-top:0}
ins {color:green;background:#dfd;text-decoration:none}
del {color:red;background:#fdd;text-decoration:none}
#params {margin:1em 0;font: 14px sans-serif}
.panecontainer > p {margin:0;border:1px solid #bcd;border-bottom:none;padding:1px 3px;background:#def;font:14px sans-serif}
.panecontainer > p + div {margin:0;padding:2px 0 2px 2px;border:1px solid #bcd;border-top:none}
.pane {margin:0;padding:0;border:0;width:100%;min-height:20em;overflow:auto;font:12px monospace}
#htmldiff {color:gray}
#htmldiff.onlyDeletions ins {display:none}
#htmldiff.onlyInsertions del {display:none}
</style>
<title>Check HMAC FIN Signature</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
<h1>Check HMAC FIN Signature</h1>
<div>
<?php
function stripslashes_deep(&$value) {
	$value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
	return $value;
	}
if ( (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) || (ini_get('magic_quotes_sybase') && strtolower(ini_get('magic_quotes_sybase'))!="off") ) {
	stripslashes_deep($_GET);
	stripslashes_deep($_POST);
	}


function sign($input,$key){
    $pos = strpos($input,"{S:\r\n{");
    $tosign = $input;
    if($pos>0){
        $tosign = substr($input,0,$pos);
    }

    $res = hash_hmac('sha256', $tosign, $key, true);

    return $tosign . "{S:\r\n{MDG:" . strtoupper(bin2hex($res)) . "}}";
}

include 'finediff.php';

$granularity = 2;
$from_text = '';
$to_text = '';
$secret = '';
$diff_opcodes = '';
$diff_opcodes_len = 0;
$data_key = '';

$start_time = gettimeofday(true);

	if ( !empty($_POST['from']) || !empty($_POST['to'])) {
		if ( !empty($_POST['from']) ) {
			$from_text = $_POST['from'];
			}
                if ( !empty($_POST['secret']) ) {
                        $secret = $_POST['secret'];
                        }
		}
        if(''!==$secret){
    	// limit input
    	    $from_text = substr($from_text, 0, 1024*100); 
            $to_text = sign($from_text,$secret);
	// ensure input is suitable for diff
	    $from_text = mb_convert_encoding($from_text, 'HTML-ENTITIES', 'UTF-8');

	    $granularityStacks = array(
		FineDiff::$paragraphGranularity,
		FineDiff::$sentenceGranularity,
		FineDiff::$wordGranularity,
		FineDiff::$characterGranularity
		);
	    $diff_opcodes = FineDiff::getDiffOpcodes($from_text, $to_text, $granularityStacks[$granularity]);
	    $diff_opcodes_len = strlen($diff_opcodes);
	    $exec_time = gettimeofday(true) - $start_time;
            $rendered_diff = FineDiff::renderDiffToHTMLFromOpcodes($from_text, $diff_opcodes);
            $from_len = strlen($from_text);
            $to_len = strlen($to_text);
        }
?>
<div class="panecontainer" style="width:99%"><p>Diff <span style="color:gray">(diff: <?php printf('%.3f', $exec_time); ?> seconds, diff len: <?php echo $diff_opcodes_len; ?> chars)</span>&emsp;/&emsp;Show <input type="radio" name="htmldiffshow" onclick="setHTMLDiffVisibility('deletions');">Deletions only&ensp;<input type="radio" name="htmldiffshow" checked="checked" onclick="setHTMLDiffVisibility();">All&ensp;<input type="radio" name="htmldiffshow" onclick="setHTMLDiffVisibility('insertions');">Insertions only</p><div><div id="htmldiff" class="pane" style="white-space:pre-wrap"><?php
echo $rendered_diff; ?></div></div>
</div>
<form action="hmacdiff.php" method="post">
<p style="margin:1em 0 0.5em 0">Enter text to diff below:</p>
<div class="panecontainer" style="display:inline-block;width:49.5%"><p>From</p><div><textarea name="from" class="pane"><?php echo htmlentities($from_text, ENT_QUOTES, 'UTF-8'); ?></textarea></div></div>
<div class="panecontainer" style="display:inline-block;width:49.5%"><p>Secret</p><div><textarea name="secret" class="pane"><?php echo htmlentities($secret, ENT_QUOTES, 'UTF-8'); ?></textarea></div></div>
<p id="params">
<input type="submit" value="View diff">&emsp;<a href="hmacdiff.php"><button>Clear all</button></a></p>
</form>
<script type="text/javascript">
<!--
function setHTMLDiffVisibility(what) {
	var htmldiffEl = document.getElementById('htmldiff'),
		className = htmldiffEl.className;
	className = className.replace(/\bonly(Insertions|Deletions)\b/g, '').replace(/\s{2,}/g, ' ').replace(/\s+$/, '').replace(/^\s+/, '');
	if ( what === 'deletions' ) {
		htmldiffEl.className = className + ' onlyDeletions';
		}
	else if ( what === 'insertions' ) {
		htmldiffEl.className = className + ' onlyInsertions';
		}
	else {
		htmldiffEl.className = className;
		}
	}
// -->
</script>
</body>
</html>
