<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional\Fixture;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Nested value object used to exercise property-path prefix matching
 * (Precognition-Validate-Only: address keeps address.street / address.city).
 */
final class Address
{
    public function __construct(
        #[Assert\NotBlank]
        public string $street = '',
        #[Assert\NotBlank]
        public string $city = '',
    ) {
    }
}
