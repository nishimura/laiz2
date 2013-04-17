<?php

namespace Laiz\Core;

use \Zend\ServiceManager\ServiceManager;
use \Zend\ServiceManager\Config;
use \Laiz\Request\Util as RequestUtil;
use \Laiz\Request\Exception\RedirectExceptionInterface;
use \Zend\Config\Reader\Ini;
use \Zend\Validator\ValidatorPluginManager;

class Controller
{

    const APPLICATION_ENV = 'APPLICATION_ENV';
    const ENV_DEV = 'development';

    private $di;
    private $config;
    private $logManager;
    private $request;
    private $isDev;

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
        $this->isDev = $env === self::ENV_DEV;

        $config = isset($this->config['logger']) ?
            $this->config['logger'] : array();
        $this->di
            ->instanceManager()
            ->setParameters('Laiz\Core\LogManager',
                            array('config' => $config,
                                  'display' => $this->isDev));

        // initialize di
        if (file_exists('config/di.ini')){
            $reader = new Ini();
            $diConfig = $reader->fromFile('config/di.ini');
            $this->setupDi($diConfig);
        }

        $this->logManager = $this->di->get('Laiz\Core\LogManager');

        $this->logManager
            ->setErrorHandler()
            ->setExceptionLogHandler();

        $this->request = $request;
        return $this;
    }

    private function setupDi($config)
    {
        $di = $this->di;
        $im = $di->instanceManager();
        foreach ($config as $class => $v){
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
            RequestUtil::handleRedirectException($e, $this->request);
            return;
        }

        // run view
        $view = new View();
        if ($ret)
            $view->setFile($ret);
        else
            $view->setPath($action->getPageName());
        $view->show($response);
    }
}
