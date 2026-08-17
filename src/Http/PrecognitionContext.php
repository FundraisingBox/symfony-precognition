<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function trim;

final readonly class PrecognitionContext
{
    public const ACTIVE_ATTRIBUTE = '_precognition_active';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function isPrecognitive(?Request $request = null): bool
    {
        $request ??= $this->requestStack->getCurrentRequest();

        return null !== $request
            && PrecognitionHeaders::TRUE_VALUE === $request->headers->get(PrecognitionHeaders::PRECOGNITION);
    }

    public function isActive(?Request $request = null): bool
    {
        $request ??= $this->requestStack->getCurrentRequest();

        return null !== $request
            && $this->isPrecognitive($request)
            && true === $request->attributes->get(self::ACTIVE_ATTRIBUTE, false);
    }

    /**
     * The DTO property paths the client wants reported, parsed from the
     * comma-separated `Precognition-Validate-Only` header.
     *
     * @return list<string> field paths; an empty list means "no filter"
     */
    public function validateOnly(?Request $request = null): array
    {
        $request ??= $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        $header = $request->headers->get(PrecognitionHeaders::VALIDATE_ONLY);

        if (null === $header) {
            return [];
        }

        $fields = array_map(trim(...), explode(',', $header));

        return array_values(array_filter($fields, static fn (string $field): bool => '' !== $field));
    }

    public function activate(Request $request): void
    {
        $request->attributes->set(self::ACTIVE_ATTRIBUTE, true);
    }
}
