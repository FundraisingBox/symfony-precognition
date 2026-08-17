<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Unit\EventListener;

use Exception;
use FundraisingBox\Precognition\EventListener\PrecognitionValidationListener;
use FundraisingBox\Precognition\Http\PrecognitionContext;
use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use FundraisingBox\Precognition\Validation\ViolationPathFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

#[CoversClass(PrecognitionValidationListener::class)]
final class PrecognitionValidationListenerTest extends TestCase
{
    public function testReturns204AndStopsPropagationWhenRequestedFieldsAreValid(): void
    {
        $violations = $this->violationList('email');
        $event = $this->dispatch($this->validationException($violations), $this->precognitiveRequest('username'));

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertTrue($event->isPropagationStopped());
        $this->assertCount(0, $violations);
    }

    public function testFiltersViolationsInPlaceWhenRequestedFieldsAreInvalid(): void
    {
        $violations = $this->violationList('username', 'email');
        $event = $this->dispatch($this->validationException($violations), $this->precognitiveRequest('username'));

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
        $this->assertCount(1, $violations);
        $this->assertSame('username', $violations->get(0)->getPropertyPath());
    }

    public function testResolvesWrappedValidationExceptionAndReturns204WhenAllFiltered(): void
    {
        $violations = $this->violationList('email');
        $wrapped = new HttpException(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Validation failed',
            $this->validationException($violations)
        );

        $event = $this->dispatch($wrapped, $this->precognitiveRequest('username'));

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertCount(0, $violations);
    }

    public function testResolvesWrappedValidationExceptionAndFiltersInPlace(): void
    {
        $violations = $this->violationList('username', 'email');
        $wrapped = new HttpException(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Validation failed',
            $this->validationException($violations)
        );

        $event = $this->dispatch($wrapped, $this->precognitiveRequest('username'));

        $this->assertNull($event->getResponse());
        $this->assertCount(1, $violations);
        $this->assertSame('username', $violations->get(0)->getPropertyPath());
    }

    public function testLeavesWrappedValidationHttpExceptionStatusUntouchedWithoutValidateOnlyHeader(): void
    {
        $violations = $this->violationList('firstName');
        $wrapped = new HttpException(
            Response::HTTP_NOT_FOUND,
            'Validation failed',
            $this->validationException($violations)
        );

        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);
        $this->context()->activate($request);

        $event = $this->dispatch($wrapped, $request);

        $this->assertSame($wrapped, $event->getThrowable());
        $this->assertNull($event->getResponse());
    }

    public function testLeavesWrappedValidationHttpExceptionStatus422Untouched(): void
    {
        $previous = $this->validationException($this->violationList('firstName'));
        $wrapped = new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Validation failed', $previous);

        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);
        $this->context()->activate($request);

        $event = $this->dispatch($wrapped, $request);

        $this->assertSame($wrapped, $event->getThrowable());
        $this->assertNull($event->getResponse());
    }

    public function testResolvesDeeplyNestedValidationException(): void
    {
        $violations = $this->violationList('email');
        $inner = $this->validationException($violations);
        $middle = new RuntimeException('intermediate', 0, $inner);
        $wrapped = new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'boom', $middle);

        $event = $this->dispatch($wrapped, $this->precognitiveRequest('username'));

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testIgnoresHttpExceptionWithNonValidationPrevious(): void
    {
        $wrapped = new HttpException(
            Response::HTTP_BAD_REQUEST,
            'Bad request',
            new RuntimeException('not a validation failure')
        );

        $event = $this->dispatch($wrapped, $this->precognitiveRequest('username'));

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testIgnoresNonPrecognitiveRequest(): void
    {
        $exception = $this->validationException(new ConstraintViolationList());
        $request = new Request();
        $request->headers->set(PrecognitionHeaders::VALIDATE_ONLY, 'username');

        $event = $this->dispatch($exception, $request);

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testIgnoresWhenValidateOnlyHeaderIsAbsent(): void
    {
        $violations = $this->violationList('email');
        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);
        $this->context()->activate($request);

        $event = $this->dispatch($this->validationException($violations), $request);

        $this->assertNull($event->getResponse());
        $this->assertCount(1, $violations);
    }

    public function testIgnoresNonValidationException(): void
    {
        $event = $this->dispatch(new Exception('boom'), $this->precognitiveRequest('username'));

        $this->assertNull($event->getResponse());
        $this->assertFalse($event->isPropagationStopped());
    }

    private function violationList(string ...$propertyPaths): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();
        foreach ($propertyPaths as $propertyPath) {
            $violations->add(new ConstraintViolation('message', null, [], null, $propertyPath, null));
        }

        return $violations;
    }

    private function validationException(ConstraintViolationListInterface $violations): ValidationFailedException
    {
        return new ValidationFailedException(null, $violations);
    }

    private function dispatch(Throwable $exception, Request $request): ExceptionEvent
    {
        $event = new ExceptionEvent(
            $this->createKernelStub(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        (new PrecognitionValidationListener(new ViolationPathFilter(), $this->context()))->onKernelException($event);

        return $event;
    }

    private function precognitiveRequest(string $validateOnly): Request
    {
        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);
        $request->headers->set(PrecognitionHeaders::VALIDATE_ONLY, $validateOnly);
        $this->context()->activate($request);

        return $request;
    }

    private function context(): PrecognitionContext
    {
        return new PrecognitionContext(new RequestStack());
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
