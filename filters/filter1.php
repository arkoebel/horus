<?php

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
