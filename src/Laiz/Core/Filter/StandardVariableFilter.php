<?php

namespace Laiz\Core\Filter;

class StandardVariableFilter implements VariableFilterInterface
{
    public function accept($content)
    {
        return true;
    }
    public function cast($content, $request = null)
    {
        if (is_string($content))
            $request = $this->castPrimitive($content, $request);
        else if (is_object($content))
            $request = $this->castObject($content, $request);

        return $request;
    }
    private function castObject($content, $request)
    {
        if (!is_array($request))
            return $content;

        foreach ($request as $k => $v)
            $content->$k = $v;

        return $content;
    }
    private function castPrimitive($content, $request)
    {
        $content = strtolower($content);
        switch ($content){
        case 'int':
        case 'integer':
        case 'float':
        case 'double':
        case 'real':
        case 'string':
            settype($request, $content);
            break;

        case 'bool':
        case 'boolean':
            if ($request === 'false')
                $request = false;
            else
                settype($request, $content);

        default:
            break;
        }
        return $request;
    }
}
