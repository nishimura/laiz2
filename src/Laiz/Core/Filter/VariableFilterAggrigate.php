<?php

namespace Laiz\Core\Filter;

use Laiz\Core\Di;

class VariableFilterAggrigate
{
    private $di;
    private $defaultFilter = 'Laiz\Core\Filter\StandardVariableFilter';
    private $filters = array();
    public function __construct(Di $di, $iniFile = null)
    {
        $this->di = $di;
        if ($iniFile){
            $filters = parse_ini_file($iniFile);
            foreach ($filters as $filter)
                $this->filters[] = $filter;
        }
        $this->filters[] = $this->defaultFilter;
    }
    public function cast($content, $request = null)
    {
        foreach ($this->filters as $filterName){
            $filter = $this->di->get($filterName);
            if (!($filter instanceof VariableFilterInterface))
                throw new \RuntimeException(get_class($filter) . ' does not implements VariableFilterInterface');
            if ($filter->accept($content)){
                $ret = $filter->cast($content, $request);
                return $ret;
            }
        }
        return $ret;
    }
}
