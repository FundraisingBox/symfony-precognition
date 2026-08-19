<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Fixtures;

use FundraisingBox\Precognition\Attribute\PrecognitiveForm;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[PrecognitiveForm(TaskType::class)]
final readonly class ClassLevelTaskController
{
    public function __construct(
        private ControllerInvocationTracker $tracker,
    ) {
    }

    #[Route('/task/class-level', methods: ['POST'])]
    public function new(): Response
    {
        $this->tracker->record();

        return new JsonResponse(null, Response::HTTP_CREATED);
    }
}
