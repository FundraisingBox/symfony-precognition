<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Unit\Http;

use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use FundraisingBox\Precognition\Http\PrecognitionRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(PrecognitionRequest::class)]
#[CoversClass(PrecognitionHeaders::class)]
final class PrecognitionRequestTest extends TestCase
{
    public function testIsPrecognitiveWhenHeaderIsTrue(): void
    {
        $request = new Request();
        $request->headers->set(PrecognitionHeaders::PRECOGNITION, 'true');

        $this->assertTrue(PrecognitionRequest::isPrecognitive($request));
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

        $this->assertFalse(PrecognitionRequest::isPrecognitive($request));
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

        $this->assertSame($expected, PrecognitionRequest::validateOnly($request));
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
}
