<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition;

use InvalidArgumentException;
use LogicException;
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
        $rootNode = $definition->rootNode();

        $this->configureRootNode($rootNode);
    }

    private function configureRootNode(object $rootNode): void
    {
        // Symfony 6.4.0 exposes a broad PHPDoc type here; guarded dynamic calls
        // keep phpstan clean across lowest and latest dependency sets.
        $childrenCallable = [$rootNode, 'children'];
        if (!is_callable($childrenCallable)) {
            throw new LogicException('The root configuration node must support child nodes.');
        }

        $children = call_user_func($childrenCallable);
        if (!is_object($children)) {
            throw new LogicException('The root configuration node children builder must be an object.');
        }

        $booleanNodeCallable = [$children, 'booleanNode'];
        if (!is_callable($booleanNodeCallable)) {
            throw new LogicException('The root configuration node children builder must support boolean nodes.');
        }

        $booleanNode = call_user_func($booleanNodeCallable, 'allow_all_routes');
        if (!is_object($booleanNode)) {
            throw new LogicException('The allow_all_routes configuration node must be an object.');
        }

        $infoCallable = [$booleanNode, 'info'];
        if (!is_callable($infoCallable)) {
            throw new LogicException('The allow_all_routes configuration node must support info text.');
        }
        call_user_func($infoCallable, 'Allow precognitive requests on every route instead of requiring #[Precognitive].');

        $defaultFalseCallable = [$booleanNode, 'defaultFalse'];
        if (!is_callable($defaultFalseCallable)) {
            throw new LogicException('The allow_all_routes configuration node must support a false default.');
        }
        call_user_func($defaultFalseCallable);

        $booleanNodeEndCallable = [$booleanNode, 'end'];
        if (!is_callable($booleanNodeEndCallable)) {
            throw new LogicException('The allow_all_routes configuration node must support ending the node.');
        }
        call_user_func($booleanNodeEndCallable);

        $childrenEndCallable = [$children, 'end'];
        if (!is_callable($childrenEndCallable)) {
            throw new LogicException('The root configuration node children builder must support ending the node.');
        }
        call_user_func($childrenEndCallable);
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
