<?php

namespace Laiz\Core\Annotation;

class Validator extends ContentParserAnnotation implements SingleContentAnnotation
{
    public function getMethod()
    {
        return 'valid';
    }
}
