<?php

/* Sample mapper class that reads one XML field to determine the full roadmap.
The class MUST implement HorusMapperInterface.
Do try to have different names for all mapper classes.
If two different files use the same class names and are used within the same request, bad things will happen!
*/
class DynamicMapper implements HorusMapperInterface {

    const BIC_TO_DESTINATIONS = array('AGRIFRPPXXX'=>'destination_agri', 'CPMEFRPPXXX'=>'destination_cpme');
    const INSTRID =  "//*[local-name()='CdtTrfTxInf']/*[local-name()='PmtId']/*[local-name()='InstrId']";
    const SENDER_BIC='/u:DataPDU/u:Body/h:AppHdr/h:Fr/h:FIId/h:FinInstnId/h:BICFI';
    const RECEIVER_BIC='/u:DataPDU/u:Body/h:AppHdr/h:To/h:FIId/h:FinInstnId/h:BICFI';
    const MSG_MATRIX = array(
            'M0'=>array('ack.php','1'),
            'M1'=>array('nack.php','1'),
            'T0'=>array('xmitrpt.php','1'),
            'T1'=>array('xmitrptko.php','1'),
            'D0'=>array('delivnotif.php','0'),
            'D1'=>array('delivnotifko.php','0'),
            'C0'=>array('','0','http://horus/horusRecurse.php?section=datapdu'),
            'C1'=>array('',0)
    );

    /* Mapping should return an array of destinations. Each destination is an associative array with the following keys:
       "destination": name of the destination for this step.
                    Must be one of the values defined in the destinations
                    defined in the global roadmap configuration file
       "comment": a comment describing that destination
       "transform" (optional) : name of a php file in the ./transforms folder used to transform a given input
       "transformUrl" (optional) : URL the input can be POSTed to, to transform the given input
   */
    public static function doMap(
        string $input,
        string $source,
        array $destinations,
        array $headers,
        array $queryparams
        ): array {

        $result = array();

        
        $xml = simplexml_load_string($input);
        //Let's assume for simplicity the actual versions are the right ones
        $xml->registerXPathNamespace('u','urn:swift:saa:xsd:saa.2.0');
        $xml->registerXPathNamespace('h', 'urn:iso:std:iso:20022:tech:xsd:head.001.001.01');

        $receiverbic = HorusXml::getXpathValue($xml, self::RECEIVER_BIC);
        $senderbic = HorusXml::getXpathValue($xml, self::SENDER_BIC);

        //Let's get the field we're using to select the roadmap steps.
        $tmpInstrId = $xml->xpath(self::INSTRID);
        $roadmapSelectorField = reset($tmpInstrId);

        $lastGroup = '';

        if($roadmapSelectorField !== false){
            // Let's extract the values which determine the actual roadmap
            // -M0T0D0C0-1645191
            $res = preg_match(
                '/-([MTDC][012]){0,1}([MTDC][012]){0,1}([MTDC][012]){0,1}([MTDC][012]){0,1}-/',
                $roadmapSelectorField,
                $roadmapSelector
            );
            if ($res !== false){
                // We're cycling through all the groups found
                while($rr = array_shift($roadmapSelector)){
                    if(array_key_exists($rr, self::MSG_MATRIX)){
                        // Select the case we're working on
                        $parm = self::MSG_MATRIX[$rr];;
                        // We generate a response using a custom transformer
                        if(
                                count($parm)==2
                                && ($parm[0]!=='')
                                && array_key_exists($senderbic, self::BIC_TO_DESTINATIONS))
                            {
                            $result[] = array(
                                'destination'=>self::BIC_TO_DESTINATIONS[$senderbic],
                                'comment' => $rr,
                                'delay' => $parm[1],
                                'transform' => $parm[0]);
                        }elseif(
                                (count($parm)==3) &&
                                array_key_exists($senderbic, self::BIC_TO_DESTINATIONS)
                            ){
                            // This case is for external transformations
                            $result[] = array(
                                'destination'=>self::BIC_TO_DESTINATIONS[$senderbic],
                                'comment'=> $rr,
                                'delay' => $parm[1],
                                'transformUrl' => $parm[2]);

                        }else{
                            // We're dropping this message, so nothing
                        }
                    }else{
                        //Hack : the only group not found in the MSG_MATRIX array
                        //is the one for the whole expression e.g. -M0T0C0D0-
                        $lastGroup = $rr;
                    }

                }

                // Now, let's see about the destination
                if(preg_match('/M1|T1|C1|D2/',$lastGroup)){
                    // Not supposed to send a message to the partner, so do nothing.
                }else{
                    $result[] = array(
                        'destination'=> self::BIC_TO_DESTINATIONS[$receiverbic],
                        'comment' => 'sending payload to the partner',
                        'delay'=> '0',
                        'transform' => 'swap.php'
                    );
                }

            }
        }
        return $result;

    }
}
