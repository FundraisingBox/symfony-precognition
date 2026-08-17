<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Attribute;

use Attribute;
use Symfony\Component\Form\FormTypeInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class PrecognitiveForm
{
    /**
     * @param class-string<FormTypeInterface> $type
     */
    public function __construct(
        public string $type,
    ) {
    }
}
