<?php

namespace Laiz\Core;

use stdClass;
use ReflectionClass;
use ReflectionMethod;

class Page
{
    private $di;
    private $filterContainer;

    public function __construct(Container $di, FilterContainer $fc)
    {
        $this->di = $di;
        $this->filterContainer = $fc;
    }

    private function getAnnotations($comment)
    {
        $annotations = array();
        $lines = explode("\n", $comment);
        foreach ($lines as $line){
            if (preg_match('/@([a-z]+) +([\\\\a-zA-Z0-9_]+)/', $line, $matches))
                $annotations[] = array($matches[1], $matches[2]);
        }
        return $annotations;
    }
    private function cast($typ, $value)
    {
        switch ($typ){
        case 'boolean':
        case 'bool':
            return (boolean)$value;

        case 'int':
        case 'integer':
            return (int)$value;

        case 'string':
            return (string)$value;

        case 'array':
            return (array)$value;

        case 'object':
            return (object)$value;

        default:
            if (preg_match('/^[A-Z]/', $typ)){
                $obj = $this->di->get($typ);
                if (!$obj)
                    return $value;

                if (is_array($value)){
                    foreach (get_object_vars($obj) as $k => $_){
                        if (isset($value[$k]))
                            $obj->$k = $value[$k];
                    }
                }
                return $obj;

            }else{
                return $value;
            }
        }
    }

    private function parseAnnotation($context, $annotation, $value)
    {
        switch ($annotation){
        case 'bind':
            $context->rTarget = $context->rClass->getProperty($value);
            $this->parseAnnotations($context);
            break;

        case 'db':
            $entity = $this->di->get('Laiz\Core\Entity');
            $entity->bind($value);
            $context->rTarget->setValue($context->obj,
                                        $entity);
            break;

        case 'var':
            $typeValue = $this->cast($value, $context->params->get($context->rTarget->getName()));
            $context->rTarget->setValue($context->obj, $typeValue);
            break;

        default:
            break;
        }
    }

    private function parseAnnotations($context)
    {
        $annotations = $this->getAnnotations($context->rTarget->getDocComment());
        if ($annotations){
            foreach ($annotations as $a)
                $this->parseAnnotation($context, $a[0], $a[1]);
        }
    }

    public function run($page, $methodName, $request, $response){
        $params = $request->isPost() ? $request->getPost() : $request->getQuery();
        foreach (get_object_vars($page) as $k => $_){
            if ($k[0] === '_')
                continue;

            $v = $params->get($k);
            if ($v !== null)
                $page->$k = $v;
        }

        $context = new stdClass();
        $context->obj = $page;
        $context->rClass = new ReflectionClass($page);
        $context->rTarget = new ReflectionMethod($page, $methodName);
        $context->params = $params;
        $context->response = $response;
        $this->parseAnnotations($context);

        $prepare = '_validate' . ucfirst($methodName);
        $err = false;
        if ($request->isPost() && method_exists($page,  $prepare)){
            $ret = $this->di->call($page, $prepare);
            if ($ret){
                $err = true;
                $errors = new stdClass();
                foreach ($ret as $k => $v){
                    if (is_array($v))
                        $errors->$k = (object)$v;
                    else
                        $errors->$k = $v;
                }
                $response->errors = $errors;
            }
        }

        $prepare = '_' . $methodName;
        if ($request->isPost() && method_exists($page, $prepare) && !$err){
            $ret = $this->di->call($page, $prepare);
            if ($ret)
                return $ret;
        }

        $ret = $this->di->call($page, $methodName);

        return $ret;
    }
}
