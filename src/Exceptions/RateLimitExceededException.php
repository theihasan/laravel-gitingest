<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Exceptions;

class RateLimitExceededException extends GitIngestException
{
    public const ERROR_CODE_API_RATE_LIMIT = 'RATE_LIMIT_API';
    public const ERROR_CODE_HOURLY_LIMIT = 'RATE_LIMIT_HOURLY';
    public const ERROR_CODE_DOWNLOAD_LIMIT = 'RATE_LIMIT_DOWNLOAD';

    public static function apiRateLimitExceeded(int $resetTime): self
    {
        return (new self('GitHub API rate limit exceeded. Please try again later.', 429))
            ->setContext([
                'reset_time' => $resetTime,
                'error_code' => self::ERROR_CODE_API_RATE_LIMIT,
            ]);
    }

    public static function hourlyLimitExceeded(int $currentUsage, int $limit): self
    {
        return (new self("Hourly rate limit exceeded. Used {$currentUsage}/{$limit} requests.", 429))
            ->setContext([
                'current_usage' => $currentUsage,
                'limit' => $limit,
                'error_code' => self::ERROR_CODE_HOURLY_LIMIT,
            ]);
    }

    public static function downloadLimitExceeded(int $currentDownloads, int $limit): self
    {
        return (new self("Download limit exceeded. Downloaded {$currentDownloads}/{$limit} repositories.", 429))
            ->setContext([
                'current_downloads' => $currentDownloads,
                'limit' => $limit,
                'error_code' => self::ERROR_CODE_DOWNLOAD_LIMIT,
            ]);
    }

    public function getRetryAfter(): ?int
    {
        return $this->getContext()['reset_time'] ?? null;
    }
}
