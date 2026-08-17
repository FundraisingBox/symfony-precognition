<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition;

use InvalidArgumentException;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Registers the precognition listeners.
 */
final class PrecognitionBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('allow_all_routes')
                    ->info('Allow precognitive requests on every route instead of requiring #[Precognitive].')
                    ->defaultFalse()
                ->end()
            ->end()
        ;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $allowAllRoutes = $config['allow_all_routes'] ?? false;
        if (!is_bool($allowAllRoutes)) {
            throw new InvalidArgumentException('The "precognition.allow_all_routes" option must be a boolean.');
        }

        $container->setParameter('precognition.allow_all_routes', $allowAllRoutes);

        $configurator->import(__DIR__ . '/../config/services.php');
    }
}
