<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Exceptions;

class ProcessingException extends GitIngestException
{
    public const ERROR_CODE_FILE_TOO_LARGE = 'PROC_FILE_TOO_LARGE';
    public const ERROR_CODE_INVALID_FORMAT = 'PROC_INVALID_FORMAT';
    public const ERROR_CODE_EXTRACTION_FAILED = 'PROC_EXTRACTION_FAILED';
    public const ERROR_CODE_MEMORY_EXHAUSTED = 'PROC_MEMORY_EXHAUSTED';
    public const ERROR_CODE_TIMEOUT = 'PROC_TIMEOUT';

    public static function fileTooLarge(string $filename, int $size, int $maxSize): self
    {
        return (new self("File '{$filename}' is too large ({$size} bytes). Maximum allowed: {$maxSize} bytes.", 413))
            ->setContext([
                'filename' => $filename,
                'size' => $size,
                'max_size' => $maxSize,
                'error_code' => self::ERROR_CODE_FILE_TOO_LARGE,
            ]);
    }

    public static function invalidFormat(string $filename, string $expectedFormat): self
    {
        return (new self("File '{$filename}' has an invalid format. Expected: {$expectedFormat}.", 400))
            ->setContext([
                'filename' => $filename,
                'expected_format' => $expectedFormat,
                'error_code' => self::ERROR_CODE_INVALID_FORMAT,
            ]);
    }

    public static function extractionFailed(string $archive, string $reason): self
    {
        return (new self("Failed to extract archive '{$archive}': {$reason}", 500))
            ->setContext([
                'archive' => $archive,
                'reason' => $reason,
                'error_code' => self::ERROR_CODE_EXTRACTION_FAILED,
            ]);
    }

    public static function memoryExhausted(string $operation): self
    {
        return (new self("Memory exhausted during {$operation}. Consider reducing file sizes or increasing memory limit.", 507))
            ->setContext([
                'operation' => $operation,
                'error_code' => self::ERROR_CODE_MEMORY_EXHAUSTED,
            ]);
    }

    public static function timeout(string $operation, int $timeoutSeconds): self
    {
        return (new self("Operation '{$operation}' timed out after {$timeoutSeconds} seconds.", 408))
            ->setContext([
                'operation' => $operation,
                'timeout_seconds' => $timeoutSeconds,
                'error_code' => self::ERROR_CODE_TIMEOUT,
            ]);
    }
}
