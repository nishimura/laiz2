<?php

namespace Laiz\Core\Annotation;

class Variable extends ContentParserAnnotation implements BuiltinAnnotation, SingleContentAnnotation
{
    public function getMethod()
    {
        return 'cast';
    }
}
