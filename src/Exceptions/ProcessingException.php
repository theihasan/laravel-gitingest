<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Exceptions;

use Exception;

class ProcessingException extends GitIngestException
{
    public static function zipExtractionFailed(string $zipPath, string $reason): self
    {
        return new self("Failed to extract ZIP file '{$zipPath}': {$reason}");
    }

    public static function fileProcessingFailed(string $filePath, string $reason): self
    {
        return new self("Failed to process file '{$filePath}': {$reason}");
    }

    public static function optimizationFailed(string $reason): self
    {
        return new self("Content optimization failed: {$reason}");
    }

    public static function chunkingFailed(string $reason): self
    {
        return new self("Content chunking failed: {$reason}");
    }

    public static function tokenCountingFailed(string $reason): self
    {
        return new self("Token counting failed: {$reason}");
    }
}
