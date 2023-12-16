<?php

interface HorusFilterInterface {

    public function doFilter($input, $source, $headers, $queryparams): bool;

}
