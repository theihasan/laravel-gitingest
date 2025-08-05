<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Ihasan\LaravelGitingest\Services\Downloaders\PublicRepositoryDownloader;
use Ihasan\LaravelGitingest\Services\Downloaders\PrivateRepositoryDownloader;
use Ihasan\LaravelGitingest\Services\Processors\ZipProcessor;
use Ihasan\LaravelGitingest\Services\FileFilter;
use Ihasan\LaravelGitingest\Services\TokenCounter;
use Ihasan\LaravelGitingest\Contracts\DownloaderInterface;
use Ihasan\LaravelGitingest\Exceptions\GitIngestException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Carbon\Carbon;
use Throwable;

final class AnalyzeRepositoryCommand extends Command
{
    protected $signature = 'gitingest:analyze 
                           {repositories* : GitHub repository URLs to analyze (supports multiple)}
                           {--token= : GitHub access token for private repositories}
                           {--model=gpt-4 : AI model to estimate for (gpt-4, claude-3-opus, etc.)}
                           {--output= : Output file path for analysis report}
                           {--format=table : Output format (table, json, csv, markdown)}
                           {--max-file-size=1048576 : Maximum file size in bytes for filtering}
                           {--allowed-extensions=* : Allowed file extensions for filtering}
                           {--ignored-directories=* : Directories to ignore for filtering}
                           {--include-costs : Include API cost estimations}
                           {--sample-size=50 : Number of files to sample for token estimation}
                           {--detailed : Show detailed file-by-file analysis}
                           {--preview-filters : Preview filtering results}';

    protected $description = 'Analyze GitHub repositories without full processing - get statistics, estimates, and insights';

