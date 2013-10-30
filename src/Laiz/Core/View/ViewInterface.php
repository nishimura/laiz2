<?php

namespace Laiz\Core\View;

interface ViewInterface
{
    public function setFile($file, $type = 'html');
    public function show($response);
}
