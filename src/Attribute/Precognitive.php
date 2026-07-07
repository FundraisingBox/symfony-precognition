<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class Precognitive
{
}
