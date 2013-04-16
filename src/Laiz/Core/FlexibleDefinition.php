<?php

namespace Laiz\Core;

use Zend\Code\Annotation\AnnotationInterface;
use Zend\Code\Annotation\AnnotationCollection;
use Zend\Code\Annotation\AnnotationManager;
use Zend\Code\Annotation\Parser\GenericAnnotationParser;
use Zend\Code\Reflection\ClassReflection;
use Zend\Code\Scanner\CachingFileScanner;
use Zend\Di\Definition\RuntimeDefinition;
use Zend\Di\Definition\IntrospectionStrategy;
use Zend\Di\Definition\Annotation\Inject;


class FlexibleDefinition extends RuntimeDefinition
{
    private $autoMethods = array();

    public function __construct(IntrospectionStrategy $strategy = null, array $explicitClasses = null)
    {
        if ($strategy === null){
            $annotationManager = new AnnotationManager();
            $parser = new GenericAnnotationParser();
            $this->registerAnnotations($parser);
            $annotationManager->attach($parser);
            $strategy = new IntrospectionStrategy($annotationManager);
        }
        parent::__construct($strategy, $explicitClasses);
    }

    protected function registerAnnotations($parser)
    {
        $parser->registerAnnotation(new Inject());
        $parser->registerAnnotation(new Annotation\Validator());
        $parser->registerAnnotation(new Annotation\Converter());
        $parser->registerAnnotation(new Annotation\Variable());
    }

    /**
     * @override
     */
    protected function processClass($class)
    {
        parent::processClass($class);

        $this->processProperties($class);
    }
    protected function processProperties($class)
    {
        $strategy = $this->introspectionStrategy;
        $rClass = new ClassReflection($class);

        $def = &$this->classes[$class];
        $def['properties'] = array();
        $def['annotations'] = array();
        foreach ($rClass->getProperties() as $rProp){
            $propName = $rProp->getName();

            // parser of laiz for builtin annotations
            $annotations = Annotation\SimpleLineParser
                ::parseBuiltinAnnotations($rProp->getDocComment());
            $this->parseAnnotations($annotations, $rClass, $propName, $def);

            // parser of zend
            $annotations = $rProp->getAnnotations($strategy->getAnnotationManager());
            if (!($annotations instanceof AnnotationCollection))
                continue;

            $this->parseAnnotations($annotations, $rClass, $propName, $def);
        }
    }
    protected function parseAnnotations($annotations, $rClass, $propName, &$def)
    {
        foreach ($annotations as $a){
            $this->prepareAnnotation($rClass, $a);

            if ($a instanceof Annotation\ContentParserAnnotation){
                $aName = get_class($a);
                $def['properties'][$aName][$propName][] = $a->content();
                if (($a instanceof Annotation\SingleContentAnnotation) &&
                    !isset($def['annotations'][$aName]['method']))
                    $def['annotations'][$aName]['method'] = $a->getMethod();
            }
        }
    }

    protected function prepareAnnotation(ClassReflection $rClass,
                                         AnnotationInterface $annotation)
    {
        if ($annotation instanceof Annotation\ContentParserAnnotation){
            $fileScanner = new CachingFileScanner($rClass->getFileName());
            $nameInfo = $fileScanner->getClassNameInformation($rClass->getname());
            $annotation->parseInternal($nameInfo);
        }
    }

    public function getAnnotationProperties($class, $annotationClass)
    {
        if (!isset($this->classes[$class]))
            $this->processClass($class);

        return $this->classes[$class]['properties'][$annotationClass];
    }
    public function getAnnotationMethod($class, $annotationClass)
    {
        if (!isset($this->classes[$class]))
            $this->processClass($class);

        if (isset($this->classes[$class]['annotations'][$annotationClass]['method']))
            return $this->classes[$class]['annotations'][$annotationClass]['method'];
        else
            return false;
    }

    public function processMethod($class, $method)
    {
        $rClass = new \Zend\Code\Reflection\ClassReflection($class);

        $className = $rClass->getName();
        $def = &$this->classes[$className];

        if ($rClass->hasMethod($method)){
            $def['methods'][$method] = true;
            $this->processParams($def, $rClass, $rClass->getMethod($method));
        }
    }
}
