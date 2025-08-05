<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Exceptions;

class InvalidUrlException extends GitIngestException
{
    public const ERROR_CODE_INVALID_FORMAT = 'URL_INVALID_FORMAT';
    public const ERROR_CODE_UNSUPPORTED_HOST = 'URL_UNSUPPORTED_HOST';
    public const ERROR_CODE_MISSING_REPOSITORY = 'URL_MISSING_REPOSITORY';

    public static function invalidFormat(string $url): self
    {
        return (new self("Invalid URL format: '{$url}'.", 400))
            ->setContext(['url' => $url, 'error_code' => self::ERROR_CODE_INVALID_FORMAT]);
    }

    public static function unsupportedHost(string $url, string $host): self
    {
        return (new self("Unsupported host '{$host}' in URL: '{$url}'. Only GitHub URLs are supported.", 400))
            ->setContext([
                'url' => $url,
                'host' => $host,
                'error_code' => self::ERROR_CODE_UNSUPPORTED_HOST,
            ]);
    }

    public static function missingRepository(string $url): self
    {
        return (new self("URL '{$url}' does not contain valid repository information.", 400))
            ->setContext(['url' => $url, 'error_code' => self::ERROR_CODE_MISSING_REPOSITORY]);
    }
}
