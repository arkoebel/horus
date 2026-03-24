<?php
  $order = file_get_contents('php://input');
  $order = json_decode($order,true);
  $conf = $order['conf'];
  $type = $order['type'];
  $number = $order['repeat'];
  $output = '<Document xmlns="urn:isis:generic">
    <ResponseType>' . $type . '</ResponseType>
    <QueryParams>
';
  foreach($order['attr'] as $key => $item){
      $output .= '        <Param name="' . $key . '">' . $item . '</Param>' . "\n";  
  }
  $output .= '    </QueryParams>
</Document>';

  file_put_contents('/tmp/test',$output);
  echo "OK";
