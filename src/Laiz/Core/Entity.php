<?php

namespace Laiz\Core;

use Laiz\Db\Db;
use Laiz\Core\RequestDatabaseMapper as Mapper;

class Entity
{
    private $db;
    private $target;
    private $mapper;

    public function __construct(Db $db, Mapper $mapper)
    {
        $this->db = $db;
        $this->mapper = $mapper;
    }

    public function bind($name)
    {
        $this->target = $this->mapper->get($name);
    }

    public function __set($name, $value)
    {
        $this->target->$name = $value;
    }

    public function __get($name)
    {
        return $this->target->$name;
    }

    public function __isset($name)
    {
        return isset($this->target->$name);
    }

    public function save()
    {
        $this->db->save($this->target);
    }

    public function delete()
    {
        $this->db->delete($this->target);
    }
}
