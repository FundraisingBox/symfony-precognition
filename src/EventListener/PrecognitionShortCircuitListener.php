<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\EventListener;

use FundraisingBox\Precognition\Http\PrecognitionRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;

/**
 * Skips the controller body for precognitive requests.
 *
 * Runs at low priority on `kernel.controller_arguments`, i.e. after argument
 * resolution. Symfony's request-payload validation has, by then, already run:
 * custom value resolvers validate inside `argumentResolver->getArguments()`
 * (before this event) and `RequestPayloadValueResolver` validates in its own
 * `kernel.controller_arguments` subscriber (default priority). In both cases a
 * validation failure throws before this listener is reached and flows to
 * `kernel.exception`. This listener is therefore only reached once validation
 * has passed, so it replaces the controller with a no-op returning
 * `204 No Content`.
 */
final readonly class PrecognitionShortCircuitListener
{
    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!PrecognitionRequest::isPrecognitive($event->getRequest())) {
            return;
        }

        // Variadic: the kernel invokes the controller with the resolved arguments.
        $event->setController(
            static fn (mixed ...$arguments): Response => new Response(null, Response::HTTP_NO_CONTENT)
        );
    }
}
