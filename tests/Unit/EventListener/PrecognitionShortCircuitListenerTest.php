<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Unit\EventListener;

use FundraisingBox\Precognition\EventListener\PrecognitionShortCircuitListener;
use FundraisingBox\Precognition\Http\PrecognitionContext;
use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(PrecognitionShortCircuitListener::class)]
#[UsesClass(PrecognitionContext::class)]
final class PrecognitionShortCircuitListenerTest extends TestCase
{
    public function testReplacesControllerWithNoContentForPrecognitiveRequest(): void
    {
        $originalController = static fn (): Response => throw new RuntimeException('Original controller must not run');
        $event = $this->createEvent($originalController, $this->activePrecognitiveRequest(), HttpKernelInterface::MAIN_REQUEST);

        $this->listener()->onKernelControllerArguments($event);

        $controller = $event->getController();
        $response = $controller('arg1', 'arg2');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testLeavesControllerIntactForNonPrecognitiveRequest(): void
    {
        $originalController = static fn (): Response => new Response('original');
        $event = $this->createEvent($originalController, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $this->listener()->onKernelControllerArguments($event);

        $this->assertSame($originalController, $event->getController());
    }

    public function testLeavesControllerIntactForInactivePrecognitiveRequest(): void
    {
        $originalController = static fn (): Response => new Response('original');
        $event = $this->createEvent($originalController, $this->precognitiveRequest(), HttpKernelInterface::MAIN_REQUEST);

        $this->listener()->onKernelControllerArguments($event);

        $this->assertSame($originalController, $event->getController());
    }

    public function testIgnoresSubRequests(): void
    {
        $originalController = static fn (): Response => new Response('original');
        $event = $this->createEvent($originalController, $this->activePrecognitiveRequest(), HttpKernelInterface::SUB_REQUEST);

        $this->listener()->onKernelControllerArguments($event);

        $this->assertSame($originalController, $event->getController());
    }

    private function precognitiveRequest(): Request
    {
        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);

        return $request;
    }

    private function activePrecognitiveRequest(): Request
    {
        $request = $this->precognitiveRequest();
        $this->context()->activate($request);

        return $request;
    }

    private function listener(): PrecognitionShortCircuitListener
    {
        return new PrecognitionShortCircuitListener($this->context());
    }

    private function context(): PrecognitionContext
    {
        return new PrecognitionContext(new RequestStack());
    }

    private function createEvent(callable $controller, Request $request, int $requestType): ControllerArgumentsEvent
    {
        return new ControllerArgumentsEvent(
            $this->createKernelStub(),
            $controller,
            [],
            $request,
            $requestType
        );
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
