<?php

namespace Laiz\Core\View;

interface ViewInterface
{
    public function setFile($file);
    public function show($response);
}
