<?php

namespace Laiz\Core;

use Laiz\Db\Db;
use Zend\Http\PhpEnvironment\Request as ZendRequest;

class RequestDatabaseMapper
{
    private $db;
    private $request;
    private $voCache = array();

    public function __construct(Db $db)
    {
        $this->db = $db;
        $this->request = new ZendRequest();
    }

    public function get($name)
    {
        if (isset($this->voCache[$name]))
            return $this->voCache[$name];

        $orm = $this->db->from($name);
        $pkeyNames = $orm->getPkeyColumns();
        $pkeys = [];
        $request = $this->fromRequest($name);
        $fromDb = true;
        foreach ($pkeyNames as $pkeyName){
            if (!isset($request[$pkeyName]) || strlen($request[$pkeyName]) === 0){
                $fromDb = false;
                break;
            }
            $pkeys[] = $request[$pkeyName];
        }
        if ($fromDb){
            try {
                $vo = $orm->id($pkeys)->result();
            }catch (\Exception $e){
            }
        }else{
            $vo = $orm->emptyVo();
        }

        if (!isset($vo)){
            $vo = $orm->emptyVo();
        }else{
            foreach ($request as $k => $v){
                if (property_exists($vo, $k))
                    $vo->$k = $v;
            }
        }

        $this->voCache[$name] = $vo;
        return $vo;
    }

    public function fromRequest($name)
    {
        $params = $this->request->isPost() ?
            $this->request->getPost() : $this->request->getQuery();
        return (array)$params->get(lcfirst($name));
    }

}
