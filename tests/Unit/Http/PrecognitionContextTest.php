<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Unit\Http;

use FundraisingBox\Precognition\Http\PrecognitionContext;
use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(PrecognitionContext::class)]
#[CoversClass(PrecognitionHeaders::class)]
final class PrecognitionContextTest extends TestCase
{
    public function testIsPrecognitiveWhenHeaderIsTrue(): void
    {
        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, 'true');

        $this->assertTrue($this->createContext()->isPrecognitive($request));
    }

    /**
     * @param non-empty-string|null $headerValue
     */
    #[DataProvider('nonPrecognitiveHeaderProvider')]
    public function testIsNotPrecognitive(?string $headerValue): void
    {
        $request = new Request();
        if (null !== $headerValue) {
            $request->headers->set(PrecognitionHeaders::PRECOGNITION, $headerValue);
        }

        $this->assertFalse($this->createContext()->isPrecognitive($request));
    }

    /**
     * @return iterable<string, array{0: non-empty-string|null}>
     */
    public static function nonPrecognitiveHeaderProvider(): iterable
    {
        yield 'missing header' => [null];
        yield 'false value' => ['false'];
        yield 'uppercase true' => ['TRUE'];
        yield 'arbitrary value' => ['1'];
    }

    public function testIsActiveRequiresHeaderAndActivationStamp(): void
    {
        $context = $this->createContext();
        $request = new Request();

        $context->activate($request);
        $this->assertFalse($context->isActive($request));

        $request->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);
        $this->assertTrue($context->isActive($request));
    }

    public function testIsActiveRequiresActivationStamp(): void
    {
        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);

        $this->assertFalse($this->createContext()->isActive($request));
    }

    public function testActivateSetsRequestAttribute(): void
    {
        $request = new Request();

        $this->createContext()->activate($request);

        $this->assertTrue($request->attributes->get(PrecognitionContext::ACTIVE_ATTRIBUTE));
    }

    public function testFallsBackToCurrentRequest(): void
    {
        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, PrecognitionHeaders::TRUE_VALUE);
        $request->headers->set(PrecognitionHeaders::VALIDATE_ONLY, 'firstName');

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $context = new PrecognitionContext($requestStack);
        $context->activate($request);

        $this->assertTrue($context->isPrecognitive());
        $this->assertTrue($context->isActive());
        $this->assertSame(['firstName'], $context->validateOnly());
    }

    public function testNoCurrentRequestReturnsEmptyDefaults(): void
    {
        $context = $this->createContext();

        $this->assertFalse($context->isPrecognitive());
        $this->assertFalse($context->isActive());
        $this->assertSame([], $context->validateOnly());
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('validateOnlyProvider')]
    public function testValidateOnlyParsesHeader(?string $headerValue, array $expected): void
    {
        $request = new Request();
        if (null !== $headerValue) {
            $request->headers->set(PrecognitionHeaders::VALIDATE_ONLY, $headerValue);
        }

        $this->assertSame($expected, $this->createContext()->validateOnly($request));
    }

    /**
     * @return iterable<string, array{0: string|null, 1: list<string>}>
     */
    public static function validateOnlyProvider(): iterable
    {
        yield 'missing header' => [null, []];
        yield 'single field' => ['username', ['username']];
        yield 'multiple fields' => ['username,email', ['username', 'email']];
        yield 'trims whitespace' => [' username , email ', ['username', 'email']];
        yield 'drops empty entries' => ['username,,email,', ['username', 'email']];
        yield 'only commas' => [',,', []];
        yield 'empty string' => ['', []];
    }

    private function createContext(): PrecognitionContext
    {
        return new PrecognitionContext(new RequestStack());
    }
}
