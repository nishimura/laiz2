<?php

namespace Laiz\Core;

use Zend\Http\PhpEnvironment\Request;

class FilterContainer
{
    private $di;
    private $filters = array();

    public function __construct(Container $di)
    {
        $this->di = $di;
    }

    public function run($method)
    {
        $req = new Request();
        $path = $req->getUri()->getPath();

        $params = $req->isPost() ? $req->getPost() : $req->getQuery();
        $ret = array();
        foreach ($this->filters as $filter){
            if (is_string($filter))
                $filter = $this->di->get($filter);

            if (!method_exists($filter, $method))
                continue;

            if ($filter->accept($path)){
                foreach (get_object_vars($filter) as $k => $_){
                    if ($k[0] === '_')
                        continue;

                    $v = $params->get($k);
                    if ($v !== null)
                        $filter->$k = $v;
                }
                $this->di->call($filter, $method);
                $ret[] = $filter;
            }
        }
        return $ret;
    }

    public function add($filter)
    {
        $this->filters[] = $filter;
    }
}
