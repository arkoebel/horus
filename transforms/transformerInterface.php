<?php

interface HorusTransformerInterface {

    public static function doTransform(string $toTransform, array $headers, array $queryparams): string;

}