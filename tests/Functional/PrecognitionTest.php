<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional;

use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use FundraisingBox\Precognition\Tests\Functional\Fixture\RegistrationTracker;
use FundraisingBox\Precognition\Tests\Functional\Fixture\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class PrecognitionTest extends WebTestCase
{
    private const string ROUTE = '/users';

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testPrecognitiveValidRequestReturns204AndDoesNotRunController(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', self::ROUTE, $this->validPayload(), $this->precognitive());

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $response = $client->getResponse();
        $this->assertSame(PrecognitionHeaders::TRUE_VALUE, $response->headers->get(PrecognitionHeaders::PRECOGNITION));
        $this->assertSame(PrecognitionHeaders::TRUE_VALUE, $response->headers->get(PrecognitionHeaders::SUCCESS));
        $this->assertContains(PrecognitionHeaders::PRECOGNITION, $response->headers->all(PrecognitionHeaders::VARY));
        $this->assertEmpty($response->getContent());
        $this->assertSame(0, $this->trackerCount());
    }

    public function testPrecognitiveInvalidRequestReturns422WithViolations(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', self::ROUTE, $this->invalidPayload(), $this->precognitive());

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response = $client->getResponse();
        $this->assertNotEmpty($this->violationPropertyPaths($response));
        $this->assertSame(PrecognitionHeaders::TRUE_VALUE, $response->headers->get(PrecognitionHeaders::PRECOGNITION));
        $this->assertContains(PrecognitionHeaders::PRECOGNITION, $response->headers->all(PrecognitionHeaders::VARY));
        $this->assertFalse($response->headers->has(PrecognitionHeaders::SUCCESS));
        $this->assertSame(0, $this->trackerCount());
    }

    public function testNonPrecognitiveRequestRunsControllerAndIsUnaffected(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', self::ROUTE, $this->validPayload());

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = $client->getResponse();
        $this->assertFalse($response->headers->has(PrecognitionHeaders::PRECOGNITION));
        $this->assertSame(1, $this->trackerCount());
    }

    public function testValidateOnlyReturns204WhenSelectedFieldValid(): void
    {
        $client = self::createClient();

        $payload = $this->invalidPayload();
        $payload['username'] = 'alice';

        $client->jsonRequest('POST', self::ROUTE, $payload, $this->precognitive('username'));

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(PrecognitionHeaders::TRUE_VALUE, $client->getResponse()->headers->get(PrecognitionHeaders::SUCCESS));
        $this->assertSame(0, $this->trackerCount());
    }

    public function testValidateOnlyReturnsOnlySelectedFieldErrors(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', self::ROUTE, $this->invalidPayload(), $this->precognitive('username'));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $propertyPaths = $this->violationPropertyPaths($client->getResponse());
        $this->assertNotEmpty($propertyPaths);
        foreach ($propertyPaths as $propertyPath) {
            $this->assertSame('username', $propertyPath);
        }
    }

    public function testValidateOnlyKeepsNestedFieldErrorsByPrefix(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', self::ROUTE, $this->invalidPayload(), $this->precognitive('address'));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $propertyPaths = $this->violationPropertyPaths($client->getResponse());
        $this->assertNotEmpty($propertyPaths);
        foreach ($propertyPaths as $propertyPath) {
            $this->assertStringStartsWith('address', $propertyPath);
        }
    }

    /**
     * @return array{username: string, email: string, address: array{street: string, city: string}}
     */
    private function validPayload(): array
    {
        return [
            'username' => 'alice',
            'email'    => 'alice@example.com',
            'address'  => ['street' => 'Main Street 1', 'city' => 'Berlin'],
        ];
    }

    /**
     * @return array{username: string, email: string, address: array{street: string, city: string}}
     */
    private function invalidPayload(): array
    {
        return [
            'username' => 'al',
            'email'    => 'not-an-email',
            'address'  => ['street' => '', 'city' => ''],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function precognitive(?string $validateOnly = null): array
    {
        $headers = ['HTTP_' . PrecognitionHeaders::PRECOGNITION => PrecognitionHeaders::TRUE_VALUE];
        if (null !== $validateOnly) {
            $headers['HTTP_PRECOGNITION_VALIDATE_ONLY'] = $validateOnly;
        }

        return $headers;
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

    private function trackerCount(): int
    {
        $tracker = self::getContainer()->get(RegistrationTracker::class);
        self::assertInstanceOf(RegistrationTracker::class, $tracker);

        return $tracker->count();
    }
}
