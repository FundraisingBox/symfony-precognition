<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional;

use FundraisingBox\Precognition\EventListener\PrecognitionActivationListener;
use FundraisingBox\Precognition\EventListener\PrecognitionEventPriority;
use FundraisingBox\Precognition\EventListener\PrecognitionFormValidationListener;
use FundraisingBox\Precognition\EventListener\PrecognitionResponseListener;
use FundraisingBox\Precognition\EventListener\PrecognitionShortCircuitListener;
use FundraisingBox\Precognition\EventListener\PrecognitionValidationListener;
use FundraisingBox\Precognition\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(PrecognitionEventPriority::class)]
final class PrecognitionEventPriorityTest extends KernelTestCase
{
    private const CONTROLLER_ATTRIBUTES_LISTENER = 'Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener';

    public function testListenersRemainOrderedAgainstSymfonyFrameworkListeners(): void
    {
        self::bootKernel();
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $activation = $this->listenerPriority(
            $dispatcher,
            KernelEvents::CONTROLLER,
            PrecognitionActivationListener::class,
            'onKernelController'
        );
        $formValidation = $this->listenerPriority(
            $dispatcher,
            KernelEvents::CONTROLLER_ARGUMENTS,
            PrecognitionFormValidationListener::class,
            'onKernelControllerArguments'
        );
        $shortCircuit = $this->listenerPriority(
            $dispatcher,
            KernelEvents::CONTROLLER_ARGUMENTS,
            PrecognitionShortCircuitListener::class,
            'onKernelControllerArguments'
        );
        $validation = $this->listenerPriority(
            $dispatcher,
            KernelEvents::EXCEPTION,
            PrecognitionValidationListener::class,
            'onKernelException'
        );
        $responseHeaders = $this->listenerPriority(
            $dispatcher,
            KernelEvents::RESPONSE,
            PrecognitionResponseListener::class,
            'onKernelResponse'
        );

        self::assertSame(PrecognitionEventPriority::DEFAULT->value, $activation);
        self::assertSame(PrecognitionEventPriority::FORM_VALIDATION->value, $formValidation);
        self::assertSame(PrecognitionEventPriority::SHORT_CIRCUIT->value, $shortCircuit);
        self::assertSame(PrecognitionEventPriority::VALIDATION->value, $validation);
        self::assertSame(PrecognitionEventPriority::DEFAULT->value, $responseHeaders);
        self::assertGreaterThan($shortCircuit, $formValidation);

        $requestPayload = $this->listenerPriority(
            $dispatcher,
            KernelEvents::CONTROLLER_ARGUMENTS,
            RequestPayloadValueResolver::class,
            'onKernelControllerArguments'
        );

        if (class_exists(self::CONTROLLER_ATTRIBUTES_LISTENER)) {
            $controllerAttributes = self::CONTROLLER_ATTRIBUTES_LISTENER;

            $controller = $this->listenerPriority($dispatcher, KernelEvents::CONTROLLER, $controllerAttributes, 'beforeController');
            $controllerArguments = $this->listenerPriority($dispatcher, KernelEvents::CONTROLLER_ARGUMENTS, $controllerAttributes, 'beforeController');
            $exception = $this->listenerPriority($dispatcher, KernelEvents::EXCEPTION, $controllerAttributes, 'afterController');
            $response = $this->listenerPriority($dispatcher, KernelEvents::RESPONSE, $controllerAttributes, 'afterController');

            self::assertGreaterThan($controller, $activation);
            self::assertGreaterThan($controllerArguments, $shortCircuit);
            self::assertGreaterThan($requestPayload, $controllerArguments);
            self::assertGreaterThan($validation, $exception);
            self::assertGreaterThan($responseHeaders, $response);
        } else {
            self::assertGreaterThan($formValidation, $requestPayload);
        }

        $exceptionLogging = $this->listenerPriority($dispatcher, KernelEvents::EXCEPTION, ErrorListener::class, 'logKernelException');
        $exceptionRendering = $this->listenerPriority($dispatcher, KernelEvents::EXCEPTION, ErrorListener::class, 'onKernelException');
        $responseErrorHandling = $this->listenerPriority($dispatcher, KernelEvents::RESPONSE, ErrorListener::class, 'removeCspHeader');

        self::assertGreaterThan($exceptionLogging, $validation);
        self::assertGreaterThan($exceptionRendering, $exceptionLogging);
        self::assertGreaterThan($responseErrorHandling, $responseHeaders);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    private function listenerPriority(
        EventDispatcherInterface $dispatcher,
        string $event,
        string $listenerClass,
        string $method,
    ): int {
        foreach ($dispatcher->getListeners($event) as $listener) {
            if (
                !is_array($listener)
                || !is_object($listener[0])
                || !$listener[0] instanceof $listenerClass
                || $method !== $listener[1]
            ) {
                continue;
            }

            $registeredListener = [$listener[0], $method];
            self::assertIsCallable($registeredListener);

            $priority = $dispatcher->getListenerPriority($event, $registeredListener);
            self::assertNotNull($priority);

            return $priority;
        }

        self::fail(sprintf('Listener %s::%s is not registered for %s.', $listenerClass, $method, $event));
    }
}
