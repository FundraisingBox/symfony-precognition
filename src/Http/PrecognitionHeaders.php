<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Http;

/**
 * Header names and the canonical "on" value used by the precognition protocol.
 *
 * Mirrors Laravel Precognition: a request opts in with `Precognition: true`,
 * and every precognitive response echoes `Precognition: true`, varies on the
 * `Precognition` header, and (on success) carries `Precognition-Success: true`.
 */
final class PrecognitionHeaders
{
    public const string PRECOGNITION = 'Precognition';

    public const string SUCCESS = 'Precognition-Success';

    public const string VALIDATE_ONLY = 'Precognition-Validate-Only';

    public const string VARY = 'Vary';

    public const string TRUE_VALUE = 'true';
}
