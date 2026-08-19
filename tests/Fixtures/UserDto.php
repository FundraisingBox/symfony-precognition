<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO adapted from Symfony's request payload and query string mapping docs.
 * Defaults let deserialization succeed so validation reports the errors.
 */
final class UserDto
{
    public function __construct(
        #[Assert\NotBlank]
        public string $firstName = '',
        #[Assert\NotBlank]
        public string $lastName = '',
        #[Assert\GreaterThan(18)]
        public int $age = 0,
    ) {
    }
}
