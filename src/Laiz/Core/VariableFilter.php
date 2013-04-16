<?php

namespace Laiz\Core;

class VariableFilter
{
    private $filters =
        array('Laiz\Core\Annotation\Variable' =>
              'Laiz\Core\Annotation\VariableRunner',
              'Laiz\Core\Annotation\Converter' =>
              'Laiz\Core\Annotation\ConverterRunner');
    private $di;
    public function __construct(Di $di, $iniFile = null)
    {
        $this->di = $di;
        if ($iniFile){
            $config = parse_ini_file($iniFile);
            foreach ($config as $v)
                $this->filters[] = $v;
        }
    }

    public function handleAnnotations($target)
    {
        foreach ($this->filters as $annotationClass => $runnerClass)
            $this->di->handleAnnotations($target,
                                         $annotationClass,
                                         $runnerClass);
    }
}
