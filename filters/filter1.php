<?php

/* Sample filter class. The class MUST implement HorusFilterInterface.
Do try to have different names for all filter classes.
If two different files use the same class names and are used within the same request, bad things will happen!
*/
class SampleFilter implements HorusFilterInterface
{
    public function doFilter($input, $source): bool
    {
        if($source == 'A'){
            //perform some complex operation here
            $regex1 = '/Sample-XMLv1-0609141402/';
            $regex2 = '/SAADBEBBXXX/';

            return preg_match($regex1, $input) && preg_match($regex2, $input);

        }else{
            return false;
        }

    }
}
