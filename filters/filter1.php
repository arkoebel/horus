<?php

/* Sample filter class. The class MUST implement HorusFilterInterface.
Do try to have different names for all filter classes.
If two different files use the same class names and are used within the same request, bad things will happen!
*/
class SampleFilter implements HorusFilterInterface
{
    /* Tests input for specific patterns, using the original query param "source",
            incoming http headers, other query params.
        After testing the input, an optional reason can be given for success/failure. That reason will be logged.
        The function MUST return either true on success, or false on failure 
            (meaning the input didn't match the requested conditions)
    */
    public function doFilter($input, $source, $headers, $queryparams, &$reasonFailed = null): bool
    {
        if($source == 'A'){
            //perform some complex operation here
            $regex1 = '/Sample-XMLv1-0609141402/';
            $regex2 = '/SAADBEBBXXX/';

            return preg_match($regex1, $input) && preg_match($regex2, $input);

        }else{
            $reasonFailed = 'not possible';
            return false;
        }

    }
}
