<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class PrecognitionMapQueryStringTest extends WebTestCase
{
    use PrecognitionFunctionalTestHelpers;

    public function testPrecognitiveValidQueryStringReturns204(): void
    {
        $client = self::createClient();

        $client->request('GET', '/dashboard', $this->validQuery(), [], $this->precognitive());

        $this->assertPrecognitionSuccessResponse($client->getResponse());
        $this->assertSame(0, $this->trackerCount());
    }

    public function testPrecognitiveInvalidQueryStringKeepsMapQueryStringDefault404(): void
    {
        $client = self::createClient();

        $client->request('GET', '/dashboard', $this->invalidQuery(), [], $this->precognitive());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(['firstName', 'age'], $this->violationPropertyPaths($client->getResponse()));
        $this->assertSame(0, $this->trackerCount());
    }

    public function testNonPrecognitiveInvalidQueryStringKeepsSymfonyDefault404(): void
    {
        $client = self::createClient();

        $client->request('GET', '/dashboard', $this->invalidQuery(), [], $this->acceptJson());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testValidateOnlyReturnsOnlySelectedQueryStringFieldErrors(): void
    {
        $client = self::createClient();

        $client->request('GET', '/dashboard', $this->invalidQuery(), [], $this->precognitive('age'));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(['age'], $this->violationPropertyPaths($client->getResponse()));
    }

    /**
     * @return array{firstName: string, lastName: string, age: int}
     */
    private function validQuery(): array
    {
        return ['firstName' => 'John', 'lastName' => 'Smith', 'age' => 28];
    }

    /**
     * @return array{firstName: string, lastName: string, age: int}
     */
    private function invalidQuery(): array
    {
        return ['firstName' => '', 'lastName' => 'Smith', 'age' => 17];
    }
}
