<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\DataObjects;

use Illuminate\Support\Collection;
use JsonSerializable;

final readonly class ChunkResult implements JsonSerializable
{
    public function __construct(
        public int $chunkId,
        public string $content,
        public int $tokens,
        public Collection $files,
        public array $navigation,
        public string $strategy,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'chunk_id' => $this->chunkId,
            'content' => $this->content,
            'tokens' => $this->tokens,
            'files' => $this->files->toArray(),
            'navigation' => $this->navigation,
            'strategy' => $this->strategy,
            'metadata' => $this->metadata,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        $fileCount = $this->files->count();
        return "Chunk {$this->chunkId}: {$this->tokens} tokens, {$fileCount} files ({$this->strategy})";
    }

    public function getFileCount(): int
    {
        return $this->files->count();
    }

    public function getFileList(): array
    {
        return $this->files->toArray();
    }

    public function hasNavigation(): bool
    {
        return !empty($this->navigation);
    }

    public function getPreviousChunk(): ?int
    {
        return $this->navigation['previous'] ?? null;
    }

    public function getNextChunk(): ?int
    {
        return $this->navigation['next'] ?? null;
    }

    public function getContentPreview(int $maxLength = 100): string
    {
        if (strlen($this->content) <= $maxLength) {
            return $this->content;
        }

        return substr($this->content, 0, $maxLength) . '...';
    }

    public static function create(
        int $chunkId,
        string $content,
        int $tokens,
        Collection $files,
        string $strategy,
        array $navigation = [],
        array $metadata = []
    ): self {
        return new self(
            chunkId: $chunkId,
            content: $content,
            tokens: $tokens,
            files: $files,
            navigation: $navigation,
            strategy: $strategy,
            metadata: $metadata,
        );
    }
}
