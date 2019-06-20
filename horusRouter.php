<?php

$params = json_decode(file_get_contents('conf/horusRouting.json'),true);
//echo print_r($params,true);
$source = $_GET['source'];
$data = file_get_contents('php://input');
$content_type = $_SERVER['CONTENT_TYPE'];

function findSource($source,$params){
    //echo "findSource $source \n";
//    echo var_dump($params['RoutingTable'],true);
    foreach($params["RoutingTable"] as $route){
        //echo "Source = " . $route['source'] . "\n";
        if($route['source']===$source){
            return $route;
        }
    }
    return false;
}
//echo $source . "\n";

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



   $route = findSource($source, $params);

//echo print_r($route,true);

if ($route===false){
    header("HTTP/1.1 400 MALFORMED URL",TRUE,400);
    header("Content-type: application/json");
    echo json_encode(array("error"=>"Route not found"));
    exit;
}

$parameters = array();

foreach($route['parameters'] as $parameter){
    $key = urlencode($parameter['key']);
    $value = urlencode($parameter['value']);
    $parameters[] = $key . "=" . $value;
}

$responses = array();

$ii=0;

foreach($route['destinations'] as $destination){
    $ii++;
    mlog("Destination : $ii " . $destination['comment'] . "\n","INFO");
    $dest_parameters = $parameters;

    foreach($destination['destParameters'] as $dest_param){
        $key = urlencode($dest_param['key']);
        $value = urlencode($dest_param['value']);
        $dest_parameters[] = $key . "=" . $value;
    }

    $proxy_parameters = $parameters;

    foreach($destination['proxyParameters'] as $proxy_param){
        $key = urlencode($proxy_param['key']);
        $value = urlencode($proxy_param['value']);
        $proxy_parameters[] = $key . "=" . $value;
    }

    $dest_query = implode('&',$dest_parameters);
    $proxy_query = implode('&',$proxy_parameters);

    if ($dest_query !== ""){
        if(stripos($destination['destination'],'?')===FALSE){
            $dest_query = '?' . $dest_query;
        }else{
            $dest_query = '&' . $dest_query;
        }
    }

    if ($proxy_query !== ""){
        if(stripos($destination['proxy'],'?')===FALSE){
            $proxy_query = '?' . $proxy_query;
        }else{
            $proxy_query = '&' . $proxy_query;
        }
    }

    mlog("Send http request to " . $destination['proxy'] . $proxy_query . "\n",'DEBUG');
    mlog("Final destination : " . $destination['destination'] . $dest_query . "\n",'DEBUG');
    //echo "Content-type : " . $content_type . "\n";

    $headers = array('Content-type: ' . $content_type, 'Accept: application/json', 'Expect: ', 'X_BUSINESS_ID: ' . $business_id);

    if ($destination['proxy']==""){
        $dest_url = $destination['destination'] . $dest_query;
    }else{
        $dest_url = $destination['proxy'] . $proxy_query;
        $headers[] = 'X_DESTINATION_URL: ' . $destination['destination']. $dest_query;
    }
    //echo "DST = " . $dest_url . "\n";
    //echo print_r($headers);
    $handle = curl_init($dest_url);
    curl_setopt($handle, CURLOPT_URL,$dest_url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($handle, CURLOPT_POST, TRUE);
    curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($handle);
    $responses[] = $response;

    echo $response;

    if($destination['delayafter']!==""){
        sleep($destination['delayafter']);
    }
}

//die(print_r($responses,true));
