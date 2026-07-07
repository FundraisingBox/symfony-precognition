<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional\Fixture;

use FundraisingBox\Precognition\PrecognitionBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function sys_get_temp_dir;

/**
 * Minimal application kernel exercising the bundle end to end with
 * attribute-routed controller fixtures.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<\Symfony\Component\HttpKernel\Bundle\BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new PrecognitionBundle();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/precognition-bundle-test/cache/' . $this->getEnvironment();
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/precognition-bundle-test/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'test',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'php_errors'            => ['log' => false],
            'validation'            => ['enable_attributes' => true],
            'form'                  => true,
            'session'               => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection'       => true,
            'serializer'            => ['enabled' => true],
            'property_access'       => true,
            'router'                => ['utf8' => true],
        ]);

        $services = $container->services();

        $services->set(ControllerInvocationTracker::class)->public();

        $services->set(UserController::class)
            ->public()
            ->args([service(ControllerInvocationTracker::class)])
            ->tag('controller.service_arguments');

        $services->set(AuthorController::class)
            ->public()
            ->args([service(ControllerInvocationTracker::class)])
            ->tag('controller.service_arguments');

        $services->set(TaskController::class)
            ->public()
            ->args([
                service(ControllerInvocationTracker::class),
                service(FormFactoryInterface::class),
                service(CsrfTokenManagerInterface::class),
            ])
            ->tag('controller.service_arguments');

        $services->set(ClassLevelTaskController::class)
            ->public()
            ->args([service(ControllerInvocationTracker::class)])
            ->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(UserController::class, 'attribute');
        $routes->import(AuthorController::class, 'attribute');
        $routes->import(TaskController::class, 'attribute');
        $routes->import(ClassLevelTaskController::class, 'attribute');
    }
}
