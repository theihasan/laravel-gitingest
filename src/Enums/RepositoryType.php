<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Enums;

enum RepositoryType: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';

    public function requiresAuthentication(): bool
    {
        return $this === self::PRIVATE;
    }

    public function getDisplayName(): string
    {
        return match ($this) {
            self::PUBLIC => 'Public Repository',
            self::PRIVATE => 'Private Repository',
        };
    }
}
