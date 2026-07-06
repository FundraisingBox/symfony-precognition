<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class PrecognitionValidateOnlyNestedTest extends WebTestCase
{
    use PrecognitionFunctionalTestHelpers;

    public function testValidateOnlyAddressKeepsNestedAddressErrorsOnly(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', '/authors', $this->invalidPayload(), $this->precognitive('address'));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(['address.street', 'address.zipCode'], $this->violationPropertyPaths($client->getResponse()));
    }

    public function testValidateOnlyBracketFormMatchesNestedAddressField(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', '/authors', $this->invalidPayload(), $this->precognitive('[address][street]'));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(['address.street'], $this->violationPropertyPaths($client->getResponse()));
    }

    public function testValidateOnlyFirstNameReturns204WhenFirstNameIsValidAndAddressIsInvalid(): void
    {
        $client = self::createClient();

        $payload = $this->invalidPayload();
        $payload['firstName'] = 'John';

        $client->jsonRequest('POST', '/authors', $payload, $this->precognitive('firstName'));

        $this->assertPrecognitionSuccessResponse($client->getResponse());
        $this->assertSame(0, $this->trackerCount());
    }

    /**
     * @return array{firstName: string, lastName: string, address: array{street: string, zipCode: string}}
     */
    private function invalidPayload(): array
    {
        return [
            'firstName' => '',
            'lastName'  => 'Smith',
            'address'   => ['street' => '', 'zipCode' => '123456'],
        ];
    }
}
