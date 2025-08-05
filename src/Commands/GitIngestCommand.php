<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Ihasan\LaravelGitingest\Services\GitIngestService;
use Ihasan\LaravelGitingest\DataObjects\ProcessingResult;
use Ihasan\LaravelGitingest\Exceptions\GitIngestException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class GitIngestCommand extends Command
{
    protected $signature = 'gitingest:process 
                           {repository : The GitHub repository URL to process}
                           {--output= : Output file path (default: stdout)}
                           {--format=markdown : Output format (markdown, text, json)}
                           {--token= : GitHub access token for private repositories}
                           {--model=gpt-4 : AI model to optimize for (gpt-4, claude-3-opus, etc.)}
                           {--max-file-size=1048576 : Maximum file size in bytes}
                           {--allowed-extensions=* : Allowed file extensions (e.g., php,js,ts)}
                           {--ignored-directories=* : Directories to ignore}
                           {--optimization-level=1 : Content optimization level (0-3)}
                           {--chunk : Enable content chunking for large repositories}
                           {--chunk-size=100000 : Maximum tokens per chunk}
                           {--interactive : Run in interactive mode}
                           {--no-cache : Disable result caching}
                           {--cache-time=3600 : Cache time in seconds}';

    protected $description = 'Process a GitHub repository and extract its content for AI analysis';

    private ?ProgressBar $progressBar = null;
    private array $progressData = [];

    public function __construct(
        private readonly GitIngestService $gitIngestService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->showHeader();

            if ($this->option('interactive')) {
                return $this->handleInteractiveMode();
            }

            return $this->handleNonInteractiveMode();

        } catch (GitIngestException $e) {
            $this->error("GitIngest Error: {$e->getMessage()}");
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;

        } catch (Throwable $e) {
            $this->error("Unexpected error: {$e->getMessage()}");
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    private function handleInteractiveMode(): int
    {
        $this->info('🎯 Interactive GitIngest Mode');
        $this->newLine();

        // Get repository URL
        $repository = $this->argument('repository') ?: $this->ask('Repository URL');
        
        if (!$this->validateRepositoryUrl($repository)) {
            return self::FAILURE;
        }

        // Get configuration options
        $options = $this->gatherInteractiveOptions();

        return $this->processRepository($repository, $options);
    }

    private function handleNonInteractiveMode(): int
    {
        $repository = $this->argument('repository');
        
        if (!$this->validateRepositoryUrl($repository)) {
            return self::FAILURE;
        }

        $options = $this->buildOptionsFromArguments();

        return $this->processRepository($repository, $options);
    }

    private function processRepository(string $repository, array $options): int
    {
        $this->info("🚀 Processing repository: {$repository}");
        $this->newLine();

        // Setup progress tracking
        $this->setupProgressTracking();

        try {
            $result = $this->gitIngestService
                ->onProgress([$this, 'handleProgress'])
                ->setCacheEnabled(!$this->option('no-cache'))
                ->setCacheTime((int) $this->option('cache-time'))
                ->processRepository($repository, $options);

            $this->finishProgressTracking();
            
            return $this->handleResult($result, $options);

        } catch (Throwable $e) {
            $this->finishProgressTracking();
            throw $e;
        }
    }

    private function handleResult(ProcessingResult $result, array $options): int
    {
        $this->newLine();
        
        if (!$result->isSuccessful()) {
            $this->error('❌ Processing completed with errors:');
            foreach ($result->getErrors() as $error) {
                $this->line("  • {$error}");
            }
            $this->newLine();
        } else {
            $this->info('✅ Processing completed successfully!');
            $this->newLine();
        }

        // Show summary
        $this->showSummary($result);

        // Output result
        return $this->outputResult($result, $options);
    }

    private function outputResult(ProcessingResult $result, array $options): int
    {
        $outputPath = $this->option('output');
        $format = $options['format']['type'] ?? 'markdown';

        try {
            if ($outputPath) {
                return $this->outputToFile($result, $outputPath, $format);
            } else {
                return $this->outputToStdout($result, $format);
            }
        } catch (Throwable $e) {
            $this->error("Failed to write output: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function outputToFile(ProcessingResult $result, string $outputPath, string $format): int
    {
        $content = $this->formatOutput($result, $format);
        
        // Ensure directory exists
        $directory = dirname($outputPath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($outputPath, $content);
        
        $this->info("📄 Output written to: {$outputPath}");
        $this->line("   Size: " . $this->formatBytes(strlen($content)));
        
        return self::SUCCESS;
    }

    private function outputToStdout(ProcessingResult $result, string $format): int
    {
        if ($format === 'json') {
            $this->line($result->toPrettyJson());
        } else {
            // For markdown/text, show the actual content
            foreach ($result->content as $path => $fileData) {
                $this->comment("File: {$path}");
                $this->line($fileData['content']);
                $this->newLine();
            }
        }
        
        return self::SUCCESS;
    }

    private function formatOutput(ProcessingResult $result, string $format): string
    {
        return match ($format) {
            'json' => $result->toPrettyJson(),
            'text' => $this->formatAsText($result),
            default => $this->formatAsMarkdown($result), // 'markdown'
        };
    }

    private function formatAsMarkdown(ProcessingResult $result): string
    {
        $content = "# Repository Analysis\n\n";
        $content .= "**Repository:** {$result->repositoryUrl}\n";
        $content .= "**Processed:** {$result->metadata->processedAt->format('Y-m-d H:i:s')}\n";
        $content .= "**Files:** {$result->getFileCount()}\n";
        $content .= "**Tokens:** {$result->statistics->totalTokens}\n\n";

        if ($result->isChunked) {
            $content .= "## Chunks\n\n";
            $content .= "This repository was split into {$result->getChunkCount()} chunks:\n\n";
            
            foreach ($result->getChunks() ?? collect() as $chunk) {
                $content .= "### Chunk {$chunk->chunkId}\n";
                $content .= "- **Tokens:** {$chunk->tokens}\n";
                $content .= "- **Files:** {$chunk->getFileCount()}\n\n";
                $content .= "```\n{$chunk->content}\n```\n\n";
            }
        } else {
            $content .= "## Files\n\n";
            foreach ($result->content as $path => $fileData) {
                $content .= "### {$path}\n\n";
                $content .= "```\n{$fileData['content']}\n```\n\n";
            }
        }

        return $content;
    }

    private function formatAsText(ProcessingResult $result): string
    {
        $content = "Repository Analysis\n";
        $content .= str_repeat('=', 50) . "\n\n";
        $content .= "Repository: {$result->repositoryUrl}\n";
        $content .= "Processed: {$result->metadata->processedAt->format('Y-m-d H:i:s')}\n";
        $content .= "Files: {$result->getFileCount()}\n";
        $content .= "Tokens: {$result->statistics->totalTokens}\n\n";

        foreach ($result->content as $path => $fileData) {
            $content .= "File: {$path}\n";
            $content .= str_repeat('-', 30) . "\n";
            $content .= $fileData['content'] . "\n\n";
        }

        return $content;
    }

    private function gatherInteractiveOptions(): array
    {
        $options = [];

        // Output options
        $options['format'] = [
            'type' => $this->choice('Output format', ['markdown', 'text', 'json'], 'markdown'),
        ];

        // Model selection
        $options['model'] = $this->choice(
            'AI model to optimize for',
            ['gpt-4', 'gpt-4-turbo', 'claude-3-opus', 'claude-3-sonnet', 'gpt-3.5-turbo'],
            'gpt-4'
        );

        // File filtering
        if ($this->confirm('Configure file filtering?', false)) {
            $options['filter'] = $this->gatherFilterOptions();
        }

        // Optimization
        if ($this->confirm('Enable content optimization?', true)) {
            $options['optimization'] = [
                'enabled' => true,
                'level' => (int) $this->choice('Optimization level', ['0', '1', '2', '3'], '1'),
            ];
        }

        // Chunking
        if ($this->confirm('Enable content chunking for large repositories?', false)) {
            $options['chunking'] = [
                'enabled' => true,
                'max_tokens' => (int) $this->ask('Maximum tokens per chunk', '100000'),
                'strategy' => $this->choice('Chunking strategy', ['semantic', 'file', 'size'], 'semantic'),
            ];
        }

        return $options;
    }

    private function gatherFilterOptions(): array
    {
        $filter = [];

        if ($extensions = $this->ask('Allowed file extensions (comma-separated, e.g., php,js,ts)')) {
            $filter['allowed_extensions'] = array_map('trim', explode(',', $extensions));
        }

        if ($ignored = $this->ask('Ignored directories (comma-separated, e.g., node_modules,vendor)')) {
            $filter['ignored_directories'] = array_map('trim', explode(',', $ignored));
        }

        $filter['max_file_size'] = (int) $this->ask('Maximum file size (bytes)', '1048576');

        return $filter;
    }

    private function buildOptionsFromArguments(): array
    {
        $options = [
            'model' => $this->option('model'),
            'format' => [
                'type' => $this->option('format'),
            ],
            'filter' => [
                'max_file_size' => (int) $this->option('max-file-size'),
            ],
            'optimization' => [
                'level' => (int) $this->option('optimization-level'),
            ],
        ];

        // Add token if provided
        if ($token = $this->option('token')) {
            $options['token'] = $token;
        }

        // Add file extensions if provided
        if ($extensions = $this->option('allowed-extensions')) {
            $options['filter']['allowed_extensions'] = is_array($extensions) ? $extensions : [$extensions];
        }

        // Add ignored directories if provided
        if ($ignored = $this->option('ignored-directories')) {
            $options['filter']['ignored_directories'] = is_array($ignored) ? $ignored : [$ignored];
        }

        // Add chunking options if enabled
        if ($this->option('chunk')) {
            $options['chunking'] = [
                'enabled' => true,
                'max_tokens' => (int) $this->option('chunk-size'),
                'strategy' => 'semantic',
            ];
        }

        return $options;
    }

    private function validateRepositoryUrl(string $repository): bool
    {
        if (empty($repository)) {
            $this->error('Repository URL is required');
            return false;
        }

        // Basic GitHub URL validation
        if (!preg_match('/^https:\/\/github\.com\/[\w\-\.]+\/[\w\-\.]+/', $repository)) {
            $this->error('Invalid GitHub repository URL format');
            $this->line('Expected format: https://github.com/owner/repository');
            return false;
        }

        return true;
    }

    private function setupProgressTracking(): void
    {
        if ($this->output->isQuiet()) {
            return;
        }

        $this->progressBar = $this->output->createProgressBar(100);
        $this->progressBar->setFormat(' %current%% [%bar%] %message%');
        $this->progressBar->setMessage('Starting...');
        $this->progressBar->start();
    }

    public function handleProgress(array $progressData): void
    {
        $this->progressData = $progressData;

        if ($this->progressBar && !$this->output->isQuiet()) {
            $this->progressBar->setProgress((int) $progressData['percentage']);
            $this->progressBar->setMessage($progressData['message']);
        }

        if ($this->output->isVerbose()) {
            $timestamp = $progressData['timestamp'];
            $stage = $progressData['stage'];
            $message = $progressData['message'];
            $this->line("[{$timestamp}] {$stage}: {$message}");
        }
    }

    private function finishProgressTracking(): void
    {
        if ($this->progressBar && !$this->output->isQuiet()) {
            $this->progressBar->finish();
            $this->newLine(2);
        }
    }

    private function showHeader(): void
    {
        if ($this->output->isQuiet()) {
            return;
        }

        $this->line('');
        $this->line(' ╭─────────────────────────────────────╮');
        $this->line(' │            GitIngest CLI            │');
        $this->line(' │     Repository Content Extractor   │');
        $this->line(' ╰─────────────────────────────────────╯');
        $this->line('');
    }

    private function showSummary(ProcessingResult $result): void
    {
        if ($this->output->isQuiet()) {
            return;
        }

        $this->info('📊 Processing Summary');
        $this->line("   Repository: {$result->repositoryUrl}");
        $this->line("   Files processed: {$result->getFileCount()}");
        $this->line("   Total tokens: {$result->statistics->totalTokens:n}");
        $this->line("   Model: {$result->statistics->model}");
        $this->line("   Processing time: {$result->metadata->getFormattedProcessingTime()}");
        $this->line("   Memory usage: {$result->metadata->getFormattedMemoryUsage()}");

        if ($result->isChunked) {
            $this->line("   Chunks created: {$result->getChunkCount()}");
        }

        if ($result->wasOptimized) {
            $this->line('   ✅ Content was optimized');
        }

        $utilizationPercent = $result->statistics->getUtilizationPercentage();
        $utilizationColor = $utilizationPercent > 90 ? 'error' : ($utilizationPercent > 75 ? 'warn' : 'info');
        $this->{$utilizationColor}("   Token utilization: {$utilizationPercent:.1f}%");

        $this->newLine();
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
