<?php

namespace Laiz\Core\Annotation;

class SimpleLineParser
{
    public static function parseBuiltinAnnotations($text)
    {
        $annotations = array();
        foreach (explode("\n", $text) as $line){
            $ret = self::parseLine($line);
            if ($ret)
                $annotations[] = $ret;
        }
        return $annotations;
    }

    private static function parseLine($line)
    {
        if (preg_match('/@var +([^ ]+)/', $line, $matches)){
            $ret = new Variable();
            $content = $matches[1];
            $ret->initialize($content);
            return $ret;
        }
    }
}
