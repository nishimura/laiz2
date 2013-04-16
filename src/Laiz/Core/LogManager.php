<?php

namespace Laiz\Core;

use Monolog\Logger;
use Monolog\Handler;

class LogManager
{
    private $logLevels = array('info'      => Logger::INFO,
                               'notice'    => Logger::NOTICE,
                               'warning'   => Logger::WARNING,
                               'error'     => Logger::ERROR,
                               'critical'  => Logger::CRITICAL,
                               'alert'     => Logger::ALERT,
                               'emergency' => Logger::EMERGENCY);

    const NAME = 'global';
    private $logger;
    private $display;

    private $config;

    public function __construct($config, $display)
    {
        $this->config = $config;
        $this->logger = $this->newLogger(self::NAME);
        $this->display = $display;
    }

    public function setErrorHandler()
    {
        set_error_handler(array($this, 'errorHandler'));
        return $this;
    }
    public function setExceptionLogHandler()
    {
        if (!$this->display)
            set_exception_handler(array($this, 'exceptionHandler'));
        return $this;
    }
    public function exceptionHandler($e)
    {
        $this->logger->error($e->__toString());
    }
    public function errorHandler($no, $msg, $file, $line, $ctx)
    {
        if (!($no & error_reporting()))
            return true;
        $context = array();
        foreach ($ctx as $k => $v)
            if ($k !== 'GLOBALS' &&
                !preg_match('/^_[A-Z]+$/', $k))
                $context[$k] = $v;

        switch ($no) {
        case E_ERROR:
        case E_USER_ERROR:
            $this->logger->error($msg, $context);
            break;
        case E_WARNING:
        case E_USER_WARNING:
            $this->logger->warning($msg, $context);
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $this->logger->notice($msg, $context);
            break;

        defaut:
            $this->logger->warning($msg, $context);
            break;
        }

        // if return false then continue default error handling
        return !$this->display;
    }

    private function setHandler($logger, $name, $args)
    {
        $levels = array('warning' => Logger::WARNING);
        switch ($name) {
        case 'RotatingFileHandler':
            $max = isset($args['maxFiles']) ? $args['maxFiles'] : 0;
            $level = isset($this->logLevels[$args['level']]) ?
                $this->logLevels[$args['level']] : Logger::INFO;
            $logger->pushHandler(new Handler\RotatingFileHandler($args['file'], $max, $level));
            break;

        case 'StreamHandler':
            $level = isset($this->logLevels[$args['level']]) ?
                $this->logLevels[$args['level']] : Logger::INFO;
            $logger->pushHandler(new Handler\StreamHandler($args['file'], $level));
            break;

        case 'ChromePHPHandler':
            $logger->pushHandler(new Handler\ChromePHPHandler());
            break;

        default:
            break;
        }
    }

    private function setProcessor($logger, $name)
    {
        switch ($name) {
        case 'IntrospectionProcessor':
        case 'WebProcessor':
        case 'MemoryUsageProcessor':
        case 'MemoryPeakUsageProcessor':
        case '':
            $name = 'Monolog\\Processor\\' . $name;
            $logger->pushProcessor(new $name());
            break;

        default:
            break;
        }
    }

    public function getLogger()
    {
        return $this->logger;
    }
    public function newLogger($name)
    {
        $logger = new Logger($name);

        if (isset($this->config['handlers'])){
            foreach ($this->config['handlers'] as $handler => $args){
                $this->setHandler($logger, $handler, $args);
            }
        }
        if (isset($this->config['handlers'],
                  $this->config['processors'])){
            foreach ($this->config['processors'] as $proc){
                $this->setProcessor($logger, $proc);
            }
        }
        return $logger;
    }
}
