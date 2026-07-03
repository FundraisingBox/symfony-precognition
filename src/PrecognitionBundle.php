<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Registers the precognition listeners.
 *
 * The bundle has no configuration: the behaviour is entirely header-driven,
 * so there is nothing to wire per application beyond enabling the bundle.
 */
final class PrecognitionBundle extends AbstractBundle
{
    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import(__DIR__ . '/../config/services.php');
    }
}
