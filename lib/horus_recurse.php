<?php

class HorusRecurse
{
    public $common = null;
    public $http = null;
    public $business = null;
    public $xml = null;
    public $business_id = '';
    
    function __construct($business_id, $log_location)
    {
        $this->common = new HorusCommon($business_id, $log_location, 'GREEN');
        $this->http = new HorusHttp($business_id, $log_location, 'GREEN');
        $this->business = new HorusBusiness($business_id, $log_location, 'GREEN');
        $this->xml = new HorusXml($business_id, $log_location, 'GREEN');
        $this->business_id = $business_id;
    }

    /*

    {"section": "section1",
     "content-type":"application/xml",
     "comment":"Main PACS structure"
     "schema":"cristal.xsd",
     "baseNamespace": "",
     "baseNamespacePrefix": "u",
     "rootElement": "body",
     "parts":[
         {"order":"1",
          "comment":"Header transformation",
          "path":"/u:body/h:AppHdr",
          "namespaces":[
              {"prefix":"h","namespace":"urn:iso:std:iso:20022:tech:xsd:head.001.001.01"}
          ],
          "transformUrl":"http://horus/horus.php",
          "targetPath":"/u:body/h:AppHdr",
         },{
             "order":"2",
             "comment":"PACS Document transformation",
             "namespaces":[
                {"prefix":"d","element":"Document"}
             ],
             "path":"/u:body/d:Document",
             "transformUrl":"http://horus/horus.php",
             "targetElement":"Document",
             "targetElementOrder":"2"
         }
     ]}

    */

    function doRecurse($reqBody,$content_type,$proxy_mode,$matches,$accept,$params){
        
        if(!array_key_exists('section',$params)){
            throw new HorusException('Section URL parameter is unknown');
        }

        if(!array_key_exists($params['section'],$matches)){
            throw new HorusException('Section ' . $params['section'] . ' is unknown');
        }

        $section = findSection($params['section'],$matches);

        if($content_type !== $section['content_type']){
            throw new HorusException('Section ' . $params['section'] . " was supposed to be of type " . $section['content_type'] . ' but found ' . $content_type . ' instead');
        }

        $result = null;
        if ('application/xml'===$content_type){
            $result = doRecurseXml($reqBody,$section);
        }elseif('application/json'===$content_type){
            $result = doRecurseJson($reqBody,$section);
        }else{
            throw new HorusException('Unsupported content-type ' . $content_type);
        }

        return $this->http->returnWithContentType($result, $accept, 200, $proxy_mode);


    }


    function doRecurseXml($reqBody,$matches){
        $elements = array();
        if(array_key_exists('baseNamespacePrefix',$matches) && array_key_exists('baseNamespace',$matches)){
            $reqBody->registerXPathNamespace($matches['baseNamespacePrefix'],$matches['baseNamespace']);
            $this->common->mlog('Registering namespace ' . $matches['baseNamespacePrefix'] . ', ' . $matches['baseNamespace'],'INFO');
        }
        foreach ($matches['parts'] as $part){
            $this->common->mlog('Dealing with part #' . $part['order'] . ' : ' . $part['comment'],'INFO');
            if (array_key_exists('namespaces',$part)){
                $this->xml->registerExtraNamespaces($reqBody,$part['namespaces']);
            }
            $inputXmlPart = null;
            if(array_key_exists('path',$part)){
                $this->common->mlog('Extracting document from XPath=' . $part['path'],'DEBUG');
                $inputXmlPart = $reqBody->xpath($part['path']);
                if (FALSE!==$inputXmlPart && is_array($inputXmlPart && (count($inputXmlPart)>0))){
                    $xpathResult = $inputXmlPart[0];
                    $elements[$part['order']] = simplexml_load_string($this->http->forwardSingleHttpQuery($part['transformUrl'],array(), $xpathResult->saveXML()));
                }else{
                    var_dump($inputXmlPart);
                    throw new HorusException('Could not extract location ' . $part['path'] . ' for part #' . $part['order']);
                    
                }
            }else{
                throw new HorusException('No XPath to search for in configuration');
            }

        }

        $rootXml = simplexml_load_string('<' . $matches['rootElement'] . '> </' . $matches['rootElement'] . '>');
        if(array_key_exists('baseNameSpacePrefix',$matches) && array_key_exists('baseNameSpace',$matches)){
            $rootXml->registerXPathNamespace($matches['baseNameSpacePrefix'],$matches['baseNameSpace']);
        }
        
        $domRoot = dom_import_simplexml($rootXml);
        
        foreach ($elements as $index=>$element){
            $part = $matches['parts'][$index];
            $this->common->mlog("Added element to XML response " . $index . ' at ' . $part['targetXpath'],'INFO');
            $domElement = dom_import_simplexml($element);
            $domRoot->appendChild($domElement);
        }
        
        return $domRoot->ownerDocument->saveXml();



    }

    function doRecurseJson($reqBody,$matches){
        $this->common->mlog("Called Recurse Json with parameters " . print_r($matches,true) . ' and document = ' . print_r($reqBody,true),'INFO');
    }
}