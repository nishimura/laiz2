<?php

namespace Laiz\Core;

use Zend\Di\DefinitionList;

class Di extends \Zend\Di\Di
{
    private $definition;
    public function __construct()
    {
        $this->definition = new FlexibleDefinition();
        parent::__construct(new DefinitionList($this->definition));
    }

    public function callMethod($instance, $method, $params = array())
    {
        $this->definition->processMethod($this->getClass($instance), $method);

        $alias = null;
        $methodIsRequired = true;

        $methodClass = $this->getClass($instance);

        $callParameters = $this->resolveMethodParameters($methodClass, $method, $params, $alias, $methodIsRequired);

        if (count($callParameters) > 0 &&
            $callParameters === array_fill(0, count($callParameters), null)){
            // Comment in the following: array(null) is handled as error
            //throw new \RuntimeException('Missing parameter ' . $methodClass . '::' . $method);
        }

        return call_user_func_array(array($instance, $method), $callParameters);
    }

    public function handleAnnotations($instance, $annotationClass, $runnerClass)
    {
        $class = $this->getClass($instance);
        $method = $this->definition->getAnnotationMethod($class, $annotationClass);
        if (!$method)
            return; // not exists annotation

        $props = $this->definition->getAnnotationProperties($class, $annotationClass);
        $runner = $this->get($runnerClass);
        if (!$method)
            throw new \RuntimeException($annotationClass . ' is not implements SingleContentAnnotation');

        foreach ($props as $prop => $params){
            foreach ($params as $param){
                $param = $this->trimParam($param);
                $callTimeParam =  array('content' => $param,
                                        'varName' => $prop);
                if (isset($instance->$prop))
                    $callTimeParam['request'] = $instance->$prop;

                array_unshift($params, 1);
                $instance->$prop =
                    $this->callMethod($runner, $method, $callTimeParam);
            }
        }
    }

    private function trimParam($param)
    {
        if (is_string($param) && $param[0] === '"')
            return trim($param, ' "');
        if (is_array($param)){
            $ret = array();
            foreach ($param as $k => $p)
                $ret[$this->trimParam($k)] = $this->trimParam($p);
            return $ret;
        }
        return $param;
    }

    /**
     * @override
     */
    public function newInstance($name, array $params = array(), $isShared = true)
    {
        $obj = parent::newInstance($name, $params, $isShared);
        if (method_exists($obj, 'autoload'))
            spl_autoload_register(array($obj, 'autoload'));
        return $obj;
    }
}
