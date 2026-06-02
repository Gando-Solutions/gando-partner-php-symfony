<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony;

use Gando\Partner\Symfony\DependencyInjection\GandoPartnerExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class GandoPartnerBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new GandoPartnerExtension();
    }
}
