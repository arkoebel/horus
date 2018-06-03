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

function locateJson($matches,$input){
    $selected = -1;
    foreach($matches as $id=>$match){
        if(array_key_exists($match['query']['key'],$input)){
            if($input[$match['query']['key']]===$match['query']['value']){
                if(array_key_exists('queryMatch',$match) && $match['queryMatch']!=''){
                    if(preg_match('/' . $match['queryMatch'] . '/',json_encode($input))===1){
                        $selected = $id;
                    }
                }else{
                    $selected = $id;
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

    error_log("Error being generated. Cause: $errorMessage");
    ob_start();
    include $template;
    $errorOutput = ob_get_contents();
    ob_end_clean();

    returnWithContentType($errorOutput,$format,400,$forward);

}

function returnGenericJsonError($format,$template,$errorMessage,$forward=''){

    error_log("Error JSON being generated. Cause: $errorMessage");
    ob_start();
    include $template;
    $errorOutput = ob_get_contents();
    ob_end_clean();

error_log("JSON=" . $errorOutput);

    returnWithContentType($errorOutput,$format,400,$forward,true,true);

}
function returnArrayWithContentType($data,$content_type,$status,$forward='',$exitafter=true,$mytime,$no_conversion=false){
    error_log('RAWC: no_conversion = ' . $no_conversion);
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
        error_log('Generate Curl calls at '. (microtime(true) - $mytime)*1000);
        $mh = curl_multi_init();
        $ch = array();
        foreach($data as $i => $content){
            $ch[$i] = curl_init($forward);
            curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch[$i], CURLOPT_POST, TRUE);
            curl_setopt($ch[$i], CURLOPT_HTTPHEADER, array('Content-type: ' . $content_type, 'Accept: application/json', 'Expect: '));
            curl_setopt($ch[$i], CURLOPT_POSTFIELDS, convertOutData($content,$content_type,$no_conversion));
            curl_setopt($ch[$i], CURLOPT_SSL_VERIFYPEER, False);
            curl_setopt($ch[$i], CURLOPT_VERBOSE, True);
            curl_setopt($ch[$i], CURLOPT_HEADER, True);
            //curl_setopt($ch[$i], CURLINFO_HEADER_OUT, True);
            curl_multi_add_handle($mh, $ch[$i]);
        }
        error_log('Curl calls generated at ' . (microtime(true) - $mytime)*1000);
        $running = NULL;
        do {
            curl_multi_exec($mh,$running);
            curl_multi_select($mh,10);
        } while($running > 0);
       
        $json = false;
        error_log('Got all curl responses at ' . (microtime(true) - $mytime)*1000);

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
        error_log('Got all responses at ' . (microtime(true) - $mytime)*1000);

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
    error_log("RWCT no_conversion:" . ($no_conversion ? 'TRUE':'FALSE'));
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
        curl_setopt($handle, CURLOPT_HTTPHEADER, array('Content-type: ' . $content_type, 'Accept: application/json', 'Expect: '));
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
    error_log("Convert: no_conversion=" . ($no_conversion ? 'TRUE':'FALSE'));
    if(!$no_conversion){
        error_log("Conversion");
        if($content_type == 'application/json'){
            $dataJSON = array('payload' => $data);
            return json_encode($dataJSON);
        }else{
            return $data;
        }
    }else{
        error_log("No Conversion");
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
    

//var_dump($_GET);

$mmatches = json_decode(file_get_contents('horusParams.json'),true);
$genericError = 'templates/' . $mmatches["errorTemplate"];
$errorFormat = $mmatches['errorFormat'];
$preferredType = setReturnType($_SERVER['HTTP_ACCEPT'],$errorFormat);
error_log("Preferred mime type : " . $preferredType);
$simpleJsonMatches = $mmatches['simplejson'];
$matches = $mmatches["pacs"];
//print_r($matches);
$reqbody = file_get_contents('php://input');
$content_type = $_SERVER['CONTENT_TYPE'];
error_log("Request : " . print_r($_SERVER,true));
error_log("Received Data to post:\n" . $reqbody);
$request_headers = apache_request_headers();
if (array_key_exists('X_DESTINATION_URL',$request_headers)){
    $proxy_mode = $request_headers['X_DESTINATION_URL'];
}else{
     if (array_key_exists('x_destination_url',$request_headers)){
        $proxy_mode =  $request_headers['x_destination_url'];
    }else{
        $proxy_mode = '';
    }
}

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
    error_log("Received request at " . (microtime(true) - $mytime)*1000);
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
        error_log("=== Conversion XML -> JSON ===");
        $convert = true;
    }
    error_log("Generated all data at " . (microtime(true) - $mytime)*1000);
    returnArrayWithContentType($content,$reqparams['destinationcontent'],200,$proxy_mode,false,$mytime, !$convert);
    
}else if (("simplejson" === $request_type)&&("application/json" === $content_type)){
    $input = extractSimpleJsonPayload($reqbody);
    if($input === null){
        $error_message = "JSON Error " . decodeJsonError(json_last_error());
        returnGenericJsonError($preferredType,'templates/generic_error.json',$error_message,$proxy_mode);
    }
    //error_log("XJSON=" . print_r($simpleJsonMatches,true));
    $selected = locateJson($simpleJsonMatches,$input);
    if ($selected == -1){
        $error_message = "No match found";
        returnGenericJsonError($preferredType,'templates/generic_error.json',$errorMessage,$proxy_mode);
    }else{
        error_log('Selected : ' . $selected);
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
//echo "schema=$selectedXsd\n";
    if($valid){
        $selected = locate($matches,$selectedXsd,$input);
        if($selected == -1){
            $errorMessage = "Found match, but filtered out\n";
            $errorMessage .= "XSD = $selectedXsd";
            returnGenericError($preferredType,$genericError,$errorMessage,$proxy_mode);
        }
        $vars = array();
        foreach(findMatch($matches,$selected,"parameters") as $param=>$path){
            $query->registerXPathNamespace('u',$namespaces[""]);
            $rr = ($query->xpath($path)); 
            $vars[$param] = (string) $rr[0];
        }
        $vars = array_merge($vars, $_GET);
        //var_dump($vars);
        //var_dump(findMatch($matches,$selected,"responseTemplate"));
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
            //die(print_r($output,true));
            if(!($outputxml->schemaValidate('xsd/' . $formats[$nrep]))){
                $errorMessage = "Could not validate output with " . $formats[$nrep] . "\n";
                $errorMessage .= libxml_display_errors();
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
            error_log('params forward : ' . print_r($forwardparams,true));
            $fwd_params = array();
            foreach ($forwardparams[0] as $forwardparam){
                $key = urlencode($forwardparam['key']);
                $value = urlencode($forwardparam['value']);
                $fwd_params[] = $key . '=' . $value;
            }
            error_log('query out : ' . print_r($fwd_params,true));
            if(stripos($proxy_mode,'?')===FALSE)
                $proxy_mode .= '?';
            else
                $proxy_mode .= '&';
            $proxy_mode .= implode('&',$fwd_params);
        }

        if($multiple){
            returnWithContentType($response . "--" . $mime_boundary . "--" . $eol . $eol,"multipart/form-data; boundary=$mime_boundary",200);
        }else{  
            returnWithContentType($response,$preferredType,200,$proxy_mode);
        }
    }else{
        $errorMessage = "Unable to find appropriate response.\n";
        $errorMessage .= libxml_display_errors();
        returnGenericError($preferredType,$genericError,$errorMessage,$proxy_mode);
    }
}
 
