<?php

namespace Laiz\Core;

use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\Config;
use Zend\Config\Reader\Ini;
use Zend\Validator\ValidatorPluginManager;
use Laiz\Request\Util as RequestUtil;
use Laiz\Request\Exception\RedirectExceptionInterface;
use Laiz\Core\Response\ExceptionInterface as ResponseException;

class Controller
{

    const APPLICATION_ENV = 'APPLICATION_ENV';
    const ENV_DEV = 'development';

    private $di;
    private $config;
    private $request;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function bootstrap()
    {
        // initialize required instances
        $this->di = new Di();
        $im = $this->di->instanceManager();
        $im->addSharedInstance($this->di, get_class($this->di));

        $im->addTypePreference('Zend\Stdlib\RequestInterface',
                               'Zend\Http\PhpEnvironment\Request');
        $request = $this->di->get('Zend\Http\PhpEnvironment\Request');
        $env = $request->getServer(self::APPLICATION_ENV, self::ENV_DEV);

        // initialize di
        if (file_exists('config/di.ini')){
            $reader = new Ini();
            $diConfig = $reader->fromFile('config/di.ini');
            $this->setupDi($diConfig);
        }

        $this->request = $request;
        return $this;
    }

    private function setupDi($config)
    {
        $di = $this->di;
        $im = $di->instanceManager();

        // include other ini files.
        if (isset($config['include'])){
            $reader = new Ini();
            foreach ((array)$config['include'] as $file){
                $subConfig = $reader->fromFile($file);
                $this->setupDi($subConfig);
            }
            unset($config['include']);
        }
        foreach ($config as $class => $v){
            if (isset($v['alias']))
                $im->addAlias($class, $v['alias']);

            if (isset($v['parameters']))
                $im->setParameters($class, $v['parameters']);

            if (isset($v['interface']))
                $im->addTypePreference($v['interface'], $class);

            if (isset($v['methods'])){
                foreach ($v['methods'] as $method => $params){
                    $im->setInjections($class, array($method => (array)$params));
                }
            }

            if (isset($v['preload']) && $v['preload'])
                $di->get($class);
            if (isset($v['shared']))
                $im->setShared($class, $v['shared']);
        }
    }

    public static function init($config = null)
    {
        // initialize config
        if ($config === null){
            if (file_exists('config/config.ini')){
                $reader = new Ini();
                $config = $reader->fromFile('config/config.ini');
            }else{
                $config = array();
            }
        }
        $self = new static($config);
        return $self->bootstrap();
    }

    public function run(){
        $response = $this->di->get('Laiz\Core\Response');

        $actionConfig = isset($this->config['action']) ?
            $this->config['action'] : array();

        
        $this->di
            ->instanceManager()
            ->setParameters('Laiz\Core\Action',
                            array('response' => $response,
                                  'config' => $actionConfig));

        $action = $this->di->get('Laiz\Core\Action');

        // run action
        try {
            $ret = $action->run();
        }catch (RedirectExceptionInterface $e){
            // send redirect header to the browser
            RequestUtil::handleRedirectException($e, $this->request);
            return;
        }catch (ResponseException $e){
            // run custom response
            $e->respond();
            return;
        }

        // run default view
        $view = isset($this->config['view']['class']) ?
            $this->config['view']['class'] : 'Laiz\Core\View\LaizView';

        $view = new $view();
        if ($ret)
            $view->setFile($ret);
        else
            $view->setFile($action->getPageName() . '.html');
        $view->show($response);
    }
}
