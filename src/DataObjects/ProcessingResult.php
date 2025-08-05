<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\DataObjects;

use Illuminate\Support\Collection;
use JsonSerializable;

final readonly class ProcessingResult implements JsonSerializable
{
    public function __construct(
        public string $repositoryUrl,
        public array $content,
        public TokenStatistics $statistics,
        public ProcessingMetadata $metadata,
        public bool $isChunked = false,
        public ?Collection $chunks = null,
        public bool $wasOptimized = false,
        public array $errors = [],
    ) {}

    public function toArray(): array
    {
        $result = [
            'repository_url' => $this->repositoryUrl,
            'content' => $this->content,
            'statistics' => $this->statistics->toArray(),
            'metadata' => $this->metadata->toArray(),
            'is_chunked' => $this->isChunked,
            'was_optimized' => $this->wasOptimized,
            'errors' => $this->errors,
        ];

        if ($this->isChunked && $this->chunks !== null) {
            $result['chunks'] = $this->chunks->map(fn(ChunkResult $chunk) => $chunk->toArray())->toArray();
            $result['chunk_count'] = $this->chunks->count();
        }

        return $result;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        $status = [];
        
        if ($this->isChunked) {
            $status[] = "chunked ({$this->getChunkCount()})";
        }
        
        if ($this->wasOptimized) {
            $status[] = "optimized";
        }
        
        if ($this->hasErrors()) {
            $status[] = "with errors ({$this->getErrorCount()})";
        }

        $statusStr = empty($status) ? '' : ' [' . implode(', ', $status) . ']';
        
        return "ProcessingResult: {$this->repositoryUrl} - {$this->statistics->totalTokens} tokens{$statusStr}";
    }

    public function isSuccessful(): bool
    {
        return empty($this->errors);
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getChunkCount(): int
    {
        return $this->chunks?->count() ?? 0;
    }

    public function getChunks(): ?Collection
    {
        return $this->chunks;
    }

    public function getChunk(int $chunkId): ?ChunkResult
    {
        return $this->chunks?->firstWhere('chunkId', $chunkId);
    }

    public function getContentSize(): int
    {
        return collect($this->content)->sum(fn($fileData) => strlen($fileData['content'] ?? ''));
    }

    public function getFileCount(): int
    {
        return count($this->content);
    }

    public function getFormattedSummary(): string
    {
        $lines = [
            "Repository: {$this->repositoryUrl}",
            "Files: {$this->getFileCount()}",
            "Tokens: {$this->statistics->totalTokens} / {$this->statistics->modelLimit}",
            "Processing Time: {$this->metadata->getFormattedProcessingTime()}",
            "Memory Usage: {$this->metadata->getFormattedMemoryUsage()}",
        ];

        if ($this->isChunked) {
            $lines[] = "Chunks: {$this->getChunkCount()}";
        }

        if ($this->wasOptimized) {
            $lines[] = "Content was optimized";
        }

        if ($this->hasErrors()) {
            $lines[] = "Errors: {$this->getErrorCount()}";
        }

        return implode("\n", $lines);
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags);
    }

    public function toPrettyJson(): string
    {
        return $this->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function withError(string $error): self
    {
        $errors = $this->errors;
        $errors[] = $error;

        return new self(
            repositoryUrl: $this->repositoryUrl,
            content: $this->content,
            statistics: $this->statistics,
            metadata: $this->metadata,
            isChunked: $this->isChunked,
            chunks: $this->chunks,
            wasOptimized: $this->wasOptimized,
            errors: $errors,
        );
    }

    public static function create(
        string $repositoryUrl,
        array $content,
        TokenStatistics $statistics,
        ProcessingMetadata $metadata,
        bool $wasOptimized = false,
        ?Collection $chunks = null,
        array $errors = []
    ): self {
        return new self(
            repositoryUrl: $repositoryUrl,
            content: $content,
            statistics: $statistics,
            metadata: $metadata,
            isChunked: $chunks !== null && $chunks->isNotEmpty(),
            chunks: $chunks,
            wasOptimized: $wasOptimized,
            errors: $errors,
        );
    }

    public static function createWithError(
        string $repositoryUrl,
        string $error,
        ProcessingMetadata $metadata
    ): self {
        return new self(
            repositoryUrl: $repositoryUrl,
            content: [],
            statistics: TokenStatistics::create(0, 0, 'unknown', 0, collect()),
            metadata: $metadata,
            errors: [$error],
        );
    }
}
