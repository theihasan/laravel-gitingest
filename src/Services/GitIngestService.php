<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Ihasan\LaravelGitingest\Contracts\DownloaderInterface;
use Ihasan\LaravelGitingest\Services\Downloaders\PrivateRepositoryDownloader;
use Ihasan\LaravelGitingest\Services\Downloaders\PublicRepositoryDownloader;
use Ihasan\LaravelGitingest\Services\Processors\ContentProcessor;
use Ihasan\LaravelGitingest\Services\Processors\ZipProcessor;
use Ihasan\LaravelGitingest\Services\Optimizers\ContentOptimizer;
use Ihasan\LaravelGitingest\Exceptions\GitIngestException;
use Ihasan\LaravelGitingest\Exceptions\DownloadException;
use Ihasan\LaravelGitingest\Exceptions\ProcessingException;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Throwable;

final class GitIngestService
{
    private array $progressCallbacks = [];
    private bool $cacheEnabled = true;
    private int $cacheTime = 3600;

    public function __construct(
        private readonly PublicRepositoryDownloader $publicDownloader,
        private readonly PrivateRepositoryDownloader $privateDownloader,
        private readonly ZipProcessor $zipProcessor,
        private readonly FileFilter $fileFilter,
        private readonly ContentProcessor $contentProcessor,
        private readonly TokenCounter $tokenCounter,
        private readonly ContentOptimizer $contentOptimizer,
        private readonly ContentChunker $contentChunker,
        private readonly LoopInterface $eventLoop,
    ) {}

