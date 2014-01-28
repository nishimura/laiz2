<?php

namespace Laiz\Core;

abstract class Configure
{
    abstract protected function getPageNamespace();

    protected function configureContainer(Container $di)
    {
        $di->register('laizPageNamespace', $this->getPageNamespace());
        $di->register('laizViewPublicDir', 'public');
        $di->register('laizViewCacheDir', 'cache');
        $di->register('laizViewBehaviors', array());

        $di->setPrototype('Laiz\Core\Entity');

        return $di;
    }

    public static function getContainer()
    {
        $di = new Container();
        $di->register('Laiz\Core\Container', $di);

        $self = new static();
        $self->configureContainer($di);

        return $di;
    }
}
