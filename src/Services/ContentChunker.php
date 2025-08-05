<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

final readonly class ContentChunker
{
    public function __construct(
        private TokenCounter $tokenCounter,
        private array $config,
    ) {}

    public function chunkRepository(Collection $files, array $options = []): Collection
    {
        $strategy = Arr::get($options, 'strategy', 'semantic');
        $maxTokensPerChunk = Arr::get($options, 'max_tokens_per_chunk', 100_000);
        $model = Arr::get($options, 'model', 'gpt-4');
        
        return match ($strategy) {
            'semantic' => $this->semanticChunking($files, $maxTokensPerChunk, $model, $options),
            'file_based' => $this->fileBasedChunking($files, $maxTokensPerChunk, $model, $options),
            'directory_based' => $this->directoryBasedChunking($files, $maxTokensPerChunk, $model, $options),
            'dependency_aware' => $this->dependencyAwareChunking($files, $maxTokensPerChunk, $model, $options),
            'size_balanced' => $this->sizeBalancedChunking($files, $maxTokensPerChunk, $model, $options),
            default => $this->semanticChunking($files, $maxTokensPerChunk, $model, $options),
        };
    }

    public function generateChunkSummaries(Collection $chunks): Collection
    {
        return $chunks->map(function (array $chunk): array {
            return array_merge($chunk, [
                'summary' => $this->generateChunkSummary($chunk),
                'file_count' => count($chunk['files']),
                'total_tokens' => $chunk['metadata']['total_tokens'],
                'primary_languages' => $this->detectPrimaryLanguages($chunk['files']),
                'key_files' => $this->identifyKeyFiles($chunk['files']),
            ]);
        });
    }

    public function createChunkNavigation(Collection $chunks): array
    {
        return [
            'total_chunks' => $chunks->count(),
            'chunk_index' => $chunks->map(function (array $chunk, int $index): array {
                return [
                    'chunk_id' => $chunk['id'],
                    'chunk_number' => $index + 1,
                    'title' => $chunk['metadata']['title'] ?? "Chunk " . ($index + 1),
                    'summary' => $chunk['summary'] ?? '',
                    'file_count' => count($chunk['files']),
                    'token_count' => $chunk['metadata']['total_tokens'],
                    'primary_directories' => $this->getPrimaryDirectories($chunk['files']),
                ];
            })->values()->toArray(),
            'cross_references' => $this->generateCrossReferences($chunks),
        ];
    }

    private function semanticChunking(Collection $files, int $maxTokens, string $model, array $options): Collection
    {
        $chunks = collect();
        $currentChunk = $this->initializeChunk();
        $dependencyGraph = $this->buildDependencyGraph($files);
        
        // Group files by semantic relationships
        $semanticGroups = $this->groupFilesBySemantics($files, $dependencyGraph);
        
        foreach ($semanticGroups as $group) {
            $groupTokens = $this->calculateGroupTokens($group, $model);
            
            if ($this->wouldExceedLimit($currentChunk, $groupTokens, $maxTokens)) {
                if (!empty($currentChunk['files'])) {
                    $chunks->push($this->finalizeChunk($currentChunk, $model));
                    $currentChunk = $this->initializeChunk();
                }
                
                // If group is still too large, split it
                if ($groupTokens > $maxTokens) {
                    $subChunks = $this->splitLargeGroup($group, $maxTokens, $model);
                    $chunks = $chunks->merge($subChunks);
                } else {
                    $currentChunk = $this->addGroupToChunk($currentChunk, $group);
                }
            } else {
                $currentChunk = $this->addGroupToChunk($currentChunk, $group);
            }
        }
        
        if (!empty($currentChunk['files'])) {
            $chunks->push($this->finalizeChunk($currentChunk, $model));
        }
        
        return $this->addContextBoundaries($chunks, $files);
    }

    private function fileBasedChunking(Collection $files, int $maxTokens, string $model, array $options): Collection
    {
        $chunks = collect();
        $currentChunk = $this->initializeChunk();
        
        foreach ($files as $file) {
            $fileTokens = $this->tokenCounter->countTokens($file['content'] ?? '', $model);
            
            if ($this->wouldExceedLimit($currentChunk, $fileTokens, $maxTokens)) {
                if (!empty($currentChunk['files'])) {
                    $chunks->push($this->finalizeChunk($currentChunk, $model));
                    $currentChunk = $this->initializeChunk();
                }
                
                // Handle oversized files
                if ($fileTokens > $maxTokens) {
                    $fileChunks = $this->splitOversizedFile($file, $maxTokens, $model);
                    $chunks = $chunks->merge($fileChunks);
                } else {
                    $currentChunk['files'][] = $file;
                    $currentChunk['token_count'] += $fileTokens;
                }
            } else {
                $currentChunk['files'][] = $file;
                $currentChunk['token_count'] += $fileTokens;
            }
        }
        
        if (!empty($currentChunk['files'])) {
            $chunks->push($this->finalizeChunk($currentChunk, $model));
        }
        
        return $chunks;
    }

    private function directoryBasedChunking(Collection $files, int $maxTokens, string $model, array $options): Collection
    {
        $directoryGroups = $this->groupFilesByDirectory($files);
        $chunks = collect();
        
        foreach ($directoryGroups as $directory => $directoryFiles) {
            $directoryTokens = $this->calculateGroupTokens($directoryFiles, $model);
            
            if ($directoryTokens <= $maxTokens) {
                // Entire directory fits in one chunk
                $chunk = $this->createDirectoryChunk($directory, $directoryFiles, $model);
                $chunks->push($chunk);
            } else {
                // Split directory across multiple chunks
                $subChunks = $this->splitDirectoryIntoChunks($directory, $directoryFiles, $maxTokens, $model);
                $chunks = $chunks->merge($subChunks);
            }
        }
        
        return $chunks;
    }

    private function dependencyAwareChunking(Collection $files, int $maxTokens, string $model, array $options): Collection
    {
        $dependencyGraph = $this->buildDependencyGraph($files);
        $chunks = collect();
        $processed = collect();
        
        // Start with entry points (files with no dependencies)
        $entryPoints = $this->findEntryPoints($dependencyGraph);
        
        foreach ($entryPoints as $entryPoint) {
            if ($processed->contains($entryPoint)) {
                continue;
            }
            
            $dependencyChain = $this->buildDependencyChain($entryPoint, $dependencyGraph);
            $chainFiles = $files->filter(fn(array $file): bool => 
                in_array($file['path'], $dependencyChain, true)
            );
            
            $chainTokens = $this->calculateGroupTokens($chainFiles->toArray(), $model);
            
            if ($chainTokens <= $maxTokens) {
                $chunk = $this->createDependencyChunk($chainFiles, $model);
                $chunks->push($chunk);
                $processed = $processed->merge($dependencyChain);
            } else {
                $subChunks = $this->splitDependencyChain($chainFiles, $maxTokens, $model, $dependencyGraph);
                $chunks = $chunks->merge($subChunks);
                $processed = $processed->merge($dependencyChain);
            }
        }
        
        // Handle remaining files
        $remainingFiles = $files->reject(fn(array $file): bool => 
            $processed->contains($file['path'])
        );
        
        if ($remainingFiles->isNotEmpty()) {
            $remainingChunks = $this->fileBasedChunking($remainingFiles, $maxTokens, $model, $options);
            $chunks = $chunks->merge($remainingChunks);
        }
        
        return $chunks;
    }

    private function sizeBalancedChunking(Collection $files, int $maxTokens, string $model, array $options): Collection
    {
        $targetChunkSize = (int) ($maxTokens * 0.8); // Leave some buffer
        $chunks = collect();
        $currentChunk = $this->initializeChunk();
        
        // Sort files by size to better balance chunks
        $sortedFiles = $files->sortBy(fn(array $file): int => 
            $this->tokenCounter->countTokens($file['content'] ?? '', $model)
        );
        
        foreach ($sortedFiles as $file) {
            $fileTokens = $this->tokenCounter->countTokens($file['content'] ?? '', $model);
            
            if ($currentChunk['token_count'] + $fileTokens > $targetChunkSize) {
                if (!empty($currentChunk['files'])) {
                    $chunks->push($this->finalizeChunk($currentChunk, $model));
                    $currentChunk = $this->initializeChunk();
                }
            }
            
            if ($fileTokens > $maxTokens) {
                $fileChunks = $this->splitOversizedFile($file, $maxTokens, $model);
                $chunks = $chunks->merge($fileChunks);
            } else {
                $currentChunk['files'][] = $file;
                $currentChunk['token_count'] += $fileTokens;
            }
        }
        
        if (!empty($currentChunk['files'])) {
            $chunks->push($this->finalizeChunk($currentChunk, $model));
        }
        
        return $this->balanceChunkSizes($chunks, $targetChunkSize, $model);
    }

    private function initializeChunk(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'files' => [],
            'token_count' => 0,
            'created_at' => now()->toISOString(),
        ];
    }

    private function finalizeChunk(array $chunk, string $model): array
    {
        return array_merge($chunk, [
            'metadata' => $this->generateChunkMetadata($chunk, $model),
            'context_info' => $this->generateContextInfo($chunk),
        ]);
    }

    private function wouldExceedLimit(array $currentChunk, int $additionalTokens, int $maxTokens): bool
    {
        return ($currentChunk['token_count'] + $additionalTokens) > $maxTokens;
    }

    private function buildDependencyGraph(Collection $files): array
    {
        $dependencies = [];
        
        $files->each(function (array $file) use (&$dependencies): void {
            $filePath = $file['path'];
            $content = $file['content'] ?? '';
            $dependencies[$filePath] = $this->extractDependencies($content, $file['extension'] ?? '');
        });
        
        return $dependencies;
    }

    private function extractDependencies(string $content, string $extension): array
    {
        return match ($extension) {
            'php' => $this->extractPhpDependencies($content),
            'js', 'ts', 'jsx', 'tsx' => $this->extractJavaScriptDependencies($content),
            'py' => $this->extractPythonDependencies($content),
            default => [],
        };
    }

    private function extractPhpDependencies(string $content): array
    {
        $dependencies = [];
        
        // Extract use statements
        preg_match_all('/use\s+([\\\\A-Za-z0-9_]+)/', $content, $matches);
        $dependencies = array_merge($dependencies, $matches[1] ?? []);
        
        // Extract require/include statements
        preg_match_all('/(require|include)(?:_once)?\s*\(?\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
        $dependencies = array_merge($dependencies, $matches[2] ?? []);
        
        return array_unique($dependencies);
    }

    private function extractJavaScriptDependencies(string $content): array
    {
        $dependencies = [];
        
        // Extract import statements
        preg_match_all('/import\s+.*?from\s+[\'"]([^\'"]+)[\'"]/', $content, $matches);
        $dependencies = array_merge($dependencies, $matches[1] ?? []);
        
        // Extract require statements
        preg_match_all('/require\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches);
        $dependencies = array_merge($dependencies, $matches[1] ?? []);
        
        return array_unique($dependencies);
    }

    private function extractPythonDependencies(string $content): array
    {
        $dependencies = [];
        
        // Extract import statements
        preg_match_all('/^import\s+([A-Za-z0-9_.]+)/m', $content, $matches);
        $dependencies = array_merge($dependencies, $matches[1] ?? []);
        
        // Extract from imports
        preg_match_all('/^from\s+([A-Za-z0-9_.]+)\s+import/m', $content, $matches);
        $dependencies = array_merge($dependencies, $matches[1] ?? []);
        
        return array_unique($dependencies);
    }

    private function groupFilesBySemantics(Collection $files, array $dependencyGraph): array
    {
        $groups = [];
        $processed = [];
        
        foreach ($files as $file) {
            $filePath = $file['path'];
            
            if (in_array($filePath, $processed, true)) {
                continue;
            }
            
            $relatedFiles = $this->findRelatedFiles($filePath, $files, $dependencyGraph);
            $groups[] = $relatedFiles;
            $processed = array_merge($processed, array_column($relatedFiles, 'path'));
        }
        
        return $groups;
    }

    private function findRelatedFiles(string $filePath, Collection $files, array $dependencyGraph): array
    {
        $related = [];
        $queue = [$filePath];
        $visited = [];
        
        while (!empty($queue)) {
            $currentPath = array_shift($queue);
            
            if (in_array($currentPath, $visited, true)) {
                continue;
            }
            
            $visited[] = $currentPath;
            $file = $files->firstWhere('path', $currentPath);
            
            if ($file) {
                $related[] = $file;
                
                // Add dependencies to queue
                $dependencies = $dependencyGraph[$currentPath] ?? [];
                foreach ($dependencies as $dependency) {
                    if (!in_array($dependency, $visited, true)) {
                        $queue[] = $dependency;
                    }
                }
            }
        }
        
        return $related;
    }

    private function calculateGroupTokens(array $files, string $model): int
    {
        return collect($files)->sum(fn(array $file): int => 
            $this->tokenCounter->countTokens($file['content'] ?? '', $model)
        );
    }

    private function splitOversizedFile(array $file, int $maxTokens, string $model): Collection
    {
        $content = $file['content'] ?? '';
        $chunks = $this->tokenCounter->chunkTextByTokens($content, $maxTokens, $model);
        
        return $chunks->map(function (string $chunkContent, int $index) use ($file, $model): array {
            return [
                'id' => Str::uuid()->toString(),
                'files' => [
                    array_merge($file, [
                        'content' => $chunkContent,
                        'chunk_part' => $index + 1,
                        'is_partial' => true,
                    ])
                ],
                'token_count' => $this->tokenCounter->countTokens($chunkContent, $model),
                'metadata' => [
                    'type' => 'oversized_file_chunk',
                    'original_file' => $file['path'],
                    'part' => $index + 1,
                    'total_parts' => $chunks->count(),
                ],
                'created_at' => now()->toISOString(),
            ];
        });
    }

    private function addContextBoundaries(Collection $chunks, Collection $allFiles): Collection
    {
        return $chunks->map(function (array $chunk, int $index) use ($chunks, $allFiles): array {
            $contextInfo = [
                'previous_chunk' => $index > 0 ? $chunks->get($index - 1)['id'] : null,
                'next_chunk' => $index < $chunks->count() - 1 ? $chunks->get($index + 1)['id'] : null,
                'related_files' => $this->findRelatedFilesAcrossChunks($chunk, $allFiles),
            ];
            
            return array_merge($chunk, ['context_boundaries' => $contextInfo]);
        });
    }

    private function generateChunkMetadata(array $chunk, string $model): array
    {
        $files = $chunk['files'];
        
        return [
            'total_tokens' => $chunk['token_count'],
            'file_count' => count($files),
            'total_size' => collect($files)->sum(fn(array $file): int => strlen($file['content'] ?? '')),
            'languages' => $this->detectLanguages($files),
            'directories' => $this->extractDirectories($files),
            'title' => $this->generateChunkTitle($files),
            'complexity_score' => $this->calculateComplexityScore($files),
        ];
    }

    private function generateChunkSummary(array $chunk): string
    {
        $files = $chunk['files'];
        $fileCount = count($files);
        $languages = $this->detectPrimaryLanguages($files);
        $directories = $this->getPrimaryDirectories($files);
        
        $summary = "Contains {$fileCount} files";
        
        if (!empty($languages)) {
            $summary .= " primarily in " . implode(', ', array_slice($languages, 0, 3));
        }
        
        if (!empty($directories)) {
            $summary .= " from " . implode(', ', array_slice($directories, 0, 2)) . " directories";
        }
        
        return $summary;
    }

    private function detectPrimaryLanguages(array $files): array
    {
        $languages = collect($files)
            ->groupBy(fn(array $file): string => $file['extension'] ?? 'unknown')
            ->map(fn(Collection $group): int => $group->count())
            ->sortDesc()
            ->keys()
            ->take(3)
            ->toArray();
        
        return array_filter($languages, fn(string $lang): bool => $lang !== 'unknown');
    }

    private function getPrimaryDirectories(array $files): array
    {
        return collect($files)
            ->map(fn(array $file): string => dirname($file['path'] ?? ''))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(3)
            ->toArray();
    }

    private function generateCrossReferences(Collection $chunks): array
    {
        $references = [];
        
        $chunks->each(function (array $chunk) use (&$references): void {
            $chunkFiles = collect($chunk['files'])->pluck('path');
            
            // Find references to files in other chunks
            foreach ($chunk['files'] as $file) {
                $content = $file['content'] ?? '';
                $dependencies = $this->extractDependencies($content, $file['extension'] ?? '');
                
                foreach ($dependencies as $dependency) {
                    $referencedChunk = $this->findChunkContainingFile($dependency, $chunks);
                    if ($referencedChunk && $referencedChunk['id'] !== $chunk['id']) {
                        $references[] = [
                            'from_chunk' => $chunk['id'],
                            'to_chunk' => $referencedChunk['id'],
                            'reference_type' => 'dependency',
                            'file' => $file['path'],
                            'referenced_file' => $dependency,
                        ];
                    }
                }
            }
        });
        
        return $references;
    }

    private function findChunkContainingFile(string $filePath, Collection $chunks): ?array
    {
        return $chunks->first(function (array $chunk) use ($filePath): bool {
            return collect($chunk['files'])->contains(fn(array $file): bool => 
                $file['path'] === $filePath
            );
        });
    }

    private function groupFilesByDirectory(Collection $files): array
    {
        return $files->groupBy(fn(array $file): string => 
            dirname($file['path'] ?? '')
        )->toArray();
    }

    private function findEntryPoints(array $dependencyGraph): array
    {
        $allDependencies = collect($dependencyGraph)->flatten()->unique()->toArray();
        
        return collect($dependencyGraph)
            ->keys()
            ->reject(fn(string $file): bool => in_array($file, $allDependencies, true))
            ->toArray();
    }

    private function buildDependencyChain(string $entryPoint, array $dependencyGraph): array
    {
        $chain = [];
        $queue = [$entryPoint];
        $visited = [];
        
        while (!empty($queue)) {
            $current = array_shift($queue);
            
            if (in_array($current, $visited, true)) {
                continue;
            }
            
            $visited[] = $current;
            $chain[] = $current;
            
            $dependencies = $dependencyGraph[$current] ?? [];
            foreach ($dependencies as $dependency) {
                if (!in_array($dependency, $visited, true)) {
                    $queue[] = $dependency;
                }
            }
        }
        
        return $chain;
    }

    private function generateContextInfo(array $chunk): array
    {
        return [
            'entry_points' => $this->identifyChunkEntryPoints($chunk['files']),
            'exports' => $this->identifyChunkExports($chunk['files']),
            'internal_dependencies' => $this->mapInternalDependencies($chunk['files']),
        ];
    }

    private function identifyChunkEntryPoints(array $files): array
    {
        return collect($files)
            ->filter(fn(array $file): bool => $this->isEntryPoint($file))
            ->pluck('path')
            ->toArray();
    }

    private function identifyChunkExports(array $files): array
    {
        $exports = [];
        
        foreach ($files as $file) {
            $content = $file['content'] ?? '';
            $fileExports = $this->extractExports($content, $file['extension'] ?? '');
            if (!empty($fileExports)) {
                $exports[$file['path']] = $fileExports;
            }
        }
        
        return $exports;
    }

    private function mapInternalDependencies(array $files): array
    {
        $filePaths = collect($files)->pluck('path')->toArray();
        $dependencies = [];
        
        foreach ($files as $file) {
            $content = $file['content'] ?? '';
            $fileDeps = $this->extractDependencies($content, $file['extension'] ?? '');
            $internalDeps = array_intersect($fileDeps, $filePaths);
            
            if (!empty($internalDeps)) {
                $dependencies[$file['path']] = $internalDeps;
            }
        }
        
        return $dependencies;
    }

    private function extractExports(string $content, string $extension): array
    {
        return match ($extension) {
            'php' => $this->extractPhpExports($content),
            'js', 'ts', 'jsx', 'tsx' => $this->extractJavaScriptExports($content),
            'py' => $this->extractPythonExports($content),
            default => [],
        };
    }

    private function extractPhpExports(string $content): array
    {
        $exports = [];
        
        // Extract class definitions
        preg_match_all('/class\s+([A-Za-z0-9_]+)/', $content, $matches);
        $exports = array_merge($exports, $matches[1] ?? []);
        
        // Extract function definitions
        preg_match_all('/function\s+([A-Za-z0-9_]+)/', $content, $matches);
        $exports = array_merge($exports, $matches[1] ?? []);
        
        return array_unique($exports);
    }

    private function extractJavaScriptExports(string $content): array
    {
        $exports = [];
        
        // Extract export statements
        preg_match_all('/export\s+(?:default\s+)?(?:class|function|const|let|var)\s+([A-Za-z0-9_$]+)/', $content, $matches);
        $exports = array_merge($exports, $matches[1] ?? []);
        
        return array_unique($exports);
    }

    private function extractPythonExports(string $content): array
    {
        $exports = [];
        
        // Extract class and function definitions
        preg_match_all('/^(?:class|def)\s+([A-Za-z0-9_]+)/m', $content, $matches);
        $exports = array_merge($exports, $matches[1] ?? []);
        
        return array_unique($exports);
    }

    private function isEntryPoint(array $file): bool
    {
        $fileName = basename($file['path'] ?? '');
        $entryPatterns = ['index', 'main', 'app', 'bootstrap', '__init__'];
        
        return collect($entryPatterns)->contains(fn(string $pattern): bool => 
            Str::contains($fileName, $pattern)
        );
    }

    private function detectLanguages(array $files): array
    {
        return collect($files)
            ->groupBy(fn(array $file): string => $file['extension'] ?? 'unknown')
            ->keys()
            ->filter(fn(string $ext): bool => $ext !== 'unknown')
            ->toArray();
    }

    private function extractDirectories(array $files): array
    {
        return collect($files)
            ->map(fn(array $file): string => dirname($file['path'] ?? ''))
            ->unique()
            ->toArray();
    }

    private function generateChunkTitle(array $files): string
    {
        $directories = $this->getPrimaryDirectories($files);
        $languages = $this->detectPrimaryLanguages($files);
        
        if (!empty($directories)) {
            $mainDir = basename($directories[0]);
            return ucfirst($mainDir) . " Module";
        }
        
        if (!empty($languages)) {
            return ucfirst($languages[0]) . " Files";
        }
        
        return "Code Files";
    }

    private function calculateComplexityScore(array $files): float
    {
        $totalLines = collect($files)->sum(fn(array $file): int => 
            substr_count($file['content'] ?? '', "\n") + 1
        );
        
        $uniqueLanguages = count($this->detectLanguages($files));
        $fileCount = count($files);
        
        // Simple complexity score based on various factors
        return round(($totalLines * 0.1) + ($uniqueLanguages * 10) + ($fileCount * 2), 2);
    }

    private function identifyKeyFiles(array $files): array
    {
        return collect($files)
            ->filter(fn(array $file): bool => $this->isKeyFile($file))
            ->pluck('path')
            ->take(5)
            ->toArray();
    }

    private function isKeyFile(array $file): bool
    {
        $fileName = basename($file['path'] ?? '');
        $content = $file['content'] ?? '';
        
        // Consider files with many exports or classes as key files
        $exports = $this->extractExports($content, $file['extension'] ?? '');
        
        return count($exports) > 3 || 
               $this->isEntryPoint($file) ||
               Str::contains($fileName, ['config', 'service', 'controller', 'model']);
    }

    private function findRelatedFilesAcrossChunks(array $chunk, Collection $allFiles): array
    {
        $chunkFiles = collect($chunk['files'])->pluck('path');
        $related = [];
        
        foreach ($chunk['files'] as $file) {
            $content = $file['content'] ?? '';
            $dependencies = $this->extractDependencies($content, $file['extension'] ?? '');
            
            foreach ($dependencies as $dependency) {
                if (!$chunkFiles->contains($dependency)) {
                    $relatedFile = $allFiles->firstWhere('path', $dependency);
                    if ($relatedFile) {
                        $related[] = $dependency;
                    }
                }
            }
        }
        
        return array_unique($related);
    }

    private function createDirectoryChunk(string $directory, array $files, string $model): array
    {
        $chunk = $this->initializeChunk();
        $chunk['files'] = $files;
        $chunk['token_count'] = $this->calculateGroupTokens($files, $model);
        
        return $this->finalizeChunk($chunk, $model);
    }

    private function splitDirectoryIntoChunks(string $directory, array $files, int $maxTokens, string $model): Collection
    {
        return $this->fileBasedChunking(collect($files), $maxTokens, $model, []);
    }

    private function createDependencyChunk(Collection $files, string $model): array
    {
        $chunk = $this->initializeChunk();
        $chunk['files'] = $files->toArray();
        $chunk['token_count'] = $this->calculateGroupTokens($files->toArray(), $model);
        
        return $this->finalizeChunk($chunk, $model);
    }

    private function splitDependencyChain(Collection $files, int $maxTokens, string $model, array $dependencyGraph): Collection
    {
        return $this->semanticChunking($files, $maxTokens, $model, ['strategy' => 'dependency_aware']);
    }

    private function addGroupToChunk(array $chunk, array $group): array
    {
        $chunk['files'] = array_merge($chunk['files'], $group);
        return $chunk;
    }

    private function splitLargeGroup(array $group, int $maxTokens, string $model): Collection
    {
        return $this->fileBasedChunking(collect($group), $maxTokens, $model, []);
    }

    private function balanceChunkSizes(Collection $chunks, int $targetSize, string $model): Collection
    {
        // Basic rebalancing - could be more sophisticated
        return $chunks->map(function (array $chunk) use ($targetSize, $model): array {
            if ($chunk['token_count'] < $targetSize * 0.5 && count($chunk['files']) < 3) {
                $chunk['metadata']['needs_rebalancing'] = true;
            }
            
            return $chunk;
        });
    }
}
