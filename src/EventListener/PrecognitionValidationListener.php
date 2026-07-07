<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\EventListener;

use FundraisingBox\Precognition\Http\PrecognitionContext;
use FundraisingBox\Precognition\Validation\ViolationPathFilter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

use function count;

/**
 * Implements precognitive validation response handling.
 *
 * Runs before the app's validation error renderer. It normalises precognitive
 * validation failures to Laravel's `422` protocol status, then applies
 * `Precognition-Validate-Only` post-validation filtering when requested. If no
 * selected violations remain the fields are valid -> `204`; otherwise the
 * now-filtered exception falls through to the normal `422` render.
 *
 * Domain-free: it keys off Symfony's standard {@see ValidationFailedException}.
 * That exception is not always the top-level throwable: `#[MapRequestPayload]`
 * (via `RequestPayloadValueResolver`) wraps it in an `HttpException` and exposes
 * it as `previous`, while custom value resolvers may throw it directly. The
 * listener therefore walks the `getPrevious()` chain to find it. Filtering the
 * list in place is enough because the app's validation renderer reads that same
 * `ConstraintViolationListInterface`.
 */
final readonly class PrecognitionValidationListener
{
    public function __construct(
        private ViolationPathFilter $violationPathFilter,
        private PrecognitionContext $precognitionContext,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->precognitionContext->isActive($request)) {
            return;
        }

        $exception = $this->resolveValidationException($event->getThrowable());
        if (null === $exception) {
            return;
        }

        $this->normaliseValidationFailedStatus($event, $exception);

        $requestedFields = $this->precognitionContext->validateOnly($request);
        if ([] === $requestedFields) {
            return;
        }

        $violations = $exception->getViolations();
        foreach ($this->violationPathFilter->nonMatchingOffsets($violations, $requestedFields) as $offset) {
            $violations->remove($offset);
        }

        if (0 === count($violations)) {
            // setResponse() stops propagation.
            $event->setResponse(new Response(null, Response::HTTP_NO_CONTENT));
            // The 204 is intentional; without this the kernel rewrites non-error
            // responses produced from an exception to 500.
            $event->allowCustomResponseCode();
        }
    }

    private function normaliseValidationFailedStatus(ExceptionEvent $event, ValidationFailedException $exception): void
    {
        $throwable = $event->getThrowable();
        if (!$throwable instanceof HttpExceptionInterface) {
            return;
        }

        if (Response::HTTP_UNPROCESSABLE_ENTITY === $throwable->getStatusCode()) {
            return;
        }

        $event->setThrowable(new HttpException(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $throwable->getMessage(),
            $exception
        ));
    }

    /**
     * Finds the standard validation exception in the throwable chain, whether
     * it is the thrown exception itself (custom resolvers) or wrapped as a
     * previous exception (`#[MapRequestPayload]`).
     */
    private function resolveValidationException(Throwable $throwable): ?ValidationFailedException
    {
        $current = $throwable;

        while (null !== $current) {
            if ($current instanceof ValidationFailedException) {
                return $current;
            }

            $current = $current->getPrevious();
        }

        return null;
    }
}
