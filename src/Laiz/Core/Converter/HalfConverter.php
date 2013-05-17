<?php

namespace Laiz\Core\Converter;

class HalfConverter
{
    public function __invoke($arg)
    {
        if (is_string($arg)){
            $arg = mb_convert_kana($arg, 'asKV');
        }
        return $arg;
    }
}
