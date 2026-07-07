<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\EventListener;

use FundraisingBox\Precognition\Attribute\PrecognitiveForm;
use FundraisingBox\Precognition\Form\FormErrorViolationMapper;
use FundraisingBox\Precognition\Http\PrecognitionRequest;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final readonly class PrecognitionFormValidationListener
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private FormErrorViolationMapper $formErrorViolationMapper,
    ) {
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!PrecognitionRequest::isPrecognitive($request)) {
            return;
        }

        $attribute = $event->getAttributes(PrecognitiveForm::class)[0] ?? null;
        if (!$attribute instanceof PrecognitiveForm) {
            return;
        }

        $form = $this->formFactory->create($attribute->type, options: ['csrf_protection' => false]);
        $form->submit($request->getPayload()->all($form->getName()), clearMissing: true);

        if ($form->isValid()) {
            return;
        }

        $violations = $this->formErrorViolationMapper->map($form);

        throw new HttpException(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Validation failed',
            new ValidationFailedException($form->getData(), $violations)
        );
    }
}
