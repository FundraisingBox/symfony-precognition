<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Fixtures;

use FundraisingBox\Precognition\Attribute\PrecognitiveForm;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Mirrors the Symfony Forms documentation's createForm()/handleRequest() flow.
 * The fixture returns JSON instead of rendering Twig to avoid a Twig test
 * dependency.
 */
final readonly class TaskController
{
    public function __construct(
        private ControllerInvocationTracker $tracker,
        private FormFactoryInterface $formFactory,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/task/new', methods: ['POST'])]
    #[PrecognitiveForm(TaskType::class)]
    public function new(Request $request): Response
    {
        $task = new Task();
        $form = $this->formFactory->create(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->tracker->record();

            return new JsonResponse(['task' => $task->getTask()], Response::HTTP_CREATED);
        }

        return new JsonResponse(['valid' => false], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/task/unannotated', methods: ['POST'])]
    public function unannotated(Request $request): Response
    {
        $task = new Task();
        $form = $this->formFactory->create(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->tracker->record();

            return new JsonResponse(['task' => $task->getTask()], Response::HTTP_CREATED);
        }

        return new JsonResponse(['valid' => false], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/task/token', methods: ['GET'])]
    public function token(): Response
    {
        return new JsonResponse(['token' => $this->csrfTokenManager->getToken('task')->getValue()]);
    }
}
