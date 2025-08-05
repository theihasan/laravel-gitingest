<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Exceptions;

use Exception;

class DownloadException extends GitIngestException
{
    public static function repositoryNotFound(string $repository): self
    {
        return new self("Repository not found: {$repository}");
    }

    public static function accessDenied(string $repository): self
    {
        return new self("Access denied to repository: {$repository}");
    }

    public static function networkError(string $message): self
    {
        return new self("Network error during download: {$message}");
    }

    public static function invalidToken(): self
    {
        return new self("Invalid or expired GitHub token");
    }
}
