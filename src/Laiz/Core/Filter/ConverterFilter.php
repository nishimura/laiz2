<?php

namespace Laiz\Core\Filter;

use Zend\Filter\FilterPluginManager;
use Zend\Filter\FilterInterface;

class ConverterFilter
{
    private $manager;
    public function __construct(FilterPluginManager $manager,
                                $iniFile = null)
    {
        $this->manager = $manager;

        if ($iniFile){
            $config = parse_ini_file($iniFile);
            foreach ($config as $k => $v)
                $this->manager->setInvokableClass($k, $v);
        }
    }
    public function convert($content, $request = null)
    {
        if ($request === null)
            return $request;

        if (is_object($request)){
            if (!is_array($content))
                throw new \RuntimeException('object converter must be array.');
            foreach ($content as $prop => $value) {
                if (!is_string($prop))
                    throw new \RuntimeException('object converter must have string key.');
                $request->$prop =
                    $this->convertInternal($value, $request->$prop);
            }
        } else {
            $request = $this->convertInternal($content, $request);
        }

        return $request;
    }
    private function convertInternal($filter, $request)
    {
        if (is_array($filter)) {
            foreach ($filter as $row) {
                $request = $this->convertInternal($row, $request);
            }
            return $request;
        }
        if (is_object($filter)){
            if ($filter instanceof FilterInterface){
                return $filter->filter($request);
            } else if (is_callable($filter)){
                return $filter($request);
            } else {
                throw new \RuntimeException('Unknown converter type: ' . get_class($filter));
            }
        } else {
            switch ($filter) {
            case 'trim':
                $name = 'stringtrim';
                break;
            case 'lower':
                $name = 'stringtolower';
                break;
            case 'upper':
                $name = 'stringtoupper';
                break;

            default:
                $name = $filter;
                break;
            }
            $converter = $this->manager->get($name);
            if ($converter instanceof FilterInterface)
                return $converter->filter($request);
            else if (is_callable($converter))
                return $converter($request);
            else
                throw new \RuntimeException('Unknown converter type: ' . get_class($converter));
        }
    }
}
