<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Http;

use Symfony\Component\HttpFoundation\Request;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function trim;

/**
 * Reads the precognition intent from an incoming request.
 *
 * Stateless helper so it can be used from any listener without wiring.
 */
final class PrecognitionRequest
{
    public static function isPrecognitive(Request $request): bool
    {
        return PrecognitionHeaders::TRUE_VALUE === $request->headers->get(PrecognitionHeaders::PRECOGNITION);
    }

    /**
     * The DTO property paths the client wants reported, parsed from the
     * comma-separated `Precognition-Validate-Only` header.
     *
     * @return list<string> field paths; an empty list means "no filter"
     */
    public static function validateOnly(Request $request): array
    {
        $header = $request->headers->get(PrecognitionHeaders::VALIDATE_ONLY);

        if (null === $header) {
            return [];
        }

        $fields = array_map(trim(...), explode(',', $header));

        return array_values(array_filter($fields, static fn (string $field): bool => '' !== $field));
    }
}
