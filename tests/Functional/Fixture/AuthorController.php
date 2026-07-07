<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional\Fixture;

use FundraisingBox\Precognition\Attribute\Precognitive;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class AuthorController
{
    public function __construct(
        private ControllerInvocationTracker $tracker,
    ) {
    }

    #[Route('/authors', methods: ['POST'])]
    #[Precognitive]
    public function create(#[MapRequestPayload] Author $author): Response
    {
        $this->tracker->record();

        return new JsonResponse(['firstName' => $author->firstName], Response::HTTP_CREATED);
    }
}
