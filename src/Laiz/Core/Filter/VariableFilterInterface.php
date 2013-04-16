<?php

namespace Laiz\Core\Filter;

interface VariableFilterInterface
{
    public function accept($content);
    public function cast($content, $request);
}
