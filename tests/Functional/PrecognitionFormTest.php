<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional;

use FundraisingBox\Precognition\Http\PrecognitionHeaders;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;

#[CoversNothing]
final class PrecognitionFormTest extends WebTestCase
{
    use PrecognitionFunctionalTestHelpers;

    public function testPrecognitiveValidFormReturns204AndDoesNotRunController(): void
    {
        $client = self::createClient();

        $client->request('POST', '/task/new', $this->validTaskPayload(), [], $this->precognitive());

        $this->assertPrecognitionSuccessResponse($client->getResponse());
        $this->assertSame(0, $this->trackerCount());
    }

    public function testPrecognitiveInvalidFormReturns422WithRootlessViolationPath(): void
    {
        $client = self::createClient();

        $client->request('POST', '/task/new', $this->invalidTaskPayload(), [], $this->precognitive());

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response = $client->getResponse();
        $this->assertSame(PrecognitionHeaders::TRUE_VALUE, $response->headers->get(PrecognitionHeaders::PRECOGNITION));
        $this->assertFalse($response->headers->has(PrecognitionHeaders::SUCCESS));
        $this->assertSame(['task'], $this->violationPropertyPaths($response));
        $this->assertSame(0, $this->trackerCount());
    }

    public function testValidateOnlyReturns204WhenSelectedFormFieldIsValid(): void
    {
        $client = self::createClient();

        $client->request('POST', '/task/new', [
            'task' => [
                'task'    => 'Write docs',
                'dueDate' => '',
            ],
        ], [], $this->precognitive('task'));

        $this->assertPrecognitionSuccessResponse($client->getResponse());
        $this->assertSame(0, $this->trackerCount());
    }

    public function testValidateOnlyKeepsOnlySelectedFormFieldViolation(): void
    {
        $client = self::createClient();

        $client->request('POST', '/task/new', [
            'task' => [
                'task'    => '',
                'dueDate' => '',
            ],
        ], [], $this->precognitive('task'));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(['task'], $this->violationPropertyPaths($client->getResponse()));
        $this->assertSame(0, $this->trackerCount());
    }

    public function testBracketedValidateOnlyFieldNameMatchesFormViolation(): void
    {
        $client = self::createClient();

        $client->request('POST', '/task/new', $this->invalidTaskPayload(), [], $this->precognitive('[task]'));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(['task'], $this->violationPropertyPaths($client->getResponse()));
        $this->assertSame(0, $this->trackerCount());
    }

    public function testNonPrecognitiveValidFormRunsCanonicalControllerFlow(): void
    {
        $client = self::createClient();

        $client->request('POST', '/task/new', $this->validTaskPayloadWithCsrfToken($client), [], $this->acceptJson());

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertFalse($client->getResponse()->headers->has(PrecognitionHeaders::PRECOGNITION));
        $this->assertSame(1, $this->trackerCount());
    }

    public function testPrecognitiveFormValidationIgnoresCsrfToken(): void
    {
        $client = self::createClient();

        $client->request('POST', '/task/new', $this->validTaskPayload(), [], $this->precognitive());

        $this->assertPrecognitionSuccessResponse($client->getResponse());
        $this->assertSame(0, $this->trackerCount());
    }

    public function testUnannotatedFormControllerStillShortCircuitsWithoutValidation(): void
    {
        $client = self::createClient();

        $client->request('POST', '/task/unannotated', $this->invalidTaskPayload(), [], $this->precognitive());

        $this->assertPrecognitionSuccessResponse($client->getResponse());
        $this->assertSame(0, $this->trackerCount());
    }

    public function testClassLevelPrecognitiveFormAttributeValidates(): void
    {
        $client = self::createClient();

        $client->request('POST', '/task/class-level', $this->invalidTaskPayload(), [], $this->precognitive());

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(['task'], $this->violationPropertyPaths($client->getResponse()));
        $this->assertSame(0, $this->trackerCount());
    }

    /**
     * @return array{task: array{task: string, dueDate: string}}
     */
    private function validTaskPayload(): array
    {
        return [
            'task' => [
                'task'    => 'Write docs',
                'dueDate' => '2026-07-06',
            ],
        ];
    }

    /**
     * @return array{task: array{task: string, dueDate: string}}
     */
    private function invalidTaskPayload(): array
    {
        return [
            'task' => [
                'task'    => '',
                'dueDate' => '2026-07-06',
            ],
        ];
    }

    /**
     * @return array{task: array{task: string, dueDate: string, _token: string}}
     */
    private function validTaskPayloadWithCsrfToken(KernelBrowser $client): array
    {
        $client->request('GET', '/task/token', [], [], $this->acceptJson());
        self::assertResponseIsSuccessful();

        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('token', $data);
        self::assertIsString($data['token']);

        $payload = $this->validTaskPayload();
        $payload['task']['_token'] = $data['token'];

        return $payload;
    }
}
