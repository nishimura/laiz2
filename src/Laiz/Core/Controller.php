<?php

namespace Laiz\Core;

use Zend\Http\PhpEnvironment\Request;
use Laiz\Request\Util as RequestUtil;
use Laiz\Request\Exception\RedirectExceptionInterface;
use Laiz\Core\Response\ExceptionInterface as ResponseException;
use Laiz\Template\Parser as Template;

use stdClass;
use ReflectionClass;
use ReflectionMethod;

class Controller
{
    private $di;

    protected function __construct($di)
    {
        $this->di = $di;
    }

    public static function newInstance($di)
    {
        return new static($di);
    }

    private function canonicalizePath($request)
    {
        $path = $request->getUri()->getPath();
        if (preg_match('|\.html/$|', $path))
            $path = rtrim($path, '/');
        else if (preg_match('|/$|', $path))
            $path .= 'index.html';

        $info = pathinfo($path);
        $path = $info['dirname'] . '/' . $info['filename'];
        $path = ltrim($path, '/');
        if (isset($info['extension']))
            $ext = $info['extension'];
        else
            $ext = 'html';
        return array($path, $ext);
    }

    private function getPageAndMethod($path)
    {
        $methodName = null;

        $dirs = array_map('ucfirst', explode('/', $path));
        $cls = array_pop($dirs);
        $parts = explode('_', $cls);
        if (count($parts) > 1)
            $method = array_pop($parts);
        else
            $method = 'index';
        $cls = implode('', array_map('ucfirst', $parts));
        $dirs[] = $cls;
        $clspath = implode('\\', $dirs);
        $clspath = implode('', array_map('ucfirst', explode('_', $clspath)));

        $clspath = $this->di->get('laizPageNamespace') . '\\' . $clspath;
        if (!class_exists($clspath) && $method != 'index'){
            $clspath .= '\\' . ucfirst($method);
            $method = 'index';
        }

        if (!class_exists($clspath))
            return array(null, null);

        $page = $this->di->get($clspath);
        if (!method_exists($page, $method))
            return array(null, null);

        return array($page, $method);
    }

    private function showView($file, $response)
    {
        $publicdir = $this->di->get('laizViewPublicDir');
        $cachedir = $this->di->get('laizViewCacheDir');
        $template = new Template($publicdir, $cachedir);

        $behaviors = $this->di->get('laizViewBehaviors');
        if ($behaviors){
            foreach ($behaviors as $char => $callback)
                $template->addBehavior($char, $callback, true);
        }

        $template
            ->setFile($file)
            ->show($response);
    }

    private function runFilter($method, $request, $response)
    {
        $filterContainer = $this->di->get('Laiz\Core\FilterContainer');

        try {
            $ret = $filterContainer->run($method);

        }catch (RedirectExceptionInterface $e){
            // send redirect header to the browser
            RequestUtil::handleRedirectException($e, $request);
            return false;
        }catch (ResponseException $e){
            // run custom response
            $e->respond();
            return false;
        }

        foreach ($ret as $obj){
            foreach ($obj as $k => $v)
                $response->$k = $v;
        }
        return true;
    }

    public function run(){
        $request = new Request();

        list($path, $ext) = $this->canonicalizePath($request);

        list($page, $methodName) = $this->getPageAndMethod($path);

        $response = new stdClass();

        if (!$this->runFilter('preFilter', $request, $response))
            return;

        $ret = null;
        if ($page && $methodName){
            try {

                $runner = $this->di->get('Laiz\Core\Page');
                $ret = $runner->run($page, $methodName, $request, $response);

                foreach ($page as $k => $v)
                    if ($k[0] !== '_')
                        $response->$k = $v;

            }catch (RedirectExceptionInterface $e){
                // send redirect header to the browser
                RequestUtil::handleRedirectException($e, $request);
                return;
            }catch (ResponseException $e){
                // run custom response
                $e->respond();
                return;
            }
        }

        if (!$this->runFilter('postFilter', $request, $response))
            return;

        if ($ret){
            $file = $ret;
        }else{
            $file = $path . '.' . $ext;
        }

        $this->showView($file, $response);
    }
}
