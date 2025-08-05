<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\DataObjects;

use JsonSerializable;

final readonly class FileStatistics implements JsonSerializable
{
    public function __construct(
        public string $path,
        public int $tokens,
        public int $size,
        public int $lines,
        public string $extension,
        public float $processingTime = 0.0,
    ) {}

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'tokens' => $this->tokens,
            'size' => $this->size,
            'lines' => $this->lines,
            'extension' => $this->extension,
            'processing_time' => $this->processingTime,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return "File: {$this->path} ({$this->tokens} tokens, {$this->size} bytes, {$this->lines} lines)";
    }

    public function getTokensPerLine(): float
    {
        return $this->lines > 0 ? $this->tokens / $this->lines : 0.0;
    }

    public function getTokensPerByte(): float
    {
        return $this->size > 0 ? $this->tokens / $this->size : 0.0;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            path: $data['path'] ?? '',
            tokens: $data['tokens'] ?? 0,
            size: $data['size'] ?? 0,
            lines: $data['lines'] ?? 0,
            extension: $data['extension'] ?? '',
            processingTime: $data['processing_time'] ?? 0.0,
        );
    }
}
