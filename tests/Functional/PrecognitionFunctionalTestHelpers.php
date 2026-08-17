<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional;

use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use FundraisingBox\Precognition\Tests\Functional\Fixture\ControllerInvocationTracker;
use FundraisingBox\Precognition\Tests\Functional\Fixture\TestKernel;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;

trait PrecognitionFunctionalTestHelpers
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @return array<string, string>
     */
    private function precognitive(?string $validateOnly = null): array
    {
        $headers = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_' . PrecognitionHeaders::PRECOGNITION => PrecognitionHeaders::TRUE_VALUE,
        ];
        if (null !== $validateOnly) {
            $headers['HTTP_PRECOGNITION_VALIDATE_ONLY'] = $validateOnly;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function acceptJson(): array
    {
        return ['HTTP_ACCEPT' => 'application/json'];
    }

    /**
     * @return list<string>
     */
    private function violationPropertyPaths(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('violations', $data);
        self::assertIsArray($data['violations']);

        $propertyPaths = [];
        foreach ($data['violations'] as $violation) {
            self::assertIsArray($violation);
            self::assertArrayHasKey('propertyPath', $violation);
            self::assertIsString($violation['propertyPath']);
            $propertyPaths[] = $violation['propertyPath'];
        }

        return $propertyPaths;
    }

    private function assertNoViolations(Response $response): void
    {
        $data = json_decode((string) $response->getContent(), true);
        if (null === $data) {
            return;
        }

        self::assertIsArray($data);
        self::assertArrayNotHasKey('violations', $data);
    }

    private function assertPrecognitionSuccessResponse(Response $response): void
    {
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(PrecognitionHeaders::TRUE_VALUE, $response->headers->get(PrecognitionHeaders::PRECOGNITION));
        self::assertSame(PrecognitionHeaders::TRUE_VALUE, $response->headers->get(PrecognitionHeaders::SUCCESS));
        self::assertContains(PrecognitionHeaders::PRECOGNITION, $response->headers->all(PrecognitionHeaders::VARY));
        self::assertEmpty($response->getContent());
    }

    private function trackerCount(): int
    {
        $tracker = self::getContainer()->get(ControllerInvocationTracker::class);
        self::assertInstanceOf(ControllerInvocationTracker::class, $tracker);

        return $tracker->count();
    }
}
