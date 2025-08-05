<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\DataObjects;

use Illuminate\Support\Collection;
use JsonSerializable;

final readonly class TokenStatistics implements JsonSerializable
{
    public function __construct(
        public int $totalTokens,
        public int $totalFiles,
        public string $model,
        public int $modelLimit,
        public bool $exceedsLimit,
        public Collection $fileStats,
        public float $averageTokensPerFile = 0.0,
        public int $maxTokensInFile = 0,
        public int $minTokensInFile = 0,
        public string $largestFile = '',
        public string $smallestFile = '',
    ) {}

    public function toArray(): array
    {
        return [
            'total_tokens' => $this->totalTokens,
            'total_files' => $this->totalFiles,
            'model' => $this->model,
            'model_limit' => $this->modelLimit,
            'exceeds_limit' => $this->exceedsLimit,
            'average_tokens_per_file' => $this->averageTokensPerFile,
            'max_tokens_in_file' => $this->maxTokensInFile,
            'min_tokens_in_file' => $this->minTokensInFile,
            'largest_file' => $this->largestFile,
            'smallest_file' => $this->smallestFile,
            'file_stats' => $this->fileStats->map(fn(FileStatistics $stat) => $stat->toArray())->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        $status = $this->exceedsLimit ? 'EXCEEDS LIMIT' : 'Within limit';
        return "Tokens: {$this->totalTokens}/{$this->modelLimit} ({$status}) across {$this->totalFiles} files";
    }

    public function getUtilizationPercentage(): float
    {
        return $this->modelLimit > 0 ? ($this->totalTokens / $this->modelLimit) * 100 : 0.0;
    }

    public function getRemainingTokens(): int
    {
        return max(0, $this->modelLimit - $this->totalTokens);
    }

    public function getFilesByExtension(): array
    {
        return $this->fileStats
            ->groupBy(fn(FileStatistics $stat) => $stat->extension)
            ->map(fn(Collection $files) => [
                'count' => $files->count(),
                'total_tokens' => $files->sum(fn(FileStatistics $stat) => $stat->tokens),
                'average_tokens' => $files->avg(fn(FileStatistics $stat) => $stat->tokens),
            ])
            ->toArray();
    }

    public function getTopFiles(int $limit = 10): Collection
    {
        return $this->fileStats
            ->sortByDesc(fn(FileStatistics $stat) => $stat->tokens)
            ->take($limit);
    }

    public static function create(
        int $totalTokens,
        int $totalFiles,
        string $model,
        int $modelLimit,
        Collection $fileStats
    ): self {
        $tokenCounts = $fileStats->map(fn(FileStatistics $stat) => $stat->tokens);
        
        $maxTokens = $tokenCounts->max() ?? 0;
        $minTokens = $tokenCounts->min() ?? 0;
        
        $largestFile = $fileStats
            ->sortByDesc(fn(FileStatistics $stat) => $stat->tokens)
            ->first()?->path ?? '';
            
        $smallestFile = $fileStats
            ->sortBy(fn(FileStatistics $stat) => $stat->tokens)
            ->first()?->path ?? '';

        return new self(
            totalTokens: $totalTokens,
            totalFiles: $totalFiles,
            model: $model,
            modelLimit: $modelLimit,
            exceedsLimit: $totalTokens > $modelLimit,
            fileStats: $fileStats,
            averageTokensPerFile: $totalFiles > 0 ? $totalTokens / $totalFiles : 0.0,
            maxTokensInFile: $maxTokens,
            minTokensInFile: $minTokens,
            largestFile: $largestFile,
            smallestFile: $smallestFile,
        );
    }
}
