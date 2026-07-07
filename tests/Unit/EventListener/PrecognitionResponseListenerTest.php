<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Unit\EventListener;

use FundraisingBox\Precognition\EventListener\PrecognitionResponseListener;
use FundraisingBox\Precognition\Http\PrecognitionContext;
use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(PrecognitionResponseListener::class)]
final class PrecognitionResponseListenerTest extends TestCase
{
    public function testAddsSuccessHeaderOnNoContentResponse(): void
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $this->dispatch($this->precognitiveRequest(), $response, HttpKernelInterface::MAIN_REQUEST);

        $this->assertSame(PrecognitionHeaders::TRUE_VALUE, $response->headers->get(PrecognitionHeaders::PRECOGNITION));
        $this->assertSame(PrecognitionHeaders::TRUE_VALUE, $response->headers->get(PrecognitionHeaders::SUCCESS));
        $this->assertSame(PrecognitionHeaders::PRECOGNITION, $response->headers->get(PrecognitionHeaders::VARY));
    }

    public function testDoesNotAddSuccessHeaderOnNonNoContentResponse(): void
    {
        $response = new Response('{}', Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->dispatch($this->precognitiveRequest(), $response, HttpKernelInterface::MAIN_REQUEST);

        $this->assertSame(PrecognitionHeaders::TRUE_VALUE, $response->headers->get(PrecognitionHeaders::PRECOGNITION));
        $this->assertFalse($response->headers->has(PrecognitionHeaders::SUCCESS));
        $this->assertSame(PrecognitionHeaders::PRECOGNITION, $response->headers->get(PrecognitionHeaders::VARY));
    }

    public function testAppendsToExistingVaryHeader(): void
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->set(PrecognitionHeaders::VARY, 'Accept');

        $this->dispatch($this->precognitiveRequest(), $response, HttpKernelInterface::MAIN_REQUEST);

        $this->assertSame(['Accept', PrecognitionHeaders::PRECOGNITION], $response->headers->all(PrecognitionHeaders::VARY));
    }

    public function testLeavesResponseUntouchedForNonPrecognitiveRequest(): void
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $this->dispatch(new Request(), $response, HttpKernelInterface::MAIN_REQUEST);

        $this->assertFalse($response->headers->has(PrecognitionHeaders::PRECOGNITION));
        $this->assertFalse($response->headers->has(PrecognitionHeaders::SUCCESS));
        $this->assertFalse($response->headers->has(PrecognitionHeaders::VARY));
    }

    public function testIgnoresSubRequests(): void
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $this->dispatch($this->precognitiveRequest(), $response, HttpKernelInterface::SUB_REQUEST);

        $this->assertFalse($response->headers->has(PrecognitionHeaders::PRECOGNITION));
    }

    private function dispatch(Request $request, Response $response, int $requestType): void
    {
        $event = new ResponseEvent($this->createKernelStub(), $request, $requestType, $response);
        (new PrecognitionResponseListener(new PrecognitionContext(new RequestStack())))->onKernelResponse($event);
    }

    private function precognitiveRequest(): Request
    {
        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);

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
