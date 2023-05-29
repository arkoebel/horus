<?php

interface Horus_HeaderInterface
{
    public function sendHeader($header, $replace = true, $error = 0);
}

class Horus_Header implements Horus_HeaderInterface
{

    public function sendHeader($header, $replace = true, $error = 0)
    {
        @header($header, $replace, $error);
    }
}

class Horus_HeaderMock implements Horus_HeaderInterface
{
    public function sendHeader($header, $replace = true, $error = 0)
    {
        error_log($header, $replace, $error);
    }
}
