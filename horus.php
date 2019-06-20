<?php

$mytime = microtime(true);

//if (extension_loaded('oci8'))
//    require_once "database_oci.php";
//else
//    require_once "database_pdo.php";

function echoerror($exception){
    ob_clean();
    die('Error ' . $exception->getMessage());
}

function mlog($message,$log_level,$format = 'TXT') {

 global $business_id;

 $alog = array(); 
 $alog['timestamp'] = utc_time(5);
 $alog['program'] = 'GREEN';
 $alog['log_level'] = $log_level;
 $alog['file'] = $_SERVER["PHP_SELF"];
 $alog['business_id'] = $business_id;
 if ($format === 'TXT') {
   $alog['message'] = $message;
 }else{
   $json = $json_decode($message,true);
   $alog = array_merge($alog,$json);
   $alog['orig_message'] = $message;
 }
 error_log(json_encode($alog,JSON_UNESCAPED_SLASHES)); 
 
 if (json_last_error()!=0)  
   error_log(json_last_error_msg()); 
}

function utc_time($precision = 0)
{
    $time = gettimeofday();

    if (is_int($precision) && $precision >= 0 && $precision <= 6) {
        $total = (string) $time['sec'] . '.' . str_pad((string) $time['usec'], 6, '0', STR_PAD_LEFT);
        $total_rounded = bcadd($total, '0.' . str_repeat('0', $precision) . '5', $precision);
        @list($integer, $fraction) = explode('.', $total_rounded);
        $format = $precision == 0
            ? "Y-m-d\TH:i:s\Z"
            : "Y-m-d\TH:i:s,".$fraction."\Z";
        return gmdate($format, $integer);
    }

    return false;
}

function getNewBusinessId() {

    return md5(time());
}

function libxml_display_error($error)
{
    $return = "";
    switch ($error->level) {
        case LIBXML_ERR_WARNING:
            $return .= "Warning $error->code : ";
            break;
        case LIBXML_ERR_ERROR:
            $return .= "Error $error->code : ";
            break;
        case LIBXML_ERR_FATAL:
            $return .= "Fatal Error $error->code : ";
            break;
    }
    $return .= trim($error->message);
    $return .= " on line $error->line\n";

    return $return;
}

function libxml_display_errors() {
    $ret = "";
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
        $ret .= libxml_display_error($error);
    }
    libxml_clear_errors();
    return $ret;
}


function findMatch($matches,$request,$field){
    if(array_key_exists($request,$matches)){
        if(array_key_exists($field, $matches[$request])){
            return $matches[$request][$field];
        }else{
            return '';
        }
    }else{
        return '';
    }
}

function locate($matches,$found,$value){
    $selected = -1;
    foreach($matches as $id=>$match){
//	print_r($match);
        if($match['query']===$found){
            if(array_key_exists('queryMatch',$match) && $match['queryMatch']!=''){
                //echo('/' . $match['queryMatch'] . '/' . "\n");
                if(preg_match('/' . $match['queryMatch'] . '/',$value)===1){
                    $selected = $id;
                    //echo('found ' . $id . "\n");
                }else{
                    //echo('not found' . "\n");
                }
            }else
                $selected = $id;
        }
    }
    //echo "Selected = $selected\n";
    return $selected;
}

function locateJson($matches,$input, $queryParams = null){
    $selected = -1;
    foreach($matches as $id=>$match){
        if(array_key_exists($match['query']['key'],$input)){
            if(array_key_exists('queryKey',$match['query'])){
                if( array_key_exists($match['query']['queryKey'],$queryParams) && $match['query']['queryValue'] === $queryParams[$match['query']['queryKey']]){
                    mlog($id . ': trying -- Matched query param','DEBUG');
                    if($input[$match['query']['key']]===$match['query']['value']){
                        if(array_key_exists('queryMatch',$match) && $match['queryMatch']!=''){
                            if(preg_match('/' . $match['queryMatch'] . '/',json_encode($input))===1){
                                mlog($id . ': matched -- querymatch, query param','DEBUG');
                                $selected = $id;
                            }  
                        }else{
                            mlog($id . ': matched -- no query match, query param','DEBUG');
                            $selected = $id;
                        }
                    }
                }else{
                    mlog($id . ': trying -- Query param wasn\'t a match','DEBUG');
                }
            }else{
                if($input[$match['query']['key']]===$match['query']['value']){
                    if(array_key_exists('queryMatch',$match) && $match['queryMatch']!=''){
                        if(preg_match('/' . $match['queryMatch'] . '/',json_encode($input))===1){
                            mlog($id . ': matched -- querymatch, no query param','DEBUG');
                            $selected = $id;
                        }
                    }else{
                        mlog($id . ': matched -- no query match','DEBUG');
                        $selected = $id;
                    }
                }
            }
        }        

    }

    return $selected;

}

function formMultiPart($file,$data,$mime_boundary,$eol,$content_type) { 
	$cc = '';
	$cc .= '--' . $mime_boundary . $eol;
	$cc .= "Content-Disposition: form-data; name=\"$file\"; filename=\"$file\"" . $eol;
	$cc .= 'Content-Type: ' . $content_type . $eol;
	$cc .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
	$cc .= chunk_split(base64_encode($data)) . $eol;

	return $cc;
}

function decodeJsonError($errnum){
    switch ($errnum) {
        case JSON_ERROR_NONE:
            $message = 'No errors';
        break;
        case JSON_ERROR_DEPTH:
            $message = 'Maximum stack depth exceeded';
        break;
        case JSON_ERROR_STATE_MISMATCH:
            $message = 'Underflow or the modes mismatch';
        break;
        case JSON_ERROR_CTRL_CHAR:
            $message = 'Unexpected control character found';
        break;
        case JSON_ERROR_SYNTAX:
            $message = 'Syntax error, malformed JSON';
        break;
        case JSON_ERROR_UTF8:
            $message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
        break;
        default:
            $message = 'Unknown error';
        break;
    }

    return $message;
}

function extractPayload($content_type,$body,$errorTemplate,$errorFormat){
    if($content_type=="application/json"){
        $json = json_decode($body,true);
        if (json_last_error()!= JSON_ERROR_NONE) {
            returnGenericError($errorFormat,$errorTemplate,'JSON Malformed : ' . decodeJsonError(json_last_error()));
        }else{
            if($json['payload']!=null)
                return $json['payload'];
            else
            returnGenericError($content_type,$errorTemplate,'Empty JSON Payload');
        }
    }else
        return $body;
}

function extractSimpleJsonPayload($body){
    return json_decode($body,true);
}

function returnGenericError($format,$template,$errorMessage,$forward=''){

    mlog("Error being generated. Cause: $errorMessage",'INFO');
    ob_start();
    include $template;
    $errorOutput = ob_get_contents();
    ob_end_clean();

    returnWithContentType($errorOutput,$format,400,$forward);

}

function returnGenericJsonError($format,$template,$errorMessage,$forward=''){

    mlog("Error JSON being generated. Cause: $errorMessage",'INFO');
    ob_start();
    include $template;
    $errorOutput = ob_get_contents();
    ob_end_clean();

    mlog($errorOutput,'DEBUG','JSON');

    returnWithContentType($errorOutput,$format,400,$forward,true,true);

}
function returnArrayWithContentType($data,$content_type,$status,$forward='',$exitafter=true,$mytime,$no_conversion=false){
    global $business_id;
    mlog('RAWC: no_conversion = ' . $no_conversion,'DEBUG');
    switch($status){
        case 200:
            header("HTTP/1.1 200 OK",TRUE,200);
            break;
        case 400:
            header("HTTP/1.1 400 MALFORMED URL",TRUE,400);
            break;
        case 404:
            header("HTTP/1.1 404 NOT FOUND",TRUE,404);
            break;
        case 500:
            header("HTTP/1.1 500 SERVER ERROR",TRUE,500);
            break;
    }

   if($forward === null)
        $forward = '';

    if($forward !== ''){
        mlog('Generate Curl calls at '. (microtime(true) - $mytime)*1000,'INFO');
        $mh = curl_multi_init();
        $ch = array();
        foreach($data as $i => $content){
            $ch[$i] = curl_init($forward);
            curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch[$i], CURLOPT_POST, TRUE);
            curl_setopt($ch[$i], CURLOPT_HTTPHEADER, array('Content-type: ' . $content_type, 'Accept: application/json', 'Expect: ','X_BUSINESS_ID: ' . $business_id));
            curl_setopt($ch[$i], CURLOPT_POSTFIELDS, convertOutData($content,$content_type,$no_conversion));
            curl_setopt($ch[$i], CURLOPT_SSL_VERIFYPEER, False);
            curl_setopt($ch[$i], CURLOPT_VERBOSE, True);
            curl_setopt($ch[$i], CURLOPT_HEADER, True);
            //curl_setopt($ch[$i], CURLINFO_HEADER_OUT, True);
            curl_multi_add_handle($mh, $ch[$i]);
        }
        mlog('Curl calls generated at ' . (microtime(true) - $mytime)*1000,'INFO');
        $running = NULL;
        do {
            curl_multi_exec($mh,$running);
            curl_multi_select($mh,10);
        } while($running > 0);
       
        $json = false;
        mlog('Got all curl responses at ' . (microtime(true) - $mytime)*1000,'INFO');

        $response = array();
        foreach($data as $i => $content){
            $curlError = curl_error($ch[$i]);
            //var_dump(curl_getinfo($ch[$i]));
            $content_length = curl_getinfo($ch[$i],CURLINFO_HEADER_SIZE);
            if($curlError == ""){
                $bbody = curl_multi_getcontent($ch[$i]);
                $bheader = explode("\n",substr($bbody, 0, $content_length));
                $response_headers = array();
                foreach($bheader as $header){
                    $exp = preg_split("/\:\s/",$header);
                    if(count($exp)>1)
                        $response_headers[$exp[0]]=$exp[1];
                }
                if(stripos($response_headers["Content-Type"],'json')>0){
                    $json = true;
                    $response[$i] = json_decode(substr($bbody, $content_length),true);
                }else{ 
                    $response[$i] = substr($bbody, $content_length);
                }
            }else{
                $response[$i] = "Error loop $i $curlError\n";
            }
            curl_multi_remove_handle($mh,$ch[$i]);
            curl_close($ch[$i]);
        }
        curl_multi_close($mh);
        mlog('Got all responses at ' . (microtime(true) - $mytime)*1000,'INFO');

        if($json){
            echo json_encode($response);
        }else{
            echo implode("\n",$response);
        }
    }

    if ($exitafter===true)
        exit;
}

function returnWithContentType($data,$content_type,$status,$forward='',$exitafter=true, $no_conversion=false){
    global $business_id;
    mlog("RWCT no_conversion:" . ($no_conversion ? 'TRUE':'FALSE'),'DEBUG');
    switch($status){
        case 200:
            header("HTTP/1.1 200 OK",TRUE,200);
            break;
        case 400:
            header("HTTP/1.1 400 MALFORMED URL",TRUE,400);
            break;
        case 404:
            header("HTTP/1.1 404 NOT FOUND",TRUE,404);
            break;
        case 500:
            header("HTTP/1.1 500 SERVER ERROR",TRUE,500);
            break;
    }
    if($forward === null)
        $forward = '';

    if($forward !== ''){
        $handle = curl_init($forward);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($handle, CURLOPT_POST, TRUE);
        curl_setopt($handle, CURLOPT_HTTPHEADER, array('Content-type: ' . $content_type, 'Accept: application/json', 'Expect: ', 'X_BUSINESS_ID: ' . $business_id));
        curl_setopt($handle, CURLOPT_POSTFIELDS, convertOutData($data,$content_type, $no_conversion));
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, False);
        $response = curl_exec($handle);
        header("Content-type: $content_type");
        //echo "Horus sending to " . $forward . "\n";
        //echo "Horus Content Type : " . $content_type . "\n";
        //echo "Response received : " . $response . "\n";
        //die(print_r(curl_getinfo($handle),true));
        echo $response . "\n";
    }else{
        header("Content-type: $content_type");

        echo convertOutData($data,$content_type,$no_conversion);
    }
    if ($exitafter===true)
        exit;
}

function convertOutData($data,$content_type,$no_conversion=false){
    mlog("Convert: no_conversion=" . ($no_conversion ? 'TRUE':'FALSE'),'DEBUG');
    if(!$no_conversion){
        mlog("Conversion",'DEBUG');
        if($content_type == 'application/json'){
            $dataJSON = array('payload' => $data);
            return json_encode($dataJSON);
        }else{
            return $data;
        }
    }else{
        mlog("No Conversion",'DEBUG');
        return $data;
    }
}

function setReturnType($accept,$default){

    if ($accept == null)
        return $default;
    else {
        $types = explode(',',$accept);
        foreach ($types as $type){
            if(stripos($type,'application/xml')!==FALSE)
                return 'application/xml';
            else if(stripos($type,'application/json')!==FALSE)
                return 'application/json';
        }
        returnWithContentType('Supported output types are only application/xml and application/json','text/plain',400);
    }
}        
    
function extractHeader($header){
    if (function_exists('apache_request_headers')){
        $request_headers = apache_request_headers();
    }else{
        $request_headers = $_SERVER;
    }

    if (array_key_exists(strtoupper($header),$request_headers)){
        return $request_headers[strtoupper($header)];
    }else{
         if (array_key_exists(strtolower($header),$request_headers)){
            return $request_headers[strtolower($header)];
        }else{
            return '';
        }
    }

}
//var_dump($_GET);

$mmatches = json_decode(file_get_contents('conf/horusParams.json'),true);
$genericError = 'templates/' . $mmatches["errorTemplate"];
$errorFormat = $mmatches['errorFormat'];
$preferredType = setReturnType($_SERVER['HTTP_ACCEPT'],$errorFormat);
mlog("Preferred mime type : " . $preferredType,'DEBUG');
$simpleJsonMatches = $mmatches['simplejson'];
$matches = $mmatches["pacs"];
//print_r($matches);
$reqbody = file_get_contents('php://input');
$content_type = $_SERVER['CONTENT_TYPE'];
mlog("Request : " . print_r($_SERVER,true) . "\n",'DEBUG');
mlog("Received Data to post:\n" . $reqbody . "\n",'INFO');

$proxy_mode = extractHeader('X_DESTINATION_URL');

$business_id = extractHeader('X_BUSINESS_ID');

if ($business_id === '')
    $business_id = getNewBusinessId();


if(array_key_exists('type',$_GET))
    $request_type = $_GET["type"];
else 
    $request_type = '';