    public function processRepository(
        string $repositoryUrl,
        array $options = []
    ): array {
        try {
            $this->emitProgress('starting', 0, 'Starting repository processing');
            
            $processedOptions = $this->processOptions($options);
            $cacheKey = $this->generateCacheKey($repositoryUrl, $processedOptions);
            
            if ($this->cacheEnabled && Cache::has($cacheKey)) {
                $this->emitProgress('cache_hit', 100, 'Retrieved from cache');
                return Cache::get($cacheKey);
            }

            $result = $this->executeProcessingPipeline($repositoryUrl, $processedOptions);
            
            if ($this->cacheEnabled) {
                Cache::put($cacheKey, $result, $this->cacheTime);
            }

            $this->emitProgress('completed', 100, 'Repository processing completed');
            return $result;

        } catch (Throwable $e) {
            Log::error('Repository processing failed', [
                'repository' => $repositoryUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->emitProgress('error', 0, "Processing failed: {$e->getMessage()}");
            throw new GitIngestException("Failed to process repository: {$e->getMessage()}", 0, $e);
        }
    }

    public function processRepositoryAsync(
        string $repositoryUrl,
        array $options = []
    ): PromiseInterface {
        return \React\Promise\resolve()
            ->then(fn() => $this->processRepository($repositoryUrl, $options));
    }

    public function onProgress(callable $callback): self
    {
        $this->progressCallbacks[] = $callback;
        return $this;
    }

    public function setCacheEnabled(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        return $this;
    }

    public function setCacheTime(int $seconds): self
    {
        $this->cacheTime = $seconds;
        return $this;
    }

    private function executeProcessingPipeline(string $repositoryUrl, array $options): array
    {
        // Step 1: Download repository
        $this->emitProgress('downloading', 10, 'Downloading repository');
        $downloader = $this->selectDownloader($options);
        $zipPath = $downloader->download($repositoryUrl, $options);

        try {
            // Step 2: Extract ZIP
            $this->emitProgress('extracting', 20, 'Extracting repository contents');
            $extractedPath = $this->zipProcessor->extractZip($zipPath);

            // Step 3: Filter files
            $this->emitProgress('filtering', 30, 'Filtering repository files');
            $files = $this->fileFilter->filterFiles($extractedPath, $options['filter'] ?? []);

            // Step 4: Process content
            $this->emitProgress('processing', 50, 'Processing file contents');
            $processedContent = $this->contentProcessor->processFiles($files, $options['format'] ?? []);

            // Step 5: Count tokens
            $this->emitProgress('counting_tokens', 60, 'Counting tokens');
            $tokenStats = $this->calculateTokenStatistics($processedContent, $options);

            // Step 6: Optimize content if needed
            $optimizedContent = $processedContent;
            if ($this->shouldOptimize($tokenStats, $options)) {
                $this->emitProgress('optimizing', 70, 'Optimizing content');
                $optimizedContent = $this->contentOptimizer->optimizeFiles(
                    $processedContent, 
                    $options['optimization'] ?? []
                );
            }

            // Step 7: Chunk content if needed
            $chunks = null;
            if ($this->shouldChunk($tokenStats, $options)) {
                $this->emitProgress('chunking', 80, 'Chunking content');
                $chunks = $this->contentChunker->chunkRepository(
                    $optimizedContent,
                    $options['chunking'] ?? []
                );
            }

            // Step 8: Generate final result
            $this->emitProgress('finalizing', 90, 'Finalizing results');
            return $this->buildResult($optimizedContent, $tokenStats, $chunks, $options);

        } finally {
            // Cleanup temporary files
            $this->cleanup($zipPath, $extractedPath ?? null);
        }
    }

    private function selectDownloader(array $options): DownloaderInterface
    {
        return !empty($options['token']) 
            ? $this->privateDownloader 
            : $this->publicDownloader;
    }

    private function calculateTokenStatistics(array $content, array $options): array
    {
        $model = $options['model'] ?? config('gitingest.default_model', 'gpt-4');
        $totalTokens = 0;
        $fileStats = [];

        foreach ($content as $path => $fileData) {
            $tokens = $this->tokenCounter->countTokens($fileData['content'], $model);
            $totalTokens += $tokens;
            $fileStats[$path] = [
                'tokens' => $tokens,
                'size' => strlen($fileData['content']),
                'lines' => substr_count($fileData['content'], "\n") + 1,
            ];
        }

        return [
            'total_tokens' => $totalTokens,
            'total_files' => count($content),
            'model' => $model,
            'file_stats' => $fileStats,
            'model_limit' => $this->tokenCounter->getModelLimit($model),
            'exceeds_limit' => $totalTokens > $this->tokenCounter->getModelLimit($model),
        ];
    }

    private function shouldOptimize(array $tokenStats, array $options): bool
    {
        if (isset($options['optimization']['enabled'])) {
            return $options['optimization']['enabled'];
        }

        // Auto-optimize if content exceeds 75% of model limit
        $threshold = $tokenStats['model_limit'] * 0.75;
        return $tokenStats['total_tokens'] > $threshold;
    }

    private function shouldChunk(array $tokenStats, array $options): bool
    {
        if (isset($options['chunking']['enabled'])) {
            return $options['chunking']['enabled'];
        }

        // Auto-chunk if content exceeds model limit
        return $tokenStats['exceeds_limit'];
    }

    private function buildResult(
        array $content, 
        array $tokenStats, 
        ?array $chunks, 
        array $options
    ): array {
        $result = [
            'repository_url' => $options['repository_url'] ?? 'unknown',
            'processed_at' => now()->toISOString(),
            'statistics' => $tokenStats,
            'content' => $content,
        ];

        if ($chunks !== null) {
            $result['chunks'] = $chunks;
            $result['chunked'] = true;
            $result['chunk_count'] = count($chunks);
        } else {
            $result['chunked'] = false;
        }

        // Add metadata
        $result['metadata'] = [
            'processing_options' => $options,
            'package_version' => $this->getPackageVersion(),
            'processing_time' => $this->getProcessingTime(),
        ];

        return $result;
    }

    private function processOptions(array $options): array
    {
        return array_merge([
            'filter' => [
                'max_file_size' => config('gitingest.filter.max_file_size', 1024 * 1024),
                'allowed_extensions' => config('gitingest.filter.allowed_extensions', []),
                'ignored_directories' => config('gitingest.filter.ignored_directories', []),
            ],
            'format' => [
                'type' => config('gitingest.format.default', 'markdown'),
                'include_tree' => config('gitingest.format.include_tree', true),
                'add_separators' => config('gitingest.format.add_separators', true),
            ],
            'optimization' => [
                'level' => config('gitingest.optimization.default_level', 1),
                'preserve_structure' => config('gitingest.optimization.preserve_structure', true),
            ],
            'chunking' => [
                'strategy' => config('gitingest.chunking.default_strategy', 'semantic'),
                'max_tokens' => config('gitingest.chunking.max_tokens_per_chunk', 100000),
                'overlap' => config('gitingest.chunking.overlap_tokens', 1000),
            ],
        ], $options);
    }

    private function generateCacheKey(string $repositoryUrl, array $options): string
    {
        $keyData = [
            'url' => $repositoryUrl,
            'options' => $options,
            'version' => $this->getPackageVersion(),
        ];

        return 'gitingest:' . md5(serialize($keyData));
    }

    private function emitProgress(string $stage, float $percentage, string $message): void
    {
        $progressData = [
            'stage' => $stage,
            'percentage' => $percentage,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        foreach ($this->progressCallbacks as $callback) {
            try {
                $callback($progressData);
            } catch (Throwable $e) {
                Log::warning('Progress callback failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function cleanup(?string $zipPath, ?string $extractedPath): void
    {
        try {
            if ($zipPath && file_exists($zipPath)) {
                unlink($zipPath);
            }

            if ($extractedPath && is_dir($extractedPath)) {
                $this->removeDirectory($extractedPath);
            }
        } catch (Throwable $e) {
            Log::warning('Cleanup failed', [
                'zip_path' => $zipPath,
                'extracted_path' => $extractedPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function getPackageVersion(): string
    {
        return '1.0.0'; // TODO: Get from composer.json
    }

    private function getProcessingTime(): float
    {
        return microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    }
}
