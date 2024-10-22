<?php

interface HorusMapperInterface {
    public static function doMap(string $input, string $source, array $destinationNames, array $headers, array $queryparams, string $transformed = null): array;
}