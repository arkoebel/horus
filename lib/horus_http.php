<?php

class HorusHttp {

    function formMultiPart($file,$data,$mime_boundary,$eol,$content_type) {
        $cc = '';
        $cc .= '--' . $mime_boundary . $eol;
        $cc .= "Content-Disposition: form-data; name=\"$file\"; filename=\"$file\"" . $eol;
        $cc .= 'Content-Type: ' . $content_type . $eol;
        $cc .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $cc .= chunk_split(base64_encode($data)) . $eol;

        return $cc;
}

function returnArrayWithContentType($data,$content_type,$status,$forward='',$exitafter=true,$mytime,$no_conversion=false){
    global $business_id;
    if ($no_conversion === FALSE)
        mlog('Conversion forced','DEBUG');
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
            curl_setopt($ch[$i], CURLOPT_HTTPHEADER, array('Content-type: ' . $content_type, 'Accept: application/json', 'Expect: ','X-Business-Id: ' . $business_id));
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
                mlog("Curl $i returned error: $curlError ","INFO");
                mlog(var_dump(curl_getinfo($ch[$i]),true),'INFO');
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
    if ($no_conversion === 'FALSE')
        mlog('Conversion forced','DEBUG');
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
        curl_setopt($handle, CURLOPT_HTTPHEADER, array('Content-type: ' . $content_type, 'Accept: application/json', 'Expect: ', 'X-Business-Id: ' . $business_id));
        curl_setopt($handle, CURLOPT_POSTFIELDS, convertOutData($data,$content_type, $no_conversion));
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, False);
        $response = curl_exec($handle);
        header("Content-type: $content_type");
        mlog("Horus sending to " . $forward,'INFO');
        mlog("Horus Content Type : " . $content_type,'INFO') ;
        if (curl_error($handle)!=='') 
            mlog("Curl Error: " . curl_error($handle),'ERROR');
        mlog("Response received : " . $response,"DEBUG") ;
        
        echo $response . "\n";
    }else{
        header("Content-type: $content_type");

        echo convertOutData($data,$content_type,$no_conversion);
    }
    if ($exitafter===true)
        exit;
}

function convertOutData($data,$content_type,$no_conversion=false){
    mlog("Data to send",'INFO');
    if(!$no_conversion){
        mlog("Forced Conversion for $content_type",'DEBUG');
        if($content_type == 'application/json'){
            $dataJSON = array('payload' => $data);
            mlog(json_encode($dataJSON),'DEBUG','JSON');
            return json_encode($dataJSON);
        }else{
            mlog($data,'DEBUG','TXT');
            return $data;
        }
    }else{
        mlog($data,'DEBUG','TXT');
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

    $conv_header = 'HTTP_' . strtoupper( preg_replace('/-/','_',$header));

    if (array_key_exists($conv_header,$request_headers)){
        return $request_headers[$conv_header];
    }else{
         if (array_key_exists(strtoupper($header),$request_headers)){
            return $request_headers[strtolower($header)];
        }else{
            return '';
        }
    }

}
    
}