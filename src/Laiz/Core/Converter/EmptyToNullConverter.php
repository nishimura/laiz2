<?php

namespace Laiz\Core\Converter;

class EmptyToNullConverter
{
    public function __invoke($arg)
    {
        if (is_string($arg) && strlen(trim($arg)) === 0)
            $arg = null;
        return $arg;
    }
}
