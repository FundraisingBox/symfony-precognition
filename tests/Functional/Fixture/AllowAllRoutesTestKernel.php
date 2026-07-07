<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional\Fixture;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class AllowAllRoutesTestKernel extends TestKernel
{
    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('precognition', [
            'allow_all_routes' => true,
        ]);
    }
}