if ("inject" === $request_type){
    $reqparams = json_decode($reqbody,true);
    $template = 'templates/' . $reqparams['template'];
    $vars=array();
    foreach($reqparams['attr'] as $key => $value)
        $vars[$key] = $value;
    $content = array();
    mlog("Received request at " . (microtime(true) - $mytime)*1000,'INFO');
    for($i=0;$i<$reqparams['repeat'];$i++){
        $vars['loop_index'] = $i;
        ob_start();
        include $template;
        $output = ob_get_contents();
        ob_end_clean();
        if("application/xml" === $reqparams['sourcetype']){
            $outputxml = new DOMDocument();
            $outputxml->loadXML(preg_replace('/\s*(<[^>]*>)\s*/','$1',$output));
            $outputxml->formatOutput=false;
            $outputxml->preserveWhiteSpace = false;
            $content[] = $outputxml->saveXML();
        } else if ("application/json" === $reqparams['sourcetype']){
            $outputjson = json_decode($output);
            $content[] = json_encode($outputjson);
        } else {
            $content[] = $output;
        }
    }
    $convert = false;
    if(("application/xml" === $reqparams['sourcetype'])&&("application/json" === $reqparams['destinationcontent'])){
        mlog("=== Conversion XML -> JSON ===",'DEBUG');
        $convert = true;
    }
    mlog("Generated all data at " . (microtime(true) - $mytime)*1000,'INFO');
    returnArrayWithContentType($content,$reqparams['destinationcontent'],200,$proxy_mode,false,$mytime, !$convert);
    
}else if (("simplejson" === $request_type)&&("application/json" === $content_type)){
    $input = extractSimpleJsonPayload($reqbody);
    if($input === null){
        $error_message = "JSON Error " . decodeJsonError(json_last_error());
        returnGenericJsonError($preferredType,'templates/generic_error.json',$error_message,$proxy_mode);
    }
    //error_log("XJSON=" . print_r($simpleJsonMatches,true));
    $selected = locateJson($simpleJsonMatches,$input,$_GET);
    if ($selected == -1){
        $error_message = "No match found";
        returnGenericJsonError($preferredType,'templates/generic_error.json',$errorMessage,$proxy_mode);
    }else{
        mlog('Selected : ' . $selected,'INFO');
    }
    $vars = array();
    foreach(findMatch($simpleJsonMatches,$selected,"parameters") as $param=>$path){
        $vars[$param] = $input[$path];
    }
    $vars = array_merge($vars, $_GET);

    $errorTemplate = findMatch($simpleJsonMatches,$selected,"errorTemplate");
    $errorTemplate = ( ($errorTemplate==null) ? 'generic_error.json' : $errorTemplate);
    $errorTemplate = 'templates/' . $errorTemplate;
    if(findMatch($simpleJsonMatches,$selected,"displayError")==="On"){
        //echo trim(preg_replace('/\s+/', ' ', $errorOutput));
        returnGenericJsonError($preferredType,$errorTemplate,"Requested error",$proxy_mode);
    }
    $response = '';
    $multiple = false;
    if(!is_array(findMatch($simpleJsonMatches,$selected,"responseTemplate"))){
        $templates = array(findMatch($simpleJsonMatches,$selected,"responseTemplate"));
        $formats = array(findMatch($simpleJsonMatches,$selected,"responseFormat"));
    }else{
        $templates = findMatch($simpleJsonMatches,$selected,"responseTemplate");
        $formats = findMatch($simpleJsonMatches,$selected,"responseFormat");
        $multiple = true;
    }
    $eol = "\r\n";
    $mime_boundary=md5(time());
    $nrep = 0;
    foreach($templates as $template){
        $respxml = 'templates/' . $template;
        ob_start();
        include $respxml;
        $output = ob_get_contents();
        ob_end_clean();
        if($multiple)
            $response .= formMultiPart($template,convertOutData($output,$preferredType,true),$mime_boundary,$eoli,$preferredType);
        else
            $response = $output;
        $nrep++;
    }
    if($multiple){
        returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol,"multipart/form-data; boundary=$mime_boundary",200,$proxy_mode,true,true);
    }else{
        returnWithContentType($response,$preferredType,200,$proxy_mode,true,true);
    }

}else{
    $input = extractPayload($content_type,$reqbody,$genericError,$preferredType);
    libxml_use_internal_errors(true);
    $query = simplexml_load_string($input);
    if($query===FALSE){
        $errorMessage = "Input XML not properly formatted.\n";
        $errorMessage .= libxml_display_errors();
        returnGenericError($preferredType,$genericError,$errorMessage,$proxy_mode);
    }
        

    $namespaces = $query->getDocNamespaces();
    $query->registerXPathNamespace('u',$namespaces[""]);

    $mnamespaces = explode(':',$namespaces[""]);

    $namespace = array_pop($mnamespaces);
    //echo $namespace . "\n";
    $domelement = dom_import_simplexml($query);
    $domdoc = $domelement->ownerDocument;

    $valid = false;
    $selectedXsd = "";
    foreach (scandir('xsd') as $schema){
        if(!(strpos($schema,$namespace)===false)){
            libxml_use_internal_errors(true);
            if($domdoc->schemaValidate('xsd/' . $schema)){
		//echo "matched $schema\n";
		$valid = true;
	        $selectedXsd = $schema;
		//break;
	    }else{
            //echo "Not validated with : $schema\n";
            }
        }else{
	//echo "skipping $schema\n";
        }
    }
   mlog("schema=$selectedXsd\n",'DEBUG');
    if($valid){
        $selected = locate($matches,$selectedXsd,$input);
        if($selected == -1){
            $errorMessage = "Found match, but filtered out\n";
            $errorMessage .= "XSD = $selectedXsd";
            mlog($errorMessage . "\n",'INFO');
            returnGenericError($preferredType,$genericError,$errorMessage,$proxy_mode);
        }
        $vars = array();
        mlog("Match comment : " . findMatch($matches,$selected,"comment") . "\n",'INFO');
        foreach(findMatch($matches,$selected,"parameters") as $param=>$path){
            $query->registerXPathNamespace('u',$namespaces[""]);
            $rr = ($query->xpath($path)); 
            if(! ($rr===FALSE)){
                $rr0 = $rr[0];
                if ( $rr0->count()!=0) {
                    $vars[$param] = $rr0->asXml();
                }else{
                    $vars[$param] = (string) $rr0;
                }
            }
        }
        $vars = array_merge($vars, $_GET);
        mlog("Variables: " . print_r($vars,true). "\n",'INFO');
        mlog("Selected template : " . findMatch($matches,$selected,"responseTemplate") . "\n",'INFO');

        $errorTemplate = findMatch($matches,$selected,"errorTemplate");
        $errorTemplate = ( ($errorTemplate==null) ? $genericError : $errorTemplate);
        $errorTemplate = 'templates/' . $errorTemplate;
        if(findMatch($matches,$selected,"displayError")==="On"){
            //echo trim(preg_replace('/\s+/', ' ', $errorOutput));
            returnGenericError($preferredType,$errorTemplate,"Requested error",$proxy_mode);
        }
        $response = '';
        $multiple = false;
        if(!is_array(findMatch($matches,$selected,"responseTemplate"))){
            $templates = array(findMatch($matches,$selected,"responseTemplate"));
            $formats = array(findMatch($matches,$selected,"responseFormat"));
            $forwardparams = array( findMatch($matches,$selected,"destParameters"));
        }else{
            $templates = findMatch($matches,$selected,"responseTemplate");
	    $formats = findMatch($matches,$selected,"responseFormat");
            $forwardparams = findMatch($matches,$selected,"destParameters");
            $multiple = true;
        }
        $eol = "\r\n";
        $mime_boundary=md5(time());
        $nrep = 0;
        foreach($templates as $template){    
            $respxml = 'templates/' . $template;
            ob_start();
            include $respxml;
            $output = ob_get_contents();
            ob_end_clean();
            $outputxml = new DOMDocument();
            $outputxml->loadXML(preg_replace('/\s*(<[^>]*>)\s*/','$1',$output));
            // $outputxml->loadXML($output);
            // die(print_r($output,true));
            if(!($outputxml->schemaValidate('xsd/' . $formats[$nrep]))){
                $errorMessage = "Could not validate output with " . $formats[$nrep] . "\n";
                $errorMessage .= libxml_display_errors();
                mlog($errorMessage . "\n",'ERROR');
                returnGenericError($preferredType,$errorTemplate,$errorMessage,$proxy_mode);
            }
            $outputxml->formatOutput=false;
            $outputxml->preserveWhiteSpace = false;
            if($multiple)
                $response .= formMultiPart($template,convertOutData($outputxml->saveXML(),$preferredType),$mime_boundary,$eoli,$preferredType);
            else
                $response = $outputxml->saveXML();
            $nrep++;
        }

        if ($forwardparams!==null && $forwardparams != "" && count($forwardparams[0])>0){
            mlog('params forward : ' . print_r($forwardparams,true),'INFO');
            $fwd_params = array();
            if(is_array($forwardparams[0])){
                foreach ($forwardparams[0] as $forwardparam){
                    $key = urlencode($forwardparam['key']);
                    $value = urlencode($forwardparam['value']);
                    $fwd_params[] = $key . '=' . $value;
                }
                mlog('query out : ' . print_r($fwd_params,true),'INFO');
                if(stripos($proxy_mode,'?')===FALSE)
                    $proxy_mode .= '?';
                else
                    $proxy_mode .= '&';
                $proxy_mode .= implode('&',$fwd_params);
            }
        }

        if($multiple){
            returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol,"multipart/form-data; boundary=$mime_boundary",200);
        }else{  
            returnWithContentType($response,$preferredType,200,$proxy_mode);
        }
    }else{
        $errorMessage = "Unable to find appropriate response.\n";
        $errorMessage .= libxml_display_errors();
        mlog($errorMessage . "\n",'ERROR');
        returnGenericError($preferredType,$genericError,$errorMessage,$proxy_mode);
    }
}
 
