<?php

class MapperSample1 implements HorusMapperInterface {

    /* Mapping should return an array of destinations. Each destination is an associative array with the following keys:
       "destination": name of the destination for this step. Must be one of the values defined in the destinations defined in the global roadmap configuration file
       "comment": a comment describing that destination
       "transform" (optional) : name of a php file in the ./transforms folder used to transform a given input
       "transformUrl" (optional) : URL the input can be POSTed to to transform the given input
   */
    static function doMap(string $input, string $source, array $destinations): array {

        $result = array();

        $result[] = array(
            "destination" => "A",
            "comment" => "sample roadmap A=>B=>C Step A"
        );
        $result[] = array(
            "destination" => "B",
            "comment" => "sample roadmap A=>B=>C Step B"
        );
        $result[] = array(
            "destination" => "C",
            "comment" => "sample roadmap A=>B=>C Step C"
        );

        return $result;

    }
}