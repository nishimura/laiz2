<?php

namespace Laiz\Core;

use Zend\Http\PhpEnvironment\Request;

class Action
{
    const DEFAULT_NAMESPACE = 'App\Page';

    const ACTION_DEFAULT = 'act';
    const ACTION_INDEX = 'index';
    const ACTION_GET = 'info';
    const ACTION_ADD = 'add';
    const ACTION_EDIT = 'edit';
    const ACTION_DELETE = 'delete';

    private $actions = array(self::ACTION_INDEX,
                             self::ACTION_GET,
                             self::ACTION_ADD,
                             self::ACTION_EDIT,
                             self::ACTION_DELETE);

    private $di;
    private $request;
    private $response;
    private $config;
    private $filterConfigFile;

    private $converterConfig;
    private $preActionConfig;
    private $postActionConfig;

    private $pageName;
    private $actionName;

    private $called = array();

    public function __construct(Di $di, Request $request, Response $response,
                                array $config)
    {
        $this->di = $di;
        $this->request = $request;
        $this->response = $response;
        $this->config = $config;

        $this->filterConfigFile = 'config/filter.ini';
    }

    public function run()
    {
        $this->parseFilter();

        // convert all request variables
        $converterFilter = $this->di->get('Laiz\Core\Filter\ConverterFilter');
        foreach ($this->converterConfig as $filterName => $requestName) {
            $this->runConverter($converterFilter, $filterName, $requestName);
        }

        // action filter
        foreach ($this->preActionConfig as $k => $v){
            $this->runFilter($k, $v, 'filter');
        }

        $ret = $this->runAction();

        // display filter
        foreach ($this->postActionConfig as $k => $v)
            $this->runFilter($k, $v, 'display');

        return $ret;
    }

    private function runAction()
    {
        list($pageClass, $method) = $this->getPageClassAndMethod();

        if (!class_exists($pageClass))
            return null;

        if (in_array($pageClass, $this->called))
            throw new \RuntimeException('loop?');

        $this->called[] = $pageClass;

        $page = $this->di->newInstance($pageClass, array(), false);

        // set request parameters and process annotations
        $this->assignProperties($page);

        if (!method_exists($page, $method) &&
            $method === self::ACTION_ADD &&
            method_exists($page, self::ACTION_EDIT))
            $method = self::ACTION_EDIT;

        if (!method_exists($page, $method) &&
            method_exists($page, self::ACTION_DEFAULT))
            $method = self::ACTION_DEFAULT;

        if (!method_exists($page, $method))
            throw new \RuntimeException('Undefined method ' . $pageClass . '::' . $method);

        $validator = $this->di->get('Laiz\Core\Filter\ValidatorFilter');
        $valid = $validator->getValid();
        $params = array();
        if ($valid !== null)
            $params['valid'] = $valid;
        $ret = $this->di->callMethod($page, $method, $params);
        $this->assignResponse($page);

        return $ret;
    }

    private function assignProperties($target)
    {
        // set request parameters
        $reqParams = $this->request->isPost() ?
            $this->request->getPost() : $this->request->getQuery();

        foreach ($target as $k => $v)
            $target->$k = $reqParams->get($k);

        // @var cast variables
        $this->di->handleAnnotations($target, 'Laiz\Core\Annotation\Variable',
                                     'Laiz\Core\Filter\VariableFilterAggrigate');

        // @Converter
        $this->di->handleAnnotations($target, 'Laiz\Core\Annotation\Converter',
                                     'Laiz\Core\Filter\ConverterFilter');

        // @Validator
        $this->di->handleAnnotations($target, 'Laiz\Core\Annotation\Validator',
                                     'Laiz\Core\Filter\ValidatorFilter');
    }
    private function assignResponse($obj)
    {
        foreach ($obj as $k => $v)
            $this->response->$k = $v;
    }
    private function getNamespace()
    {
        if (isset($this->config['namespace']))
            return $this->config['namespace'];

        return self::DEFAULT_NAMESPACE;
    }

