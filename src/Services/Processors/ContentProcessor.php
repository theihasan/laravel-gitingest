<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Services\Processors;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Generator;

final readonly class ContentProcessor
{
    public function __construct(
        private array $config,
        private int $batchSize = 10,
    ) {}

    public function processFiles(Collection $files, string $format = 'markdown'): string
    {
        return match ($format) {
            'markdown' => $this->generateMarkdownOutput($files),
            'text' => $this->generateTextOutput($files),
            'json' => $this->generateJsonOutput($files),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    public function generateDirectoryTree(Collection $files): string
    {
        $tree = [];
        
        $files->each(function (array $fileData) use (&$tree): void {
            $path = $fileData['relative_path'] ?? $fileData['path'];
            $parts = explode(DIRECTORY_SEPARATOR, $path);
            $this->addToTree($tree, $parts);
        });

        return $this->renderTree($tree);
    }

    public function processInBatches(Collection $files): Generator
    {
        foreach ($files->chunk($this->batchSize) as $batch) {
            yield $this->processBatch($batch);
        }
    }

    private function generateMarkdownOutput(Collection $files): string
    {
        $output = "# Repository Contents\n\n";
        $output .= "## Directory Structure\n\n";
        $output .= "```\n" . $this->generateDirectoryTree($files) . "\n```\n\n";
        $output .= "## File Contents\n\n";
        
        foreach ($this->processInBatches($files) as $batchContent) {
            $output .= $batchContent;
        }

        return $output;
    }

    private function generateTextOutput(Collection $files): string
    {
        $output = "REPOSITORY CONTENTS\n";
        $output .= str_repeat("=", 50) . "\n\n";
        $output .= "DIRECTORY STRUCTURE:\n\n";
        $output .= $this->generateDirectoryTree($files) . "\n\n";
        $output .= "FILE CONTENTS:\n";
        $output .= str_repeat("-", 50) . "\n\n";
        
        foreach ($this->processInBatches($files) as $batchContent) {
            $output .= $batchContent;
        }

        return $output;
    }

    private function generateJsonOutput(Collection $files): string
    {
        $data = [
            'repository' => [
                'total_files' => $files->count(),
                'total_size' => $files->sum(fn(array $file): int => $file['size'] ?? 0),
                'directory_tree' => $this->generateDirectoryTree($files),
            ],
            'files' => $files->map(fn(array $file): array => [
                'path' => $file['relative_path'] ?? $file['path'],
                'size' => $file['size'] ?? 0,
                'lines' => $file['lines'] ?? 0,
                'extension' => $file['extension'] ?? '',
                'content' => $this->detectAndConvertEncoding($file['content'] ?? ''),
            ])->values()->toArray(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function processBatch(Collection $batch): string
    {
        return $batch
            ->map(fn(array $file): string => $this->formatFileContent($file))
            ->join("\n\n");
    }

    private function formatFileContent(array $file): string
    {
        $path = $file['relative_path'] ?? $file['path'];
        $content = $this->detectAndConvertEncoding($file['content'] ?? '');
        $extension = $file['extension'] ?? '';
        $size = $file['size'] ?? 0;
        $lines = $file['lines'] ?? 0;

        $header = $this->generateFileHeader($path, $extension, $size, $lines);
        $formattedContent = $this->formatContentByType($content, $extension);

        return $header . "\n" . $formattedContent;
    }

    private function generateFileHeader(string $path, string $extension, int $size, int $lines): string
    {
        $sizeFormatted = $this->formatFileSize($size);
        
        return "### {$path}\n" .
               "*{$extension} • {$sizeFormatted} • {$lines} lines*\n" .
               "```{$extension}";
    }

    private function formatContentByType(string $content, string $extension): string
    {
        $content = $this->sanitizeContent($content);
        
        return match ($extension) {
            'md', 'markdown' => $this->formatMarkdownContent($content),
            'json' => $this->formatJsonContent($content),
            'xml' => $this->formatXmlContent($content),
            default => $content . "\n```",
        };
    }

    private function formatMarkdownContent(string $content): string
    {
        // Escape markdown to prevent rendering issues
        return str_replace(['```', '~~~'], ['\\```', '\\~~~'], $content) . "\n```";
    }

    private function formatJsonContent(string $content): string
    {
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```";
        }
        
        return $content . "\n```";
    }

    private function formatXmlContent(string $content): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        
        if (@$dom->loadXML($content)) {
            return $dom->saveXML() . "\n```";
        }
        
        return $content . "\n```";
    }

    private function sanitizeContent(string $content): string
    {
        // Remove or replace potentially problematic characters
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        
        // Limit very long lines
        $maxLineLength = Arr::get($this->config, 'processing.max_line_length', 1000);
        
        return collect(explode("\n", $content))
            ->map(fn(string $line): string => 
                strlen($line) > $maxLineLength 
                    ? substr($line, 0, $maxLineLength) . '...'
                    : $line
            )
            ->join("\n");
    }

    private function detectAndConvertEncoding(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        
        if ($encoding && $encoding !== 'UTF-8') {
            $converted = mb_convert_encoding($content, 'UTF-8', $encoding);
            return $converted !== false ? $converted : $content;
        }
        
        return $content;
    }

    private function addToTree(array &$tree, array $parts): void
    {
        $current = &$tree;
        
        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
    }

    private function renderTree(array $tree, string $prefix = '', bool $isLast = true): string
    {
        $result = '';
        $entries = array_keys($tree);
        $count = count($entries);
        
        foreach ($entries as $index => $entry) {
            $isLastEntry = $index === $count - 1;
            $connector = $isLastEntry ? '└── ' : '├── ';
            
            $result .= $prefix . $connector . $entry . "\n";
            
            if (!empty($tree[$entry])) {
                $nextPrefix = $prefix . ($isLastEntry ? '    ' : '│   ');
                $result .= $this->renderTree($tree[$entry], $nextPrefix, $isLastEntry);
            }
        }
        
        return $result;
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        
        return sprintf('%.1f %s', $bytes / (1024 ** $factor), $units[$factor] ?? 'GB');
    }
}
