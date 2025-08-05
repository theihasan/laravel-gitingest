<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

final readonly class FileFilter
{
    public function __construct(
        private array $config,
    ) {}

    public function shouldInclude(string $filePath, array $options = []): bool
    {
        return collect([
            fn(): bool => $this->checkFileSize($filePath),
            fn(): bool => $this->checkExtension($filePath, $options),
            fn(): bool => $this->checkDirectory($filePath, $options),
            fn(): bool => $this->checkGitignorePatterns($filePath, $options),
            fn(): bool => $this->checkCustomPatterns($filePath, $options),
        ])->every(fn(callable $check): bool => $check());
    }

    public function filterFiles(Collection $files, array $options = []): Collection
    {
        return $files->filter(fn(string $file): bool => $this->shouldInclude($file, $options));
    }

    private function checkFileSize(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $maxSize = Arr::get($this->config, 'filtering.max_file_size', 1024 * 1024);
        return filesize($filePath) <= $maxSize;
    }

    private function checkExtension(string $filePath, array $options): bool
    {
        $extension = '.' . pathinfo($filePath, PATHINFO_EXTENSION);
        
        $excludedExtensions = Arr::get($options, 'exclude_extensions') 
            ?? Arr::get($this->config, 'filtering.exclude_extensions', []);
            
        $includedPatterns = Arr::get($options, 'include_patterns')
            ?? Arr::get($this->config, 'filtering.include_patterns', []);

        // Check exclusions first
        if (collect($excludedExtensions)->contains($extension)) {
            return false;
        }

        // If include patterns are specified, file must match at least one
        if (!empty($includedPatterns)) {
            return collect($includedPatterns)
                ->contains(fn(string $pattern): bool => 
                    fnmatch($pattern, basename($filePath))
                );
        }

        return true;
    }

    private function checkDirectory(string $filePath, array $options): bool
    {
        $excludedDirs = Arr::get($options, 'exclude_directories')
            ?? Arr::get($this->config, 'filtering.exclude_directories', []);

        return !collect($excludedDirs)
            ->contains(fn(string $dir): bool => 
                Str::contains($filePath, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR)
                || Str::startsWith(basename(dirname($filePath)), $dir)
            );
    }

    private function checkGitignorePatterns(string $filePath, array $options): bool
    {
        $useGitignore = Arr::get($options, 'respect_gitignore', 
            Arr::get($this->config, 'filtering.respect_gitignore', true)
        );

        if (!$useGitignore) {
            return true;
        }

        // Simple gitignore patterns
        $gitignorePatterns = [
            '.git',
            'node_modules',
            'vendor',
            '.env',
            '*.log',
            'dist',
            'build',
            '.DS_Store',
        ];

        return !collect($gitignorePatterns)
            ->contains(fn(string $pattern): bool => 
                fnmatch($pattern, basename($filePath)) 
                || Str::contains($filePath, DIRECTORY_SEPARATOR . $pattern . DIRECTORY_SEPARATOR)
            );
    }

    private function checkCustomPatterns(string $filePath, array $options): bool
    {
        $customExcludes = Arr::get($options, 'custom_excludes', []);
        
        if (empty($customExcludes)) {
            return true;
        }

        return !collect($customExcludes)
            ->contains(fn(string $pattern): bool => 
                fnmatch($pattern, $filePath) || fnmatch($pattern, basename($filePath))
            );
    }
}
