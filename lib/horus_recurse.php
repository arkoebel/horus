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
        $this->common = new HorusCommon($business_id, $log_location, 'INDIGO');
        $this->http = new HorusHttp($business_id, $log_location, 'INDIGO');
        $this->business = new HorusBusiness($business_id, $log_location, 'INDIGO');
        $this->xml = new HorusXml($business_id, $log_location, 'INDIGO');
        $this->business_id = $business_id;
    }

    function getPart($order,$matches){
        foreach($matches['parts'] as $part){
            if ($order == $part['order']){
                return $part;
            }
        }
        return array();
    }

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
                if (FALSE!==$inputXmlPart && is_array($inputXmlPart) && (count($inputXmlPart)>0)){
                    $xpathResult = $inputXmlPart[0];
                    $this->common->mlog('Part Contents : ' . $xpathResult->saveXML(),'DEBUG');
                    $rr = simplexml_load_string($this->http->forwardSingleHttpQuery($part['transformUrl'],array(), $xpathResult->saveXML()));
                    $this->common->mlog('Part Transformed : ' . $rr->saveXML(),'DEBUG');
                    $elements[$part['order']] = $rr;
                }else{
                    throw new HorusException('Could not extract location ' . $part['path'] . ' for part #' . $part['order']);
                }
            }else{
                throw new HorusException('No XPath to search for in configuration');
            }

        }

        $dom = new DomDocument();
        $root = new DomElement($matches['rootElement']);
        $dom->appendChild($root);
       

        foreach ($elements as $index=>$element){
            $part =$this->getPart($index,$matches);
            if (array_key_exists('targetPath',$part)){
                $this->common->mlog("Added element to XML response " . $index . ' at ' . $part['targetPath'],'INFO');
            }else{
                $this->common->mlog("Added element to XML response " . $index ,'INFO');
            }
            $domElement = $dom->importNode(dom_import_simplexml($element),TRUE);
            $root->appendChild($domElement);
        }
        
        return $dom->saveXml();



    }

    function doRecurseJson($reqBody,$matches){
        $this->common->mlog("Called Recurse Json with parameters " . print_r($matches,true) . ' and document = ' . print_r($reqBody,true),'INFO');
    }
}