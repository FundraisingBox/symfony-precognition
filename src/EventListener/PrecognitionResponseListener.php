<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\EventListener;

use FundraisingBox\Precognition\Http\PrecognitionContext;
use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Tags every precognitive response with the protocol headers.
 *
 * Applies to both the short-circuit `204` and the validation `422` so clients
 * can always detect that precognition was honoured.
 */
final readonly class PrecognitionResponseListener
{
    public function __construct(
        private PrecognitionContext $precognitionContext,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->precognitionContext->isActive($event->getRequest())) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);
        $response->headers->set(PrecognitionHeaders::VARY, PrecognitionHeaders::PRECOGNITION, false);

        if (Response::HTTP_NO_CONTENT === $response->getStatusCode()) {
            $response->headers->set(PrecognitionHeaders::SUCCESS, PrecognitionHeaders::TRUE_VALUE);
        }
    }
}
