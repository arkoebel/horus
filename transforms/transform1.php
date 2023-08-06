<?php

class MyClass implements HorusTransformer {

    public static function doTransform(string $toTransform): string {

        $aa = simplexml_load_string($toTransform);

        error_log($aa->asXML());

        $aa->registerXPathNamespace('saa', 'urn:swift:xsd:saa.mxs.01');
        $aa->registerXPathNamespace('doc', 'urn:swift:xsd:swift.if.ia$setr.016.001.02');

        $u = $aa->xpath('/saa:DataPDU/saa:Message/saa:MessageText/doc:Document');
        return $u[0]->asXML();
    }
}
