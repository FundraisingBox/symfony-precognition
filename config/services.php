<?php

declare(strict_types=1);

use FundraisingBox\Precognition\EventListener\PrecognitionResponseListener;
use FundraisingBox\Precognition\EventListener\PrecognitionShortCircuitListener;
use FundraisingBox\Precognition\EventListener\PrecognitionValidationListener;
use FundraisingBox\Precognition\Validation\ViolationPathFilter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelEvents;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set(ViolationPathFilter::class);

    // After RequestPayloadValueResolver (kernel.controller_arguments, priority 0)
    // has validated the payload, so reaching this listener means validation passed.
    $services->set(PrecognitionShortCircuitListener::class)
        ->tag('kernel.event_listener', [
            'event'    => KernelEvents::CONTROLLER_ARGUMENTS,
            'method'   => 'onKernelControllerArguments',
            'priority' => -64,
        ]);

    // Before the app's own 422 renderer (typically priority 10 or lower), so the
    // violation list is filtered before it is rendered.
    $services->set(PrecognitionValidationListener::class)
        ->args([service(ViolationPathFilter::class)])
        ->tag('kernel.event_listener', [
            'event'    => KernelEvents::EXCEPTION,
            'method'   => 'onKernelException',
            'priority' => 20,
        ]);

    $services->set(PrecognitionResponseListener::class)
        ->tag('kernel.event_listener', [
            'event'  => KernelEvents::RESPONSE,
            'method' => 'onKernelResponse',
        ]);
};
