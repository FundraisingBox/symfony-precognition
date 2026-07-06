<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional\Fixture;

/**
 * Records how often a controller body actually ran, so tests can assert that a
 * precognitive request never reaches it.
 */
final class ControllerInvocationTracker
{
    private int $count = 0;

    public function record(): void
    {
        ++$this->count;
    }

    public function count(): int
    {
        return $this->count;
    }
}
