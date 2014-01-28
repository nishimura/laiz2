<?php

namespace Laiz\Core\View;

use Laiz\Template\Parser;

class LaizView implements ViewInterface
{
    const PUBLIC_DIR = 'public';
    const CACHE_DIR = 'cache';
    const CONFIG_FILE = 'config/view.ini';

    private $template;
    private $path;
    private $file;

    public function __construct()
    {
        $publicdir = self::PUBLIC_DIR;
        $cachedir = self::CACHE_DIR;
        if (file_exists(self::CONFIG_FILE)){
            $config = parse_ini_file(self::CONFIG_FILE, true);
            if (isset($config['dir']['public']))
                $publicdir = $config['dir']['public'];

            if (isset($config['dir']['cache']))
                $publicdir = $config['dir']['cache'];
        }
        $this->template = new Parser($publicdir, $cachedir);

        if (isset($config['behavior'])){
            foreach ($config['behavior'] as $char => $callback)
                $this->template->addBehavior($char, $callback, true);
        }
    }
    public function setFile($file, $type = 'html')
    {
        $this->file = $file;
        return $this;
    }
    public function show($vars)
    {
        $this->template
            ->setFile($this->file)
            ->show($vars);
    }
}
