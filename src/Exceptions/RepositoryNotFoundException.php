<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Exceptions;

class RepositoryNotFoundException extends GitIngestException
{
    public const ERROR_CODE_NOT_FOUND = 'REPO_NOT_FOUND';
    public const ERROR_CODE_INVALID_URL = 'REPO_INVALID_URL';
    public const ERROR_CODE_ACCESS_DENIED = 'REPO_ACCESS_DENIED';

    public static function repositoryNotFound(string $repository): self
    {
        return (new self("Repository '{$repository}' was not found or does not exist.", 404))
            ->setContext(['repository' => $repository, 'error_code' => self::ERROR_CODE_NOT_FOUND]);
    }

    public static function invalidUrl(string $url): self
    {
        return (new self("Invalid GitHub repository URL: '{$url}'.", 400))
            ->setContext(['url' => $url, 'error_code' => self::ERROR_CODE_INVALID_URL]);
    }

    public static function accessDenied(string $repository): self
    {
        return (new self("Access denied to repository '{$repository}'. Check your permissions.", 403))
            ->setContext(['repository' => $repository, 'error_code' => self::ERROR_CODE_ACCESS_DENIED]);
    }
}
