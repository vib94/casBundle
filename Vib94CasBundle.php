<?php

namespace Vib94\CasBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Vib94\CasBundle\DependencyInjection\CasExtension;

class Vib94CasBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new CasExtension();
    }
}
