<?php

namespace Laiz\Core\Filter;

use Zend\ServiceManager\ServiceManager;
use Zend\Validator\ValidatorPluginManager;
use Zend\Config\Reader\Ini;
use Zend\Stdlib\RequestInterface;
use Laiz\Core\Response;

class ValidatorFilter
{
    private $dir = 'config/validator';
    private $trigger = 'trigger';
    private $errorPrefix = 'error';
    private $valid = null; // null: not run, true: valid, false: invalid
    private $request;
    private $response;
    private $manager;
    public function __construct(RequestInterface $request, Response $response
                                , ValidatorPluginManager $manager, $iniFile = null)
    {
        $this->request = $request;
        $this->response = $response;
        $this->manager = $manager;

        if ($iniFile){
            $config = parse_ini_file($iniFile);
            foreach ($config as $k => $v)
                $this->manager->setInvokableClass($k, $v);
        }
    }

    public function setTrigger($trigger)
    {
        $this->trigger = $trigger;
    }
    public function setErrorPrefix($errorPrefix)
    {
        $this->errorPrefix = $errorPrefix;
    }
    public function getValid()
    {
        return $this->valid;
    }

    public function valid($content, $request = null, $varName = null)
    {
        $reqParams = $this->request->isPost() ?
            $this->request->getPost() : $this->request->getQuery();
        if (!$reqParams->get($this->trigger))
            return $request;

        $file = $this->dir . '/' . $content;
        if (!file_exists($file))
            throw new \RuntimeException("$content file not found.");
        $reader = new Ini();
        $config = $reader->fromFile($file);

        if ($this->valid === null)
            $this->valid = true;

        if (is_object($request)){
            foreach ($config as $k => $v){
                $this->validInternal($v, isset($request->$k) ? $request->$k : null, $varName . ucfirst($k));
            }
        }else{
            $this->validInternal($config, $request, $varName);
        }

        return $request;
    }
    private function validInternal($config, $value, $varName)
    {
        foreach ($config as $name => $message){
            $args = array();
            while (is_array($message)){
                list($arg, $message) = each($message);
                $args[] = $arg;
            }
            $ret = $this->validLine($name, $value, $args);
            if (!$ret){
                $errorKey = $this->errorPrefix . ucfirst($varName);
                $this->response->$errorKey = $message;
                $this->valid = false;
                return;
            }
        }
    }
    private function validLine($name, $value, $args)
    {
        switch ($name){
        case 'required':
            $validator = $this->manager->get('notempty');
            break;
        case 'min':
        case 'max':
            if (is_numeric($value)){
                $validator = $this->manager->get('between');
                $validator->{'set' . ucfirst($name)}($args[0]);
            }else{
                $validator = $this->manager->get('stringlength');
                $validator->{'set' . ucfirst($name)}($args[0]);
            }
            break;

        default:
            $validator = $this->manager->get($name);
            break;
        }

        return $validator->isValid($value);
    }
}
