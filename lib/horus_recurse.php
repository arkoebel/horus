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

    function findSection($name,$matches){
        foreach($matches as $section){
            if($section['section']===$name){
                return $section;
            }

        }
        return null;
    }

    function doRecurse($reqBody,$content_type,$proxy_mode,$matches,$accept,$params){
        
        if(!array_key_exists('section',$params)){
            throw new HorusException('Section URL parameter is unknown');
        }

       // if(!array_key_exists($params['section'],$matches)){
       //     throw new HorusException('Section ' . $params['section'] . ' is unknown');
       // }

        $section = $this->findSection($params['section'],$matches);

        if($content_type !== $section['content-type']){
            throw new HorusException('Section ' . $params['section'] . " was supposed to be of type " . $section['content-type'] . ' but found ' . $content_type . ' instead');
        }

        $result = null;
        if ('application/xml'===$content_type){
            $result = $this->doRecurseXml($reqBody,$section);
        }elseif('application/json'===$content_type){
            $result = $this->doRecurseJson($reqBody,$section);
        }else{
            throw new HorusException('Unsupported content-type ' . $content_type);
        }

        return $this->http->returnWithContentType($result, $accept, 200, $proxy_mode);


    }


    function doRecurseXml($body,$section){
        $elements = array();
        $xml = simplexml_load_string($body);
        if (array_key_exists('namespaces',$section)){
            $this->xml->registerExtraNamespaces($xml,$section['namespaces']);
        }

        foreach ($section['parts'] as $part){
            $this->common->mlog('Dealing with part #' . $part['order'] . ' : ' . $part['comment'],'INFO');    
            $inputXmlPart = null;
            $vars = array();
            if(array_key_exists('variables',$part)){
                $this->common->mlog('Extracting variables for part #' . $part['order'] ,'DEBUG');
                foreach($part['variables'] as $name=>$xpath){
                    $elt = array('key'=>$name,'value'=>$this->xml->getXpathVariable($xml,$xpath));
                    $this->common->mlog('  Variable ' . $elt['key'] . ' = ' . $elt['value'] ,'DEBUG');
                    $vars[] = $elt;
                }
            }
            if(array_key_exists('path',$part)){
                $this->common->mlog('Extracting document from XPath=' . $part['path'],'DEBUG');
                $inputXmlPart = $xml->xpath($part['path']);
                if (FALSE!==$inputXmlPart && is_array($inputXmlPart) && (count($inputXmlPart)>0)){
                    $xpathResult = $inputXmlPart[0];
                    $this->common->mlog('Part Contents : ' . $xpathResult->saveXML(),'DEBUG');
                    $finalUrl = $this->common->formatQueryString($part['transformUrl'],$vars,TRUE);
                    $this->common->mlog('Transformation URL is : ' . $finalUrl,'DEBUG');
                    $rr = simplexml_load_string($this->http->forwardSingleHttpQuery($part['transformUrl'],array('Content-type: application/xml','Accept: application/xml','x-business-id: '), $xpathResult->saveXML()));
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
        $root = new DomElement($section['rootElement']);
        $dom->appendChild($root);
       

        foreach ($elements as $index=>$element){
            $part =$this->getPart($index,$section);
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
