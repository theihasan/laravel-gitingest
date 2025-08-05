<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Enums;

enum ProcessingStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function isFinished(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::CANCELLED => true,
            self::PENDING, self::IN_PROGRESS => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }

    public function getDisplayName(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::IN_PROGRESS => 'blue',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
            self::CANCELLED => 'orange',
        };
    }
}
