<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Cache\Repository as Cache;

final readonly class TokenCounter
{
    private const MODEL_ENCODINGS = [
        'gpt-4' => 'cl100k_base',
        'gpt-4-turbo' => 'cl100k_base',
        'gpt-4o' => 'o200k_base',
        'gpt-3.5-turbo' => 'cl100k_base',
        'text-davinci-003' => 'p50k_base',
        'claude-3-opus' => 'cl100k_base', 
        'claude-3-sonnet' => 'cl100k_base', 
        'claude-3-haiku' => 'cl100k_base', 
    ];

    private const FALLBACK_ESTIMATION = [
        'words_per_token' => 0.75,
        'chars_per_token' => 4,
    ];

    public function __construct(
        private Cache $cache,
        private array $config,
        private int $cacheTimeMinutes = 60,
    ) {}

    public function countTokens(string $text, string $model = 'gpt-4'): int
    {
        if (empty($text)) {
            return 0;
        }

        $text = $this->normalizeText($text);
        $cacheKey = $this->getCacheKey($text, $model);

        return $this->cache->remember($cacheKey, $this->cacheTimeMinutes * 60, function () use ($text, $model): int {
            return $this->performTokenCounting($text, $model);
        });
    }

    public function countTokensBatch(Collection $texts, string $model = 'gpt-4'): Collection
    {
        return $texts->map(fn(string $text): int => $this->countTokens($text, $model));
    }

    public function estimateTokensFromFiles(Collection $files, string $model = 'gpt-4'): array
    {
        $totalTokens = 0;
        $fileTokens = [];

        $files->each(function (array $file) use (&$totalTokens, &$fileTokens, $model): void {
            $content = $file['content'] ?? '';
            $tokens = $this->countTokens($content, $model);
            
            $totalTokens += $tokens;
            $fileTokens[$file['path'] ?? 'unknown'] = [
                'tokens' => $tokens,
                'size' => strlen($content),
                'lines' => substr_count($content, "\n") + 1,
            ];
        });

        return [
            'total_tokens' => $totalTokens,
            'file_breakdown' => $fileTokens,
            'model_limits' => $this->getModelLimits($model),
            'fits_in_context' => $totalTokens <= $this->getModelLimit($model),
        ];
    }

    public function getOptimalChunkSize(string $model, float $utilizationRatio = 0.8): int
    {
        $modelLimit = $this->getModelLimit($model);
        return (int) floor($modelLimit * $utilizationRatio);
    }

    public function chunkTextByTokens(string $text, int $maxTokens, string $model = 'gpt-4'): Collection
    {
        if ($this->countTokens($text, $model) <= $maxTokens) {
            return collect([$text]);
        }

        return $this->recursiveChunking($text, $maxTokens, $model);
    }

    private function performTokenCounting(string $text, string $model): int
    {
        $encoding = $this->getModelEncoding($model);
        
        if ($this->isTiktokenAvailable() && $encoding) {
            return $this->countWithTiktoken($text, $encoding);
        }

        return $this->estimateTokenCount($text);
    }

    private function countWithTiktoken(string $text, string $encoding): int
    {
        if (function_exists('tiktoken_encode')) {
            $tokens = tiktoken_encode($text, $encoding);
            return count($tokens);
        }

        return $this->estimateTokenCount($text);
    }

    private function estimateTokenCount(string $text): int
    {
        $method = Arr::get($this->config, 'token_counting.estimation_method', 'mixed');
        
        return match ($method) {
            'words' => $this->estimateByWords($text),
            'characters' => $this->estimateByCharacters($text),
            'mixed' => $this->estimateByMixed($text),
            default => $this->estimateByMixed($text),
        };
    }

    private function estimateByWords(string $text): int
    {
        $wordCount = str_word_count($text);
        $wordsPerToken = self::FALLBACK_ESTIMATION['words_per_token'];
        
        return (int) ceil($wordCount / $wordsPerToken);
    }

    private function estimateByCharacters(string $text): int
    {
        $charCount = mb_strlen($text, 'UTF-8');
        $charsPerToken = self::FALLBACK_ESTIMATION['chars_per_token'];
        
        return (int) ceil($charCount / $charsPerToken);
    }

    private function estimateByMixed(string $text): int
    {
        // Use a weighted average of word and character-based estimation
        $wordEstimate = $this->estimateByWords($text);
        $charEstimate = $this->estimateByCharacters($text);
        
        return (int) round(($wordEstimate * 0.6) + ($charEstimate * 0.4));
    }

    private function recursiveChunking(string $text, int $maxTokens, string $model): Collection
    {
        $chunks = collect();
        $sentences = $this->splitIntoSentences($text);
        $currentChunk = '';
        
        foreach ($sentences as $sentence) {
            $testChunk = $currentChunk . ' ' . $sentence;
            
            if ($this->countTokens(trim($testChunk), $model) > $maxTokens) {
                if (!empty($currentChunk)) {
                    $chunks->push(trim($currentChunk));
                    $currentChunk = $sentence;
                } else {
                    // Sentence is too long, split by words
                    $wordChunks = $this->chunkByWords($sentence, $maxTokens, $model);
                    $chunks = $chunks->merge($wordChunks);
                }
            } else {
                $currentChunk = $testChunk;
            }
        }
        
        if (!empty($currentChunk)) {
            $chunks->push(trim($currentChunk));
        }
        
        return $chunks;
    }

    private function chunkByWords(string $text, int $maxTokens, string $model): Collection
    {
        $words = explode(' ', $text);
        $chunks = collect();
        $currentChunk = '';
        
        foreach ($words as $word) {
            $testChunk = $currentChunk . ' ' . $word;
            
            if ($this->countTokens(trim($testChunk), $model) > $maxTokens) {
                if (!empty($currentChunk)) {
                    $chunks->push(trim($currentChunk));
                    $currentChunk = $word;
                } else {
                    // Single word is too long, truncate
                    $chunks->push($word);
                }
            } else {
                $currentChunk = $testChunk;
            }
        }
        
        if (!empty($currentChunk)) {
            $chunks->push(trim($currentChunk));
        }
        
        return $chunks;
    }

    private function splitIntoSentences(string $text): array
    {
        // Simple sentence splitting - could be enhanced with NLP libraries
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return $sentences ?: [$text];
    }

    private function normalizeText(string $text): string
    {
        // Normalize whitespace and encoding
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function getModelEncoding(string $model): ?string
    {
        return self::MODEL_ENCODINGS[$model] ?? null;
    }

    private function getModelLimit(string $model): int
    {
        return match ($model) {
            'gpt-4', 'gpt-4-turbo' => 128_000,
            'gpt-4o' => 128_000,
            'gpt-3.5-turbo' => 16_385,
            'text-davinci-003' => 4_097,
            'claude-3-opus' => 200_000,
            'claude-3-sonnet' => 200_000,
            'claude-3-haiku' => 200_000,
            default => 100_000,
        };
    }

    private function getModelLimits(string $model): array
    {
        return [
            'context_limit' => $this->getModelLimit($model),
            'recommended_max' => (int) ($this->getModelLimit($model) * 0.8),
            'encoding' => $this->getModelEncoding($model),
        ];
    }

    private function isTiktokenAvailable(): bool
    {
        return function_exists('tiktoken_encode') || class_exists('Tiktoken\\Tiktoken');
    }

    private function getCacheKey(string $text, string $model): string
    {
        return 'token_count:' . $model . ':' . hash('sha256', $text);
    }
}
