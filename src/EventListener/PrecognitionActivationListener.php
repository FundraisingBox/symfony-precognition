<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\EventListener;

use FundraisingBox\Precognition\Attribute\Precognitive;
use FundraisingBox\Precognition\Attribute\PrecognitiveForm;
use FundraisingBox\Precognition\Http\PrecognitionContext;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

final readonly class PrecognitionActivationListener
{
    public function __construct(
        private PrecognitionContext $precognitionContext,
        private bool $allowAllRoutes,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->precognitionContext->isPrecognitive($request)) {
            return;
        }

        if (
            $this->allowAllRoutes
            || [] !== $event->getAttributes(Precognitive::class)
            || [] !== $event->getAttributes(PrecognitiveForm::class)
        ) {
            $this->precognitionContext->activate($request);
        }
    }
}
