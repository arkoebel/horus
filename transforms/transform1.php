<?php

/* Sample transformer class. The class MUST implement HorusTransformerInterface.
Do try to have different names for all transformer classes.
If two different files use the same class names and are used within the same request, bad things will happen!
*/
class MyClass implements HorusTransformerInterface {

    public static function doTransform(string $toTransform): string {

        $aa = simplexml_load_string($toTransform);

        $aa->registerXPathNamespace('saa', 'urn:swift:xsd:saa.mxs.01');
        $aa->registerXPathNamespace('doc', 'urn:swift:xsd:swift.if.ia$setr.016.001.02');

        $u = $aa->xpath('/saa:DataPDU/saa:Message/saa:MessageText/doc:Document');
        return $u[0]->asXML();
    }
}
