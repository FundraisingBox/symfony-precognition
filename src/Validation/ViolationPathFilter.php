<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Validation;

use Symfony\Component\Validator\ConstraintViolationListInterface;

use function array_filter;
use function array_reverse;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function str_replace;
use function trim;

/**
 * Matches constraint-violation property paths against requested field paths.
 *
 * Property paths are normalised to segment lists so the dotted object syntax
 * (`address.city`) and the bracketed collection syntax (`[address][city]`)
 * are treated as equivalent. A requested field matches a violation when its
 * segments are a prefix of (or equal to) the violation's segments, so requesting
 * `address` also keeps violations on `address.city`.
 *
 * Generic, domain-free helper used by the validate-only flow.
 */
final class ViolationPathFilter
{
    /**
     * Offsets of violations whose property path matches none of the requested
     * fields, returned in descending order so callers can safely `remove()`
     * each offset without invalidating the earlier ones.
     *
     * @param list<string> $requestedFields
     *
     * @return list<int>
     */
    public function nonMatchingOffsets(ConstraintViolationListInterface $violations, array $requestedFields): array
    {
        $requestedSegments = [];
        foreach ($requestedFields as $field) {
            $requestedSegments[] = $this->segments($field);
        }

        $offsets = [];
        foreach ($violations as $offset => $violation) {
            if (!$this->matchesAnyField($this->segments($violation->getPropertyPath()), $requestedSegments)) {
                $offsets[] = $offset;
            }
        }

        return array_reverse($offsets);
    }

    /**
     * @param list<string>       $violationSegments
     * @param list<list<string>> $requestedSegments
     */
    private function matchesAnyField(array $violationSegments, array $requestedSegments): bool
    {
        foreach ($requestedSegments as $fieldSegments) {
            if ($fieldSegments === array_slice($violationSegments, 0, count($fieldSegments))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function segments(string $propertyPath): array
    {
        $normalised = str_replace(['[', ']'], ['.', ''], $propertyPath);

        return array_values(array_filter(
            explode('.', $normalised),
            static fn (string $segment): bool => '' !== trim($segment)
        ));
    }
}
