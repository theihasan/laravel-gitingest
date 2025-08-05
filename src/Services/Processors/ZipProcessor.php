<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Services\Processors;

use Illuminate\Support\Collection;
use React\Filesystem\FilesystemInterface;
use React\Promise\PromiseInterface;
use Ihasan\LaravelGitingest\Services\FileFilter;
use ZipArchive;

final readonly class ZipProcessor
{
    public function __construct(
        private FilesystemInterface $filesystem,
        private FileFilter $fileFilter,
    ) {}

    public function extractAndProcess(string $zipPath, array $options = []): PromiseInterface
    {
        $extractPath = $this->createTempDirectory();
        
        return $this->extractZipFile($zipPath, $extractPath)
            ->then(fn() => $this->processExtractedFiles($extractPath, $options))
            ->then(function (Collection $result) use ($extractPath): Collection {
                $this->cleanupAsync($extractPath);
                return $result;
            });
    }

    private function extractZipFile(string $zipPath, string $extractPath): PromiseInterface
    {
        return $this->filesystem->file($zipPath)
            ->exists()
            ->then(function (bool $exists) use ($zipPath, $extractPath): void {
                if (!$exists) {
                    throw new \RuntimeException("ZIP file does not exist: {$zipPath}");
                }

                $zip = new ZipArchive();
                
                if ($zip->open($zipPath) !== true) {
                    throw new \RuntimeException("Cannot open ZIP file: {$zipPath}");
                }

                $zip->extractTo($extractPath);
                $zip->close();
            });
    }

    private function processExtractedFiles(string $extractPath, array $options): PromiseInterface
    {
        return $this->scanDirectory($extractPath)
            ->then(function (array $files) use ($options, $extractPath): PromiseInterface {
                $filteredFiles = collect($files)
                    ->filter(fn(string $file): bool => $this->fileFilter->shouldInclude($file, $options))
                    ->values()
                    ->toArray();

                return $this->readMultipleFiles($filteredFiles, $extractPath);
            });
    }

    private function scanDirectory(string $directory): PromiseInterface
    {
        return $this->filesystem->dir($directory)
            ->ls()
            ->then(function (array $entries) use ($directory): array {
                $files = [];
                
                foreach ($entries as $entry) {
                    if ($entry->getType() === 'file') {
                        $files[] = $entry->getPath();
                    } elseif ($entry->getType() === 'directory') {
                        // Recursively scan subdirectories
                        $subFiles = $this->scanDirectorySync($entry->getPath());
                        $files = array_merge($files, $subFiles);
                    }
                }
                
                return $files;
            });
    }

    private function scanDirectorySync(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        return collect(iterator_to_array($iterator))
            ->filter(fn(\SplFileInfo $file): bool => $file->isFile())
            ->map(fn(\SplFileInfo $file): string => $file->getPathname())
            ->values()
            ->toArray();
    }

    private function readMultipleFiles(array $filePaths, string $extractPath): PromiseInterface
    {
        $promises = collect($filePaths)
            ->map(fn(string $filePath): PromiseInterface => $this->readFileData($filePath, $extractPath))
            ->toArray();

        return \React\Promise\all($promises)
            ->then(function (array $results): Collection {
                return collect($results)
                    ->filter(fn(array $fileData): bool => !empty($fileData['content']))
                    ->mapWithKeys(fn(array $fileData): array => [
                        $fileData['relative_path'] => $fileData
                    ]);
            });
    }

    private function readFileData(string $filePath, string $extractPath): PromiseInterface
    {
        return $this->filesystem->file($filePath)
            ->getContents()
            ->then(function (string $content) use ($filePath, $extractPath): array {
                return [
                    'path' => $filePath,
                    'relative_path' => $this->getRelativePath($filePath, $extractPath),
                    'content' => $content,
                    'size' => strlen($content),
                    'lines' => substr_count($content, "\n") + 1,
                    'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
                ];
            });
    }

    private function getRelativePath(string $fullPath, string $basePath): string
    {
        return str_replace($basePath . DIRECTORY_SEPARATOR, '', $fullPath);
    }

    private function createTempDirectory(): string
    {
        $tempDir = sys_get_temp_dir() . '/gitingest_' . uniqid();
        mkdir($tempDir, 0755, true);
        return $tempDir;
    }

    private function cleanupAsync(string $directory): PromiseInterface
    {
        return $this->filesystem->dir($directory)
            ->exists()
            ->then(function (bool $exists) use ($directory): PromiseInterface {
                if ($exists) {
                    return $this->removeDirectoryAsync($directory);
                }
                return \React\Promise\resolve();
            });
    }

    private function removeDirectoryAsync(string $directory): PromiseInterface
    {
        return $this->filesystem->dir($directory)
            ->remove();
    }

    private function cleanup(string $directory): void
    {
        if (is_dir($directory)) {
            $this->removeDirectory($directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        $files = array_diff(scandir($directory), ['.', '..']);
        
        collect($files)->each(function (string $file) use ($directory): void {
            $path = $directory . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        });
        
        rmdir($directory);
    }
}
