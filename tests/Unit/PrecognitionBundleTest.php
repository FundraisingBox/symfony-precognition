<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Unit;

use FundraisingBox\Precognition\EventListener\PrecognitionActivationListener;
use FundraisingBox\Precognition\PrecognitionBundle;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

#[CoversClass(PrecognitionBundle::class)]
final class PrecognitionBundleTest extends TestCase
{
    public function testConfiguresAndLoadsDefaultServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $extension = (new PrecognitionBundle())->getContainerExtension();

        self::assertNotNull($extension);
        $extension->load([[]], $container);

        self::assertFalse($container->getParameter('precognition.allow_all_routes'));
        self::assertTrue($container->hasDefinition(PrecognitionActivationListener::class));
    }

    public function testLoadsAllowAllRoutesConfiguration(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $extension = (new PrecognitionBundle())->getContainerExtension();

        self::assertNotNull($extension);
        $extension->load([['allow_all_routes' => true]], $container);

        self::assertTrue($container->getParameter('precognition.allow_all_routes'));
    }

    public function testRejectsInvalidDirectConfiguration(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $instanceof = [];
        $configurator = new ContainerConfigurator($container, $loader, $instanceof, __FILE__, __FILE__);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "precognition.allow_all_routes" option must be a boolean.');

        (new PrecognitionBundle())->loadExtension(
            ['allow_all_routes' => 'yes'],
            $configurator,
            $container
        );
    }
}
