<?php

namespace Laiz\Core;

use ReflectionClass;
use ReflectionMethod;
use ReflectionException;
use Laiz\Core\Exception;

class Container
{
    private $components = array();
    private $params = array();
    private $aliases = array();
    private $methods = array();
    private $prototypes = array();
    private $initializers = array();

    public function __construct()
    {
    }

    public function setPrototype($name, $value = true)
    {
        $this->prototypes[$name] = true;
    }

    public function get($name)
    {
        if (isset($this->components[$name]))
            return $this->components[$name];

        if (isset($this->aliases[$name]))
            return $this->get($this->aliases[$name]);

        return $this->build($name);
    }
    public function register($name, $component)
    {
        $this->components[$name] = $component;
        return $this;
    }
    public function setParameters($className, $params)
    {
        foreach ($params as $name => $value)
            $this->params[$className][$name] = $value;
        return $this;
    }

    public function setMethods($className, $methods)
    {
        $this->methods[$className] = (array)$methods;
        return $this;
    }

    public function setAlias($from, $to)
    {
        $this->aliases[$from] = $to;
    }

    public function setInitializer($className, callable $callback)
    {
        $this->initializers[$className] = $callback;
    }

    public function call($obj, $name, $addParams = array())
    {
        try {
            $refMethod = new ReflectionMethod($obj, $name);
        }catch (ReflectionException $e){
            throw new Exception('auto parameter setting error', 0, $e);
        }
        $params = $this->getMethodParams($refMethod);
        foreach ($addParams as $p)
            $params[] = $p;
        return $refMethod->invokeArgs($obj, $params);
    }

    private function build($name)
    {
        $obj = $this->buildByReflection($name);

        if (isset($this->methods[$name])){
            foreach ($this->methods[$name] as $method){
                $this->call($obj, $method);
            }
        }
        return $obj;
    }

    private function buildByReflection($name)
    {
        if (!class_exists($name))
            throw new Exception("class name [$name] not found");

        try {
            $refClass = new ReflectionClass($name);
            $refMethod = $refClass->getConstructor();
            if ($refMethod !== null)
                $params = $this->getMethodParams($refMethod);
            else
                $params = array();
        }catch (ReflectionException $e){
            throw new Exception("build error", 0, $e);
        }

        $component = $refClass->newInstanceArgs($params);
        if (isset($this->initializers[$name]))
            $this->initializers[$name]($component);

        if (!isset($this->prototypes[$name]) || !$this->prototypes[$name])
            $this->register($name, $component);

        return $component;
    }

    private function getMethodParams($refMethod)
    {
        $params = array();
        $refParams = $refMethod->getParameters();
        foreach ($refParams as $refParam){
            $params[] = $this->getParam($refParam);
        }
        return $params;
    }
    private function getParam($refParam)
    {
        $className = $refParam->getDeclaringClass()->getName();
        $methodName = $refParam->getDeclaringFunction()->getName();
        $paramName = $refParam->getName();
        if (isset($this->params[$className][$paramName]))
            return $this->params[$className][$paramName];

        $refClass = $refParam->getClass();
        if (!$refClass){
            if (!$refParam->isOptional())
                throw new Exception('can not parse ' .
                                    $className . '#' . $methodName);
            return $refParam->getDefaultValue();
        }

        $name = $refClass->getName();

        try {
            return $this->get($name);
        }catch (Exception $e){
            if (!$refParam->isOptional())
                throw $e;

            return $refParam->getDefaultValue();
        }
    }
}
