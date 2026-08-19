<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO adapted from Symfony's Valid constraint docs. The nullable address keeps
 * payload mapping simple while #[Assert\NotNull] makes validation report a
 * missing nested object.
 */
final class Author
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 4)]
    public string $firstName = '';

    #[Assert\NotBlank]
    public string $lastName = '';

    #[Assert\NotNull]
    #[Assert\Valid]
    public ?Address $address = null;
}