    private function getPageClassAndMethod()
    {
        $actionName = null;
        // separate namespace
        $pageName = $this->getPageName();

        $parts = explode('/', $pageName);
        if (in_array($parts[count($parts) - 1], $this->actions))
            $actionName = array_pop($parts);

        // separate word
        $parts = array_map('ucfirst', $parts);
        $path = implode('\\', $parts);
        $parts = explode('_', $path);
        if (!$actionName &&
            (in_array($parts[count($parts) - 1], $this->actions)))
            $actionName = array_pop($parts);

        $parts = array_map('ucfirst', $parts);
        if (implode('', $parts) === '')
            $parts = array('Index'); // default: Package\Page\Index#index
        $className = $this->getNamespace() . '\\' . implode('', $parts);

        if (!$actionName)
            $actionName = self::ACTION_INDEX;

        return array($className, $actionName);
    }

    public function getPageName($force = false)
    {
        if (!$force && $this->pageName)
            return $this->pageName;

        $path = $this->request->getUri()->getPath();
        if (preg_match('|\.html/$|', $path))
            $path = rtrim($path, '/');
        else if (preg_match('|/$|', $path))
            $path .= 'index.html';

        $info = pathinfo($path);
        $pageName = $info['dirname'] . '/' . $info['filename'];
        $pageName = ltrim($pageName, '/');
        $this->pageName = $pageName;
        return $pageName;
    }

    private function parseFilter()
    {
        if (file_exists($this->filterConfigFile)){
            $reader = new \Zend\Config\Reader\Ini();
            $config = $reader->fromFile($this->filterConfigFile);
        }else{
            $config = array();
        }

        $this->converterConfig = isset($config['converter']) ?
            $config['converter'] : array();
        $this->preActionConfig = isset($config['filter']) ?
            $config['filter'] : array();
        $this->postActionConfig = isset($config['display']) ?
            $config['display'] : array();
    }

    private function runFilter($name, $config, $method){
        if (!is_array($config))
            throw new \RuntimeException("Invalid filter config: $name");

        if (!isset($config['pattern']))
            throw new \RuntimeException("Invalid filter config: pattern key not exists in $name");

        if (!isset($config['class']))
            throw new \RuntimeException("Invalid filter config: class key not exists in $name");

        $path = $this->request->getUri()->getPath();
        $run = false;
        if ($config['pattern'] === '*')
            $run = true;
        else if (preg_match($this->escapePattern($config['pattern']), $path))
            $run = true;
            
        if ($run === true && isset($config['ignorePatterns'])){
            foreach ((array)$config['ignorePatterns'] as $pt){
                if (preg_match($this->escapePattern($pt), $path)){
                    $run = false;
                    break;
                }
            }
        }

        if (!$run)
            return;

        $obj = $this->di->newInstance($config['class'], array(), false);

        $this->assignProperties($obj);

        $this->di->callMethod($obj, $method);

        $this->assignResponse($obj);
    }

    private function escapePattern($pattern)
    {
        $pattern = str_replace('@', '\@', $pattern);
        return '@' . $pattern . '@';
    }

    private function runConverter($converterFilter, $filterName, $requestName)
    {
        $reqParams = $this->request->isPost() ?
            $this->request->getPost() : $this->request->getQuery();
        if ($requestName === '*'){
            foreach ($reqParams as $k => $v){
                $ret = $this->runConverterInternal($converterFilter, $filterName, $v);
                $reqParams->set($k, $ret);
            }
        } else {
            $param = $reqParams->get($requestName);
            $ret = $this->runConverterInternal($converterFilter, $filterName, $param);
            $reqParams->set($requestName, $ret);
        }
    }

    private function runConverterInternal($converterFilter, $filter, $request)
    {
        if (is_array($request)){
            foreach ($request as $k => $v){
                $request[$k] = $this->runConverterInternal($converterFilter, $filter, $v);
            }
        } else {
            $request = $converterFilter->convert($filter, $request);
        }
        return $request;
    }
}
