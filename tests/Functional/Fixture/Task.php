<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Functional\Fixture;

use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class Task
{
    #[Assert\NotBlank]
    private string $task = '';

    #[Assert\NotNull]
    private ?DateTimeInterface $dueDate = null;

    public function getTask(): string
    {
        return $this->task;
    }

    public function setTask(string $task): void
    {
        $this->task = $task;
    }

    public function getDueDate(): ?DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?DateTimeInterface $dueDate): void
    {
        $this->dueDate = $dueDate;
    }
}
