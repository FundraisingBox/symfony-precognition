<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional\Fixture;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

/**
 * Real controller body: only ever runs for a non-precognitive request, since
 * the short-circuit listener replaces it for precognitive ones.
 */
final readonly class RegistrationController
{
    public function __construct(
        private RegistrationTracker $tracker,
    ) {
    }

    public function __invoke(#[MapRequestPayload] RegistrationInput $input): Response
    {
        $this->tracker->record();

        return new JsonResponse(['username' => $input->username], Response::HTTP_CREATED);
    }
}
