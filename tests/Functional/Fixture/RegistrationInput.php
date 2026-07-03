<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional\Fixture;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request payload mapped by #[MapRequestPayload]. Defaults let deserialization
 * always succeed so the validator, not the serializer, reports the errors.
 */
final class RegistrationInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3)]
        public string $username = '',
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email = '',
        #[Assert\NotNull]
        #[Assert\Valid]
        public ?Address $address = null,
    ) {
    }
}