    public function __construct(
        private readonly PublicRepositoryDownloader $publicDownloader,
        private readonly PrivateRepositoryDownloader $privateDownloader,
        private readonly ZipProcessor $zipProcessor,
        private readonly FileFilter $fileFilter,
        private readonly TokenCounter $tokenCounter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->showHeader();

            $repositories = $this->argument('repositories');
            
            if (empty($repositories)) {
                $this->error('At least one repository URL is required');
                return self::FAILURE;
            }

            $results = collect();
            
            foreach ($repositories as $repository) {
                if (!$this->validateRepositoryUrl($repository)) {
                    continue;
                }

                $this->info("🔍 Analyzing: {$repository}");
                
                try {
                    $analysis = $this->analyzeRepository($repository);
                    $results->push($analysis);
                    
                    if (count($repositories) === 1) {
                        $this->displaySingleAnalysis($analysis);
                    }
                    
                } catch (Throwable $e) {
                    $this->error("Failed to analyze {$repository}: {$e->getMessage()}");
                    if ($this->output->isVerbose()) {
                        $this->line($e->getTraceAsString());
                    }
                }

                $this->newLine();
            }

            if (count($repositories) > 1) {
                $this->displayBatchAnalysis($results);
            }

            return $this->handleOutput($results);

        } catch (GitIngestException $e) {
            $this->error("GitIngest Error: {$e->getMessage()}");
            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error("Unexpected error: {$e->getMessage()}");
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    private function analyzeRepository(string $repository): array
    {
        $startTime = microtime(true);
        
        // Step 1: Download repository
        $downloader = $this->selectDownloader();
        $options = $this->buildAnalysisOptions();
        $zipPath = $downloader->download($repository, $options);

        try {
            // Step 2: Extract and get file list
            $extractPromise = $this->zipProcessor->extractAndProcess($zipPath, $options);
            $files = null;
            
            $extractPromise->then(function($extractedFiles) use (&$files) {
                $files = $extractedFiles;
            });

            // Step 3: Analyze files
            $analysis = $this->performAnalysis($repository, $files, $options);
            $analysis['download_time'] = microtime(true) - $startTime;

            return $analysis;

        } finally {
            // Cleanup
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    private function performAnalysis(string $repository, Collection $files, array $options): array
    {
        $analysis = [
            'repository' => $repository,
            'analyzed_at' => Carbon::now()->toISOString(),
            'total_files' => $files->count(),
            'total_size' => 0,
            'file_types' => [],
            'size_distribution' => [],
            'filtered_analysis' => null,
            'token_estimation' => null,
            'cost_estimation' => null,
            'processing_estimates' => [],
        ];

        // Basic file analysis
        $totalSize = 0;
        $fileTypes = [];
        $sizeRanges = [
            'tiny' => ['max' => 1024, 'count' => 0, 'size' => 0],        // < 1KB
            'small' => ['max' => 10240, 'count' => 0, 'size' => 0],      // < 10KB
            'medium' => ['max' => 102400, 'count' => 0, 'size' => 0],    // < 100KB
            'large' => ['max' => 1048576, 'count' => 0, 'size' => 0],    // < 1MB
            'huge' => ['max' => PHP_INT_MAX, 'count' => 0, 'size' => 0], // >= 1MB
        ];

        foreach ($files as $path => $fileData) {
            $size = strlen($fileData['content'] ?? '');
            $totalSize += $size;
            
            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'no-extension';
            $fileTypes[$extension] = ($fileTypes[$extension] ?? 0) + 1;

            // Categorize by size
            foreach ($sizeRanges as $range => &$data) {
                if ($size < $data['max']) {
                    $data['count']++;
                    $data['size'] += $size;
                    break;
                }
            }
        }

        $analysis['total_size'] = $totalSize;
        $analysis['file_types'] = $fileTypes;
        $analysis['size_distribution'] = $sizeRanges;

        // Filter analysis
        if ($this->option('preview-filters')) {
            $analysis['filtered_analysis'] = $this->analyzeFiltering($files, $options);
        }

        // Token estimation
        $analysis['token_estimation'] = $this->estimateTokens($files, $options);

        // Cost estimation
        if ($this->option('include-costs')) {
            $analysis['cost_estimation'] = $this->estimateCosts($analysis['token_estimation'], $options);
        }

        // Processing time estimates
        $analysis['processing_estimates'] = $this->estimateProcessingTime($analysis);

        return $analysis;
    }

    private function analyzeFiltering(Collection $files, array $options): array
    {
        $originalCount = $files->count();
        $originalSize = $files->sum(fn($fileData) => strlen($fileData['content'] ?? ''));

        // Apply filters
        $filteredFiles = $this->fileFilter->filterFiles($files, $options['filter'] ?? []);
        
        $filteredCount = $filteredFiles->count();
        $filteredSize = $filteredFiles->sum(fn($fileData) => strlen($fileData['content'] ?? ''));

        $removedFiles = $files->keys()->diff($filteredFiles->keys());

        return [
            'original' => [
                'files' => $originalCount,
                'size' => $originalSize,
            ],
            'filtered' => [
                'files' => $filteredCount,
                'size' => $filteredSize,
            ],
            'removed' => [
                'files' => $originalCount - $filteredCount,
                'size' => $originalSize - $filteredSize,
                'percentage' => $originalCount > 0 ? (($originalCount - $filteredCount) / $originalCount) * 100 : 0,
            ],
            'removed_files' => $removedFiles->take(20)->toArray(), // Show first 20 removed files
            'removed_reasons' => $this->categorizeRemovedFiles($files, $filteredFiles, $options),
        ];
    }

    private function estimateTokens(Collection $files, array $options): array
    {
        $model = $options['model'] ?? 'gpt-4';
        $sampleSize = min((int) $this->option('sample-size'), $files->count());
        
        if ($sampleSize === 0) {
            return [
                'estimated_total' => 0,
                'confidence' => 'high',
                'sample_size' => 0,
                'method' => 'none',
            ];
        }

        // Sample files for estimation
        $sampledFiles = $files->random($sampleSize);
        $sampleTokens = 0;
        $sampleSize = 0;

        foreach ($sampledFiles as $fileData) {
            $content = $fileData['content'] ?? '';
            $size = strlen($content);
            
            if ($size > 0) {
                $tokens = $this->tokenCounter->countTokens($content, $model);
                $sampleTokens += $tokens;
                $sampleSize += $size;
            }
        }

        $totalSize = $files->sum(fn($fileData) => strlen($fileData['content'] ?? ''));
        $tokensPerByte = $sampleSize > 0 ? $sampleTokens / $sampleSize : 0;
        $estimatedTotal = (int) ($totalSize * $tokensPerByte);

        // Determine confidence level
        $confidence = match (true) {
            $sampleSize >= $files->count() => 'high',
            $sampleSize >= $files->count() * 0.5 => 'medium',
            $sampleSize >= 10 => 'low',
            default => 'very-low',
        };

        return [
            'estimated_total' => $estimatedTotal,
            'tokens_per_byte' => $tokensPerByte,
            'sample_size' => $sampledFiles->count(),
            'sample_tokens' => $sampleTokens,
            'sample_bytes' => $sampleSize,
            'confidence' => $confidence,
            'method' => 'sampling',
            'model' => $model,
        ];
    }

    private function estimateCosts(array $tokenEstimation, array $options): array
    {
        $model = $options['model'] ?? 'gpt-4';
        $estimatedTokens = $tokenEstimation['estimated_total'] ?? 0;

        // Cost per 1K tokens (as of 2024 - these should be configurable)
        $costs = [
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
            'gpt-3.5-turbo' => ['input' => 0.0015, 'output' => 0.002],
            'claude-3-opus' => ['input' => 0.015, 'output' => 0.075],
            'claude-3-sonnet' => ['input' => 0.003, 'output' => 0.015],
        ];

        $modelCosts = $costs[$model] ?? $costs['gpt-4'];
        $inputCost = ($estimatedTokens / 1000) * $modelCosts['input'];

        // Estimate output tokens (typically 10-20% of input for analysis tasks)
        $estimatedOutputTokens = $estimatedTokens * 0.15;
        $outputCost = ($estimatedOutputTokens / 1000) * $modelCosts['output'];

        return [
            'model' => $model,
            'input_tokens' => $estimatedTokens,
            'estimated_output_tokens' => (int) $estimatedOutputTokens,
            'input_cost_usd' => round($inputCost, 4),
            'output_cost_usd' => round($outputCost, 4),
            'total_cost_usd' => round($inputCost + $outputCost, 4),
            'cost_per_1k_tokens' => $modelCosts,
        ];
    }

    private function estimateProcessingTime(array $analysis): array
    {
        $fileCount = $analysis['total_files'];
        $totalSize = $analysis['total_size'];
        $estimatedTokens = $analysis['token_estimation']['estimated_total'] ?? 0;

        // Time estimates based on empirical data (these should be refined)
        $downloadTime = $analysis['download_time'] ?? 1.0;
        $extractionTime = $fileCount * 0.001; // ~1ms per file
        $tokenCountingTime = $estimatedTokens * 0.00001; // ~10μs per token
        $optimizationTime = $totalSize * 0.000001; // ~1μs per byte
        $chunkingTime = $estimatedTokens > 100000 ? $estimatedTokens * 0.00001 : 0;

        $totalEstimatedTime = $downloadTime + $extractionTime + $tokenCountingTime + $optimizationTime + $chunkingTime;

        return [
            'download' => round($downloadTime, 2),
            'extraction' => round($extractionTime, 2),
            'token_counting' => round($tokenCountingTime, 2),
            'optimization' => round($optimizationTime, 2),
            'chunking' => round($chunkingTime, 2),
            'total_estimated' => round($totalEstimatedTime, 2),
            'unit' => 'seconds',
        ];
    }

    private function categorizeRemovedFiles(Collection $originalFiles, Collection $filteredFiles, array $options): array
    {
        $removedFiles = $originalFiles->keys()->diff($filteredFiles->keys());
        $reasons = [
            'size_too_large' => 0,
            'extension_not_allowed' => 0,
            'directory_ignored' => 0,
            'gitignore_pattern' => 0,
        ];

        $maxSize = $options['filter']['max_file_size'] ?? 1048576;
        $allowedExtensions = $options['filter']['allowed_extensions'] ?? [];
        $ignoredDirectories = $options['filter']['ignored_directories'] ?? [];

        foreach ($removedFiles as $path) {
            $fileData = $originalFiles[$path];
            $size = strlen($fileData['content'] ?? '');
            $extension = pathinfo($path, PATHINFO_EXTENSION);

            if ($size > $maxSize) {
                $reasons['size_too_large']++;
            } elseif (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions)) {
                $reasons['extension_not_allowed']++;
            } elseif (!empty($ignoredDirectories) && $this->pathInIgnoredDirectory($path, $ignoredDirectories)) {
                $reasons['directory_ignored']++;
            } else {
                $reasons['gitignore_pattern']++;
            }
        }

        return $reasons;
    }

    private function pathInIgnoredDirectory(string $path, array $ignoredDirectories): bool
    {
        foreach ($ignoredDirectories as $dir) {
            if (str_starts_with($path, $dir . '/') || $path === $dir) {
                return true;
            }
        }
        return false;
    }

    private function displaySingleAnalysis(array $analysis): void
    {
        $this->info('📊 Repository Analysis Results');
        $this->newLine();

        // Basic Statistics Table
        $table = new Table($this->output);
        $table->setHeaders(['Metric', 'Value']);
        $table->addRows([
            ['Repository', $analysis['repository']],
            ['Total Files', number_format($analysis['total_files'])],
            ['Total Size', $this->formatBytes($analysis['total_size'])],
            ['Analyzed At', $analysis['analyzed_at']],
            new TableSeparator(),
            ['Est. Tokens', number_format($analysis['token_estimation']['estimated_total'])],
            ['Token Confidence', ucfirst($analysis['token_estimation']['confidence'])],
            ['Est. Processing Time', $analysis['processing_estimates']['total_estimated'] . 's'],
        ]);

        if (!empty($analysis['cost_estimation'])) {
            $table->addRows([
                new TableSeparator(),
                ['Est. Cost (USD)', '$' . $analysis['cost_estimation']['total_cost_usd']],
                ['Model', $analysis['cost_estimation']['model']],
            ]);
        }

        $table->render();
        $this->newLine();

        // File Types Distribution
        if (!empty($analysis['file_types'])) {
            $this->info('📁 File Types Distribution');
            $typeTable = new Table($this->output);
            $typeTable->setHeaders(['Extension', 'Count', 'Percentage']);
            
            $total = $analysis['total_files'];
            arsort($analysis['file_types']);
            
            foreach (array_slice($analysis['file_types'], 0, 10) as $ext => $count) {
                $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                $typeTable->addRow([$ext ?: '(no ext)', $count, $percentage . '%']);
            }
            
            $typeTable->render();
            $this->newLine();
        }

        // Size Distribution
        $this->info('📏 Size Distribution');
        $sizeTable = new Table($this->output);
        $sizeTable->setHeaders(['Category', 'Files', 'Total Size', 'Avg Size']);
        
        foreach ($analysis['size_distribution'] as $category => $data) {
            if ($data['count'] > 0) {
                $avgSize = $data['count'] > 0 ? $data['size'] / $data['count'] : 0;
                $sizeTable->addRow([
                    ucfirst($category),
                    number_format($data['count']),
                    $this->formatBytes($data['size']),
                    $this->formatBytes((int) $avgSize),
                ]);
            }
        }
        
        $sizeTable->render();
        $this->newLine();

        // Filtering Analysis
        if ($this->option('preview-filters') && !empty($analysis['filtered_analysis'])) {
            $this->displayFilteringAnalysis($analysis['filtered_analysis']);
        }
    }

    private function displayFilteringAnalysis(array $filterAnalysis): void
    {
        $this->info('🔍 Filtering Preview');
        
        $filterTable = new Table($this->output);
        $filterTable->setHeaders(['Metric', 'Original', 'After Filtering', 'Removed']);
        $filterTable->addRows([
            [
                'Files',
                number_format($filterAnalysis['original']['files']),
                number_format($filterAnalysis['filtered']['files']),
                number_format($filterAnalysis['removed']['files']) . ' (' . round($filterAnalysis['removed']['percentage'], 1) . '%)',
            ],
            [
                'Size',
                $this->formatBytes($filterAnalysis['original']['size']),
                $this->formatBytes($filterAnalysis['filtered']['size']),
                $this->formatBytes($filterAnalysis['removed']['size']),
            ],
        ]);
        
        $filterTable->render();
        $this->newLine();

        // Show removal reasons
        if (!empty($filterAnalysis['removed_reasons'])) {
            $this->comment('Removal Reasons:');
            foreach ($filterAnalysis['removed_reasons'] as $reason => $count) {
                if ($count > 0) {
                    $this->line("  • " . str_replace('_', ' ', ucfirst($reason)) . ": {$count} files");
                }
            }
            $this->newLine();
        }
    }

    private function displayBatchAnalysis(Collection $results): void
    {
        $this->info('📊 Batch Analysis Summary');
        $this->newLine();

        $table = new Table($this->output);
        $table->setHeaders(['Repository', 'Files', 'Size', 'Est. Tokens', 'Est. Time', 'Est. Cost']);

        foreach ($results as $analysis) {
            $table->addRow([
                basename($analysis['repository']),
                number_format($analysis['total_files']),
                $this->formatBytes($analysis['total_size']),
                number_format($analysis['token_estimation']['estimated_total']),
                $analysis['processing_estimates']['total_estimated'] . 's',
                isset($analysis['cost_estimation']) ? '$' . $analysis['cost_estimation']['total_cost_usd'] : 'N/A',
            ]);
        }

        $table->render();
        $this->newLine();

        // Summary totals
        $totalFiles = $results->sum('total_files');
        $totalSize = $results->sum('total_size');
        $totalTokens = $results->sum('token_estimation.estimated_total');
        $totalCost = $results->sum('cost_estimation.total_cost_usd');

        $this->info('📈 Totals:');
        $this->line("  Files: " . number_format($totalFiles));
        $this->line("  Size: " . $this->formatBytes($totalSize));
        $this->line("  Est. Tokens: " . number_format($totalTokens));
        if ($totalCost > 0) {
            $this->line("  Est. Cost: $" . round($totalCost, 4));
        }
    }

    private function handleOutput(Collection $results): int
    {
        $outputPath = $this->option('output');
        
        if (!$outputPath) {
            return self::SUCCESS;
        }

        $format = $this->option('format');
        $content = $this->formatAnalysisOutput($results, $format);

        try {
            $directory = dirname($outputPath);
            if (!File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            File::put($outputPath, $content);
            $this->info("📄 Analysis report saved to: {$outputPath}");
            
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to save analysis report: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function formatAnalysisOutput(Collection $results, string $format): string
    {
        return match ($format) {
            'json' => $results->toJson(JSON_PRETTY_PRINT),
            'csv' => $this->formatAsCsv($results),
            'markdown' => $this->formatAsMarkdown($results),
            default => $this->formatAsTable($results),
        };
    }

    private function formatAsCsv(Collection $results): string
    {
        $csv = "Repository,Files,Size (bytes),Est. Tokens,Est. Time (s),Est. Cost (USD)\n";
        
        foreach ($results as $analysis) {
            $csv .= sprintf(
                "%s,%d,%d,%d,%.2f,%.4f\n",
                $analysis['repository'],
                $analysis['total_files'],
                $analysis['total_size'],
                $analysis['token_estimation']['estimated_total'],
                $analysis['processing_estimates']['total_estimated'],
                $analysis['cost_estimation']['total_cost_usd'] ?? 0
            );
        }

        return $csv;
    }

    private function formatAsMarkdown(Collection $results): string
    {
        $md = "# Repository Analysis Report\n\n";
        $md .= "Generated: " . Carbon::now()->format('Y-m-d H:i:s') . "\n\n";

        foreach ($results as $analysis) {
            $md .= "## " . basename($analysis['repository']) . "\n\n";
            $md .= "- **URL:** {$analysis['repository']}\n";
            $md .= "- **Files:** " . number_format($analysis['total_files']) . "\n";
            $md .= "- **Size:** " . $this->formatBytes($analysis['total_size']) . "\n";
            $md .= "- **Est. Tokens:** " . number_format($analysis['token_estimation']['estimated_total']) . "\n";
            $md .= "- **Est. Processing Time:** {$analysis['processing_estimates']['total_estimated']}s\n";
            
            if (!empty($analysis['cost_estimation'])) {
                $md .= "- **Est. Cost:** $" . $analysis['cost_estimation']['total_cost_usd'] . "\n";
            }
            
            $md .= "\n";
        }

        return $md;
    }

    private function formatAsTable(Collection $results): string
    {
        // Simple text table format
        $output = "Repository Analysis Report\n";
        $output .= str_repeat('=', 80) . "\n\n";

        foreach ($results as $analysis) {
            $output .= "Repository: {$analysis['repository']}\n";
            $output .= "Files: " . number_format($analysis['total_files']) . "\n";
            $output .= "Size: " . $this->formatBytes($analysis['total_size']) . "\n";
            $output .= "Est. Tokens: " . number_format($analysis['token_estimation']['estimated_total']) . "\n";
            $output .= "Est. Time: {$analysis['processing_estimates']['total_estimated']}s\n";
            
            if (!empty($analysis['cost_estimation'])) {
                $output .= "Est. Cost: $" . $analysis['cost_estimation']['total_cost_usd'] . "\n";
            }
            
            $output .= str_repeat('-', 40) . "\n";
        }

        return $output;
    }

    private function buildAnalysisOptions(): array
    {
        $options = [
            'model' => $this->option('model'),
            'filter' => [
                'max_file_size' => (int) $this->option('max-file-size'),
            ],
        ];

        if ($extensions = $this->option('allowed-extensions')) {
            $options['filter']['allowed_extensions'] = is_array($extensions) ? $extensions : [$extensions];
        }

        if ($ignored = $this->option('ignored-directories')) {
            $options['filter']['ignored_directories'] = is_array($ignored) ? $ignored : [$ignored];
        }

        if ($token = $this->option('token')) {
            $options['token'] = $token;
        }

        return $options;
    }

    private function selectDownloader(): DownloaderInterface
    {
        return $this->option('token') ? $this->privateDownloader : $this->publicDownloader;
    }

    private function validateRepositoryUrl(string $repository): bool
    {
        if (empty($repository)) {
            $this->error('Repository URL cannot be empty');
            return false;
        }

        if (!preg_match('/^https:\/\/github\.com\/[\w\-\.]+\/[\w\-\.]+/', $repository)) {
            $this->error("Invalid GitHub repository URL: {$repository}");
            return false;
        }

        return true;
    }

    private function showHeader(): void
    {
        if ($this->output->isQuiet()) {
            return;
        }

        $this->line('');
        $this->line(' ╭─────────────────────────────────────╮');
        $this->line(' │         Repository Analyzer        │');
        $this->line(' │      Quick Stats & Estimates       │');
        $this->line(' ╰─────────────────────────────────────╯');
        $this->line('');
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
