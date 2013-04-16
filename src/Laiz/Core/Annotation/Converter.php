<?php

namespace Laiz\Core\Annotation;

class Converter extends ContentParserAnnotation implements SingleContentAnnotation
{
    public function getMethod()
    {
        return 'convert';
    }
}
