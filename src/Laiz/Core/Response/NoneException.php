<?php

namespace Laiz\Core\Response;

use Exception;

class NoneException extends Exception implements ExceptionInterface
{
    public function respond(){}
}
