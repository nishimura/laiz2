<?php

namespace Laiz\Core;

use Laiz\Template\Parser;

class View
{
    const PUBLIC_DIR = 'public';
    const CACHE_DIR = 'cache';

    private $template;
    private $path;
    private $file;

    public function __construct()
    {
        $this->template = new Parser(self::PUBLIC_DIR, self::CACHE_DIR);
    }
    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }
    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }
    public function show($vars)
    {
        $this->template
            ->setFile($this->file)
            ->setPath($this->path)
            ->show($vars);
    }
}
