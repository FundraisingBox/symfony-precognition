<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\EventListener;

/**
 * Event listener priorities, where higher values run earlier.
 */
enum PrecognitionEventPriority: int
{
    // Activation runs before Symfony's controller-attribute listener; response
    // headers run after its cleanup and before Symfony's response error handling.
    // @see vendor/symfony/http-kernel/EventListener/ControllerAttributesListener.php
    // @see vendor/symfony/http-kernel/EventListener/ErrorListener.php
    case DEFAULT = 0;

    // Runs after controller-attribute cleanup, but before Symfony's exception
    // logger and renderer.
    // @see vendor/symfony/http-kernel/EventListener/ControllerAttributesListener.php
    // @see vendor/symfony/http-kernel/EventListener/ErrorListener.php
    case VALIDATION = 20;

    // These listeners run in order. Depending on the Symfony version, its
    // payload resolver runs either before both or after both.
    // @see vendor/symfony/http-kernel/Controller/ArgumentResolver/RequestPayloadValueResolver.php
    case FORM_VALIDATION = -32;
    case SHORT_CIRCUIT = -64;
}
