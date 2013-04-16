<?php

namespace Laiz\Core\Annotation;

use Zend\Code\Annotation\AnnotationInterface;
use Zend\Code\NameInformation;
use Zend\Di\Di;

abstract class ContentParserAnnotation implements AnnotationInterface
{
    /**
     * @var mixed
     */
    protected $content = null;

    protected $contents = array();

    /**
     * {@inheritDoc}
     */
    public function initialize($content)
    {
        $this->content = $content;
    }

    public function parseInternal(NameInformation $nameInfo)
    {
        $buf = $this->content;
        if (strlen(trim($buf)) === 0)
            return;

        while (strlen($buf) > 0){
            list($k, $v, $buf) = $this->parseContent($nameInfo, $buf);
            if ($k !== null)
                $this->contents[$k] = $v;
        }
    }
    private function parseContent($nameInfo, $buf)
    {
        return array('content', $this->resolveValue($nameInfo, $buf), '');


        //
        // TODO: parse key
        //
        $tmp = explode('=', $buf, 2);
        if (count($tmp) !== 2){
            return array('content', $this->resolveValue($nameInfo, $tmp[0]), '');
        }

        list($key, $buf) = $tmp;
        $buf = trim($buf);
        $key = trim($key);
        $value = '';
        $max = strlen($buf);
        $inStr = $buf[0] === '"';
        for ($i = 0; $i < $max; $i++){
            if (!$inStr && $buf[$i] === ',')
                break;

            if ($inStr){
                if ($i != 0 && $buf[$i] === '"'){
                    $inStr = false;
                } else if ($buf[$i] === '\\' && $buf[$i + 1] === '"'){
                    $i++;
                }
            }

            $value .= $buf[$i];
        }
        $value = $this->resolveValue($nameInfo, $value);
        $ret = substr($buf, $i + 1);
        $ret = ltrim($ret, ' ,');
        return array($key, $value, $ret);
    }

    private function resolveArray($nameInfo, $value)
    {
        $arr = array();
        $inStr = $value[0] === '"';
        $inArr = $value[0] === '[';
        $buf = $value;
        $current = $value[0];
        $key = null;
        $max = strlen($buf);
        for ($i = 1; $i < $max; $i++){
            if (!$inStr && !$inArr && $buf[$i] === ','){
                if (is_string($key))
                    $arr[trim($key)] = trim($current);
                else
                    $arr[] = trim($current);
                $current = '';
                $key = null;
                continue;

            } else if (!$inStr && !$inArr &&
                       $buf[$i] === '=' && $buf[$i + 1] === '>'){
                $key = $current;
                $current = '';
                $i++;
                continue;

            }
            if (!$inStr){
                if ($buf[$i] === '"')
                    $inStr = true;

            }
            if ($inStr){
                if ($i != 0 && $buf[$i] === '"'){
                    $inStr = false;
                } else if ($buf[$i] === '\\' && $buf[$i + 1] === '"'){
                    $i++;
                }

            }
            if (!$inArr){
                if ($buf[$i] === '[')
                    $inArr = true;

            }
            if ($inArr) {
                if ($buf[$i] === ']')
                    $inArr = false;
            }

            $current .= $buf[$i];
        }
        if (strlen(trim($current)) !== 0){
            if (is_string($key))
                $arr[trim($key)] = trim($current);
            else
                $arr[] = trim($current);
        }
        $arr = array_map('trim', $arr);

        $ret = array();
        foreach ($arr as $k => $v)
            $ret[$k] = $this->resolveValue($nameInfo, $v);
        return $ret;
    }
    private function resolveValue($nameInfo, $value)
    {
        if ($value[0] === '[' && $value[strlen($value) - 1] === ']'){
            return $this->resolveArray($nameInfo,
                                       substr($value, 1, strlen($value) - 2));
        }

        if ($value[0] !== '"'){
            $backup = $value;
            $value = $nameInfo->resolveName($value);
            if (!class_exists($value) && ($this instanceof BuiltinAnnotation))
                $value = $backup;
        }
        return $value;
    }

    public function __call($name, $args)
    {
        if (!array_key_exists($name, $this->contents))
            throw new \RuntimeException("Not Exists $name in "
                                        . get_class($this)
                                        . "  annotation key.");
        return $this->contents[$name];
    }
}
