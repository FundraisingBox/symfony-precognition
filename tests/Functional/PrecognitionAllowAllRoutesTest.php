<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional;

use FundraisingBox\Precognition\Tests\Functional\Fixture\AllowAllRoutesTestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversNothing]
final class PrecognitionAllowAllRoutesTest extends WebTestCase
{
    use PrecognitionFunctionalTestHelpers;

    protected static function getKernelClass(): string
    {
        return AllowAllRoutesTestKernel::class;
    }

    public function testGlobalModeShortCircuitsUnannotatedRoutes(): void
    {
        $client = self::createClient();

        $client->request('POST', '/task/unannotated', $this->invalidTaskPayload(), [], $this->precognitive());

        $this->assertPrecognitionSuccessResponse($client->getResponse());
        $this->assertSame(0, $this->trackerCount());
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
}
