<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional\Fixture;

use FundraisingBox\Precognition\PrecognitionBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function sys_get_temp_dir;

/**
 * Minimal application kernel exercising the bundle end to end: FrameworkBundle,
 * the PrecognitionBundle and a single POST /users endpoint using
 * #[MapRequestPayload].
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
            'serializer'            => ['enabled' => true],
            'property_access'       => true,
            'router'                => ['utf8' => true],
        ]);

        $services = $container->services();

        $services->set(RegistrationTracker::class)->public();

        $services->set(RegistrationController::class)
            ->public()
            ->args([service(RegistrationTracker::class)])
            ->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('register', '/users')
            ->controller(RegistrationController::class)
            ->methods(['POST']);
    }
}
