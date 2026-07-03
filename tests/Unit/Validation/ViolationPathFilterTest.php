<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Unit\Validation;

use FundraisingBox\Precognition\Validation\ViolationPathFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[CoversClass(ViolationPathFilter::class)]
final class ViolationPathFilterTest extends TestCase
{
    /**
     * @param list<string> $propertyPaths
     * @param list<string> $requestedFields
     * @param list<int>    $expectedOffsets
     */
    #[DataProvider('offsetsProvider')]
    public function testNonMatchingOffsets(array $propertyPaths, array $requestedFields, array $expectedOffsets): void
    {
        $violations = new ConstraintViolationList();
        foreach ($propertyPaths as $propertyPath) {
            $violations->add(new ConstraintViolation('message', null, [], null, $propertyPath, null));
        }

        $filter = new ViolationPathFilter();

        $this->assertSame($expectedOffsets, $filter->nonMatchingOffsets($violations, $requestedFields));
    }

    /**
     * @return iterable<string, array{0: list<string>, 1: list<string>, 2: list<int>}>
     */
    public static function offsetsProvider(): iterable
    {
        yield 'exact match kept' => [
            ['address.city'],
            ['address.city'],
            [],
        ];

        yield 'descendant match kept' => [
            ['address.city'],
            ['address'],
            [],
        ];

        yield 'collection descendant match kept' => [
            ['items[0].price'],
            ['items'],
            [],
        ];

        yield 'non match flagged' => [
            ['address.street'],
            ['address.city'],
            [0],
        ];

        yield 'prefix without separator is not a match' => [
            ['addressLine'],
            ['address'],
            [0],
        ];

        yield 'empty filter flags everything' => [
            ['address.city', 'address.street'],
            [],
            [1, 0],
        ];

        yield 'offsets returned in descending order' => [
            ['address.city', 'address.street', 'address.zip'],
            ['address.city'],
            [2, 1],
        ];

        yield 'mixed matches and non matches' => [
            ['address.city', 'username', 'address.street'],
            ['address.city', 'address.street'],
            [1],
        ];

        yield 'bracket path matches dotted field' => [
            ['[address][city]'],
            ['address.city'],
            [],
        ];

        yield 'bracket path descendant matches dotted field' => [
            ['[address][city]'],
            ['address'],
            [],
        ];

        yield 'bracket path non match flagged' => [
            ['[address][street]'],
            ['address.city'],
            [0],
        ];

        yield 'mixed bracket and dotted notation' => [
            ['[address][city]', '[address][street]', 'username'],
            ['address.city'],
            [2, 1],
        ];
    }
}
