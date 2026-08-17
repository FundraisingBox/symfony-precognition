<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional;

use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class PrecognitionMapRequestPayloadTest extends WebTestCase
{
    use PrecognitionFunctionalTestHelpers;

    public function testPrecognitiveValidRequestReturns204AndDoesNotRunController(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', '/user', $this->validPayload(), $this->precognitive());

        $this->assertPrecognitionSuccessResponse($client->getResponse());
        $this->assertSame(0, $this->trackerCount());
    }

    public function testPrecognitiveInvalidRequestReturns422WithViolations(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', '/user', $this->invalidPayload(), $this->precognitive());

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response = $client->getResponse();
        $this->assertSame(PrecognitionHeaders::TRUE_VALUE, $response->headers->get(PrecognitionHeaders::PRECOGNITION));
        $this->assertFalse($response->headers->has(PrecognitionHeaders::SUCCESS));
        $this->assertSame(['firstName', 'age'], $this->violationPropertyPaths($response));
        $this->assertSame(0, $this->trackerCount());
    }

    public function testNonPrecognitiveRequestRunsControllerAndIsUnaffected(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', '/user', $this->validPayload(), $this->acceptJson());

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertFalse($client->getResponse()->headers->has(PrecognitionHeaders::PRECOGNITION));
        $this->assertSame(1, $this->trackerCount());
    }

    public function testValidateOnlyReturns204WhenSelectedFieldIsValid(): void
    {
        $client = self::createClient();

        $payload = $this->invalidPayload();
        $payload['firstName'] = 'John';

        $client->jsonRequest('POST', '/user', $payload, $this->precognitive('firstName'));

        $this->assertPrecognitionSuccessResponse($client->getResponse());
        $this->assertSame(0, $this->trackerCount());
    }

    /**
     * @return array{firstName: string, lastName: string, age: int}
     */
    private function validPayload(): array
    {
        return ['firstName' => 'John', 'lastName' => 'Smith', 'age' => 28];
    }

    /**
     * @return array{firstName: string, lastName: string, age: int}
     */
    private function invalidPayload(): array
    {
        return ['firstName' => '', 'lastName' => 'Smith', 'age' => 17];
    }
}
