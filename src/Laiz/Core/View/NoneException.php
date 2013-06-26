<?php

namespace Laiz\Core\View;

use Exception;

class NoneException extends Exception implements ExceptionInterface
{
    public function run(){}
}
