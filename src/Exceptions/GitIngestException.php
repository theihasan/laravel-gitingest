<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Exceptions;

use Exception;

class GitIngestException extends Exception
{
    public static function processingFailed(string $repository, string $reason): self
    {
        return new self("Failed to process repository '{$repository}': {$reason}");
    }

    public static function invalidRepository(string $repository): self
    {
        return new self("Invalid repository URL or format: {$repository}");
    }

    public static function configurationError(string $message): self
    {
        return new self("Configuration error: {$message}");
    }
}
