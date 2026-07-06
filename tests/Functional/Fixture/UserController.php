<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional\Fixture;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Real controller bodies: they only run for non-precognitive requests, since
 * the short-circuit listener replaces them for precognitive ones.
 */
final readonly class UserController
{
    public function __construct(
        private ControllerInvocationTracker $tracker,
    ) {
    }

    #[Route('/user', methods: ['POST'])]
    public function create(#[MapRequestPayload] UserDto $userDto): Response
    {
        $this->tracker->record();

        return new JsonResponse(['firstName' => $userDto->firstName], Response::HTTP_CREATED);
    }

    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(#[MapQueryString] UserDto $userDto): Response
    {
        $this->tracker->record();

        return new JsonResponse(['firstName' => $userDto->firstName]);
    }

    #[Route('/user/picture', methods: ['PUT'])]
    public function changePicture(
        #[MapUploadedFile([
            new Assert\File(mimeTypes: ['image/png', 'image/jpeg']),
        ])]
        UploadedFile $picture,
    ): Response {
        $this->tracker->record();

        return new JsonResponse(['filename' => $picture->getClientOriginalName()]);
    }
}
