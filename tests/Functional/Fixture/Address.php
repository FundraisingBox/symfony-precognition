<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional\Fixture;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Value object adapted from Symfony's Valid constraint docs. Public properties
 * with defaults keep it compatible with #[MapRequestPayload] in this fixture.
 */
final class Address
{
    #[Assert\NotBlank]
    public string $street = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 5)]
    public string $zipCode = '';
}
