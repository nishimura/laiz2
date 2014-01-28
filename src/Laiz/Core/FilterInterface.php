<?php

namespace Laiz\Core;

interface FilterInterface
{
    public function accept($path);
    // public function preFilter(); // some arguments
    // public function postFilter(); // some arguments
}
