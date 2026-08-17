<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Unit\EventListener;

use FundraisingBox\Precognition\Attribute\Precognitive;
use FundraisingBox\Precognition\Attribute\PrecognitiveForm;
use FundraisingBox\Precognition\EventListener\PrecognitionActivationListener;
use FundraisingBox\Precognition\Http\PrecognitionContext;
use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use FundraisingBox\Precognition\Tests\Functional\Fixture\TaskType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(PrecognitionActivationListener::class)]
final class PrecognitionActivationListenerTest extends TestCase
{
    public function testIgnoresRequestWithoutPrecognitionHeader(): void
    {
        $request = new Request();

        $this->dispatch([new ActivationTestController(), 'plain'], $request, allowAllRoutes: true);

        $this->assertFalse($this->context()->isActive($request));
    }

    public function testActivatesWhenGlobalModeAllowsAllRoutes(): void
    {
        $request = $this->precognitiveRequest();

        $this->dispatch([new ActivationTestController(), 'plain'], $request, allowAllRoutes: true);

        $this->assertTrue($this->context()->isActive($request));
    }

    public function testActivatesForMethodAttribute(): void
    {
        $request = $this->precognitiveRequest();

        $this->dispatch([new ActivationTestController(), 'methodPrecognitive'], $request);

        $this->assertTrue($this->context()->isActive($request));
    }

    public function testActivatesForClassAttribute(): void
    {
        $request = $this->precognitiveRequest();

        $this->dispatch([new ClassLevelActivationTestController(), 'plain'], $request);

        $this->assertTrue($this->context()->isActive($request));
    }

    public function testPrecognitiveFormAttributeIsAnOptIn(): void
    {
        $request = $this->precognitiveRequest();

        $this->dispatch([new ActivationTestController(), 'formPrecognitive'], $request);

        $this->assertTrue($this->context()->isActive($request));
    }

    public function testLeavesUnannotatedRouteInactiveByDefault(): void
    {
        $request = $this->precognitiveRequest();

        $this->dispatch([new ActivationTestController(), 'plain'], $request);

        $this->assertFalse($this->context()->isActive($request));
    }

    public function testIgnoresSubRequests(): void
    {
        $request = $this->precognitiveRequest();

        $this->dispatch([new ActivationTestController(), 'methodPrecognitive'], $request, requestType: HttpKernelInterface::SUB_REQUEST);

        $this->assertFalse($this->context()->isActive($request));
    }

    private function dispatch(
        callable $controller,
        Request $request,
        bool $allowAllRoutes = false,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): void {
        $event = new ControllerEvent($this->createKernelStub(), $controller, $request, $requestType);

        (new PrecognitionActivationListener($this->context(), $allowAllRoutes))->onKernelController($event);
    }

    private function precognitiveRequest(): Request
    {
        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);

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

final class ActivationTestController
{
    public function plain(): Response
    {
        return new Response();
    }

    #[Precognitive]
    public function methodPrecognitive(): Response
    {
        return new Response();
    }

    #[PrecognitiveForm(TaskType::class)]
    public function formPrecognitive(): Response
    {
        return new Response();
    }
}

#[Precognitive]
final class ClassLevelActivationTestController
{
    public function plain(): Response
    {
        return new Response();
    }
}
