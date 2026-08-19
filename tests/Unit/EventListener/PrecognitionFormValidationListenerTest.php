<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Unit\EventListener;

use FundraisingBox\Precognition\Attribute\PrecognitiveForm;
use FundraisingBox\Precognition\EventListener\PrecognitionFormValidationListener;
use FundraisingBox\Precognition\Form\FormErrorViolationMapper;
use FundraisingBox\Precognition\Http\PrecognitionContext;
use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use FundraisingBox\Precognition\Tests\Fixtures\ControllerInvocationTracker;
use FundraisingBox\Precognition\Tests\Fixtures\TaskController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

#[CoversClass(PrecognitionFormValidationListener::class)]
#[UsesClass(PrecognitiveForm::class)]
#[UsesClass(FormErrorViolationMapper::class)]
#[UsesClass(PrecognitionContext::class)]
final class PrecognitionFormValidationListenerTest extends TestCase
{
    private FormFactoryInterface $formFactory;
    private PrecognitionContext $precognitionContext;
    private ControllerInvocationTracker $tracker;
    private TaskController $controller;

    protected function setUp(): void
    {
        $csrfTokenManager = new CsrfTokenManager();
        $this->formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrfTokenManager))
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()))
            ->getFormFactory();
        $this->precognitionContext = new PrecognitionContext(new RequestStack());
        $this->tracker = new ControllerInvocationTracker();
        $this->controller = new TaskController($this->tracker, $this->formFactory, $csrfTokenManager);
    }

    public function testIgnoresSubRequests(): void
    {
        $controller = [$this->controller, 'new'];
        $event = $this->dispatch(
            $controller,
            $this->activePrecognitiveRequest(),
            HttpKernelInterface::SUB_REQUEST
        );

        self::assertSame($controller, $event->getController());
        self::assertSame(0, $this->tracker->count());
    }

    public function testIgnoresInactiveRequests(): void
    {
        $controller = [$this->controller, 'new'];
        $event = $this->dispatch($controller, new Request());

        self::assertSame($controller, $event->getController());
        self::assertSame(0, $this->tracker->count());
    }

    public function testIgnoresControllersWithoutPrecognitiveFormAttribute(): void
    {
        $controller = [$this->controller, 'unannotated'];
        $event = $this->dispatch($controller, $this->activePrecognitiveRequest());

        self::assertSame($controller, $event->getController());
        self::assertSame(0, $this->tracker->count());
    }

    public function testAcceptsValidForm(): void
    {
        $request = $this->activePrecognitiveRequest();
        $request->request->set('task', [
            'task'    => 'Write tests',
            'dueDate' => '2026-08-19',
        ]);

        $controller = [$this->controller, 'new'];
        $event = $this->dispatch($controller, $request);

        self::assertSame($controller, $event->getController());
        self::assertSame(0, $this->tracker->count());
    }

    public function testThrowsValidationExceptionForInvalidForm(): void
    {
        try {
            $this->dispatch([$this->controller, 'new'], $this->activePrecognitiveRequest());
            self::fail('Expected invalid form submission to throw.');
        } catch (HttpException $exception) {
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
            self::assertInstanceOf(ValidationFailedException::class, $exception->getPrevious());
            self::assertSame('task', $exception->getPrevious()->getViolations()->get(0)->getPropertyPath());
            self::assertSame(0, $this->tracker->count());
        }
    }

    private function dispatch(
        callable $controller,
        Request $request,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): ControllerArgumentsEvent {
        $event = new ControllerArgumentsEvent(
            $this->createKernelStub(),
            $controller,
            [],
            $request,
            $requestType
        );

        (new PrecognitionFormValidationListener(
            $this->formFactory,
            new FormErrorViolationMapper(),
            $this->precognitionContext,
        ))->onKernelControllerArguments($event);

        return $event;
    }

    private function activePrecognitiveRequest(): Request
    {
        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);
        $this->precognitionContext->activate($request);

        return $request;
    }

    private function createKernelStub(): HttpKernelInterface
    {
        return new class () implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): never
            {
                throw new RuntimeException('Stub method should not be called');
            }
        };
    }
}
