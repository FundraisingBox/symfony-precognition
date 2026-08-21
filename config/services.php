<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use FundraisingBox\Precognition\EventListener\PrecognitionActivationListener;
use FundraisingBox\Precognition\EventListener\PrecognitionEventPriority;
use FundraisingBox\Precognition\EventListener\PrecognitionFormValidationListener;
use FundraisingBox\Precognition\EventListener\PrecognitionResponseListener;
use FundraisingBox\Precognition\EventListener\PrecognitionShortCircuitListener;
use FundraisingBox\Precognition\EventListener\PrecognitionValidationListener;
use FundraisingBox\Precognition\Form\FormErrorViolationMapper;
use FundraisingBox\Precognition\Http\PrecognitionContext;
use FundraisingBox\Precognition\Validation\ViolationPathFilter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set(PrecognitionContext::class)
        ->args([service(RequestStack::class)]);

    $services->set(PrecognitionActivationListener::class)
        ->args([service(PrecognitionContext::class), '%precognition.allow_all_routes%'])
        ->tag('kernel.event_listener', [
            'event'    => KernelEvents::CONTROLLER,
            'method'   => 'onKernelController',
            'priority' => PrecognitionEventPriority::DEFAULT->value,
        ]);

    $services->set(ViolationPathFilter::class);

    if (interface_exists(FormFactoryInterface::class)) {
        $services->set(FormErrorViolationMapper::class);

        // Validate annotated forms before the generic precognition
        // short-circuit replaces the controller.
        $services->set(PrecognitionFormValidationListener::class)
            ->args([
                service(FormFactoryInterface::class),
                service(FormErrorViolationMapper::class),
                service(PrecognitionContext::class),
            ])
            ->tag('kernel.event_listener', [
                'event'    => KernelEvents::CONTROLLER_ARGUMENTS,
                'method'   => 'onKernelControllerArguments',
                'priority' => PrecognitionEventPriority::FORM_VALIDATION->value,
            ]);
    }

    // Replacing the controller does not stop event propagation, so Symfony's
    // payload resolver still maps and validates the arguments afterward.
    $services->set(PrecognitionShortCircuitListener::class)
        ->args([service(PrecognitionContext::class)])
        ->tag('kernel.event_listener', [
            'event'    => KernelEvents::CONTROLLER_ARGUMENTS,
            'method'   => 'onKernelControllerArguments',
            'priority' => PrecognitionEventPriority::SHORT_CIRCUIT->value,
        ]);

    // Before the app's own validation renderer (typically priority 10 or lower),
    // so the violation list is filtered before it is rendered.
    $services->set(PrecognitionValidationListener::class)
        ->args([service(ViolationPathFilter::class), service(PrecognitionContext::class)])
        ->tag('kernel.event_listener', [
            'event'    => KernelEvents::EXCEPTION,
            'method'   => 'onKernelException',
            'priority' => PrecognitionEventPriority::VALIDATION->value,
        ]);

    $services->set(PrecognitionResponseListener::class)
        ->args([service(PrecognitionContext::class)])
        ->tag('kernel.event_listener', [
            'event'    => KernelEvents::RESPONSE,
            'method'   => 'onKernelResponse',
            'priority' => PrecognitionEventPriority::DEFAULT->value,
        ]);
};
