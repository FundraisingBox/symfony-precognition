<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class PrecognitionMapUploadedFileTest extends WebTestCase
{
    use PrecognitionFunctionalTestHelpers;

    public function testPrecognitiveValidPngReturns204AndDoesNotRunController(): void
    {
        $client = self::createClient();

        $client->request(
            'PUT',
            '/user/picture',
            [],
            ['picture' => $this->uploadedFile('picture.png', 'image/png')],
            $this->precognitive()
        );

        $this->assertPrecognitionSuccessResponse($client->getResponse());
        $this->assertSame(0, $this->trackerCount());
    }

    public function testPrecognitiveInvalidFileReturns422WithPictureViolation(): void
    {
        $client = self::createClient();

        $client->request(
            'PUT',
            '/user/picture',
            [],
            ['picture' => $this->uploadedFile('picture.txt', 'text/plain')],
            $this->precognitive()
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame(['picture'], $this->violationPropertyPaths($client->getResponse()));
        $this->assertSame(0, $this->trackerCount());
    }

    public function testPrecognitiveMissingFileReturns422WithoutViolations(): void
    {
        $client = self::createClient();

        $client->request('PUT', '/user/picture', [], [], $this->precognitive());

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertNoViolations($client->getResponse());
        $this->assertSame(0, $this->trackerCount());
    }

    private function uploadedFile(string $filename, string $mimeType): UploadedFile
    {
        return new UploadedFile(__DIR__ . '/Fixture/files/' . $filename, $filename, $mimeType, null, true);
    }
}
