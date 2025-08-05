<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Enums;

enum OutputFormat: string
{
    case TEXT = 'text';
    case MARKDOWN = 'markdown';
    case JSON = 'json';

    public function getFileExtension(): string
    {
        return match ($this) {
            self::TEXT => '.txt',
            self::MARKDOWN => '.md',
            self::JSON => '.json',
        };
    }

    public function getMimeType(): string
    {
        return match ($this) {
            self::TEXT => 'text/plain',
            self::MARKDOWN => 'text/markdown',
            self::JSON => 'application/json',
        };
    }
}
