<?php

declare(strict_types=1);

use FundraisingBox\Precognition\EventListener\PrecognitionFormValidationListener;
use FundraisingBox\Precognition\EventListener\PrecognitionResponseListener;
use FundraisingBox\Precognition\EventListener\PrecognitionShortCircuitListener;
use FundraisingBox\Precognition\EventListener\PrecognitionValidationListener;
use FundraisingBox\Precognition\Form\FormErrorViolationMapper;
use FundraisingBox\Precognition\Validation\ViolationPathFilter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\KernelEvents;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set(ViolationPathFilter::class);

    if (interface_exists(FormFactoryInterface::class)) {
        $services->set(FormErrorViolationMapper::class);

        // After RequestPayloadValueResolver (0), before the generic
        // precognition short-circuit (-64). This lets annotated form
        // controllers validate without running their controller bodies.
        $services->set(PrecognitionFormValidationListener::class)
            ->args([service(FormFactoryInterface::class), service(FormErrorViolationMapper::class)])
            ->tag('kernel.event_listener', [
                'event'    => KernelEvents::CONTROLLER_ARGUMENTS,
                'method'   => 'onKernelControllerArguments',
                'priority' => -32,
            ]);
    }

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
