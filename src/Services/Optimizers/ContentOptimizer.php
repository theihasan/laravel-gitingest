<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Services\Optimizers;

use Ihasan\LaravelGitingest\Contracts\OptimizerInterface;
use Ihasan\LaravelGitingest\Services\TokenCounter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

final readonly class ContentOptimizer implements OptimizerInterface
{
    public function __construct(
        private TokenCounter $tokenCounter,
        private array $config,
    ) {}

    public function optimize(string $content, array $options = []): string
    {
        $level = Arr::get($options, 'level', 'moderate');
        $fileType = Arr::get($options, 'file_type', 'text');
        
        $strategies = $this->getStrategiesForLevel($level);
        $optimizedContent = $content;
        
        foreach ($strategies as $strategy) {
            $optimizedContent = $this->applyStrategy($optimizedContent, $strategy, $fileType, $options);
        }
        
        return $optimizedContent;
    }

    public function optimizeFiles(Collection $files, array $options = []): Collection
    {
        return $files->map(function (array $file) use ($options): array {
            $fileOptions = array_merge($options, [
                'file_type' => $file['extension'] ?? 'text',
                'file_path' => $file['path'] ?? '',
            ]);
            
            $originalContent = $file['content'] ?? '';
            $optimizedContent = $this->optimize($originalContent, $fileOptions);
            
            return array_merge($file, [
                'content' => $optimizedContent,
                'original_size' => strlen($originalContent),
                'optimized_size' => strlen($optimizedContent),
                'compression_ratio' => $this->calculateCompressionRatio($originalContent, $optimizedContent),
                'optimization_metadata' => $this->generateMetadata($originalContent, $optimizedContent, $fileOptions),
            ]);
        });
    }

    public function estimateTokenReduction(string $content, array $options = []): array
    {
        $model = Arr::get($options, 'model', 'gpt-4');
        $originalTokens = $this->tokenCounter->countTokens($content, $model);
        $optimizedContent = $this->optimize($content, $options);
        $optimizedTokens = $this->tokenCounter->countTokens($optimizedContent, $model);
        
        return [
            'original_tokens' => $originalTokens,
            'optimized_tokens' => $optimizedTokens,
            'tokens_saved' => $originalTokens - $optimizedTokens,
            'reduction_percentage' => $originalTokens > 0 ? round((($originalTokens - $optimizedTokens) / $originalTokens) * 100, 2) : 0,
            'original_size' => strlen($content),
            'optimized_size' => strlen($optimizedContent),
        ];
    }

    public function getOptimizationStrategies(): array
    {
        return [
            'whitespace' => 'Remove excessive whitespace and empty lines',
            'comments' => 'Remove non-essential comments',
            'debug_statements' => 'Remove debug and logging statements',
            'imports' => 'Optimize import statements',
            'repetitive_patterns' => 'Compress repetitive code patterns',
            'truncation' => 'Intelligently truncate oversized content',
            'minification' => 'Minify code where appropriate',
        ];
    }

    public function reverseOptimization(string $optimizedContent, array $metadata = []): string
    {
        // Basic reversal - more sophisticated reversal would require storing more metadata
        $content = $optimizedContent;
        
        if (isset($metadata['removed_whitespace'])) {
            $content = $this->restoreWhitespace($content, $metadata['removed_whitespace']);
        }
        
        if (isset($metadata['removed_comments'])) {
            $content = $this->restoreComments($content, $metadata['removed_comments']);
        }
        
        return $content;
    }

    private function getStrategiesForLevel(string $level): array
    {
        return match ($level) {
            'light' => ['whitespace', 'debug_statements'],
            'moderate' => ['whitespace', 'debug_statements', 'comments', 'imports'],
            'aggressive' => ['whitespace', 'debug_statements', 'comments', 'imports', 'repetitive_patterns', 'truncation'],
            'extreme' => ['whitespace', 'debug_statements', 'comments', 'imports', 'repetitive_patterns', 'truncation', 'minification'],
            default => ['whitespace', 'debug_statements', 'comments'],
        };
    }

    private function applyStrategy(string $content, string $strategy, string $fileType, array $options): string
    {
        return match ($strategy) {
            'whitespace' => $this->optimizeWhitespace($content),
            'comments' => $this->removeComments($content, $fileType),
            'debug_statements' => $this->removeDebugStatements($content, $fileType),
            'imports' => $this->optimizeImports($content, $fileType),
            'repetitive_patterns' => $this->compressRepetitivePatterns($content),
            'truncation' => $this->intelligentTruncation($content, $options),
            'minification' => $this->minifyContent($content, $fileType),
            default => $content,
        };
    }

    private function optimizeWhitespace(string $content): string
    {
        // Remove trailing whitespace
        $content = preg_replace('/[ \t]+$/m', '', $content);
        
        // Reduce multiple consecutive empty lines to single empty line
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        
        // Remove leading and trailing whitespace
        return trim($content);
    }

    private function removeComments(string $content, string $fileType): string
    {
        return match ($fileType) {
            'php' => $this->removePhpComments($content),
            'js', 'ts', 'jsx', 'tsx' => $this->removeJavaScriptComments($content),
            'css', 'scss', 'less' => $this->removeCssComments($content),
            'html', 'xml' => $this->removeHtmlComments($content),
            'py' => $this->removePythonComments($content),
            default => $this->removeGenericComments($content),
        };
    }

    private function removePhpComments(string $content): string
    {
        // Remove single-line comments but preserve important ones
        $content = preg_replace('/^\s*\/\/(?!\s*TODO|!\s*FIXME|!\s*NOTE).*$/m', '', $content);
        
        // Remove multi-line comments but preserve docblocks
        $content = preg_replace('/\/\*(?!\*)[\s\S]*?\*\//', '', $content);
        
        // Remove hash comments
        $content = preg_replace('/^\s*#(?!\s*TODO|!\s*FIXME).*$/m', '', $content);
        
        return $content;
    }

    private function removeJavaScriptComments(string $content): string
    {
        // Remove single-line comments
        $content = preg_replace('/^\s*\/\/(?!\s*TODO|!\s*FIXME|!\s*@).*$/m', '', $content);
        
        // Remove multi-line comments but preserve JSDoc
        $content = preg_replace('/\/\*(?!\*)[\s\S]*?\*\//', '', $content);
        
        return $content;
    }

    private function removeCssComments(string $content): string
    {
        return preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
    }

    private function removeHtmlComments(string $content): string
    {
        return preg_replace('/<!--[\s\S]*?-->/', '', $content);
    }

    private function removePythonComments(string $content): string
    {
        // Remove hash comments but preserve shebangs and important comments
        return preg_replace('/^\s*#(?!!\s*\/|!\s*TODO|!\s*FIXME).*$/m', '', $content);
    }

    private function removeGenericComments(string $content): string
    {
        // Generic comment removal
        $content = preg_replace('/^\s*\/\/.*$/m', '', $content);
        $content = preg_replace('/^\s*#.*$/m', '', $content);
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
        
        return $content;
    }

    private function removeDebugStatements(string $content, string $fileType): string
    {
        return match ($fileType) {
            'php' => $this->removePhpDebugStatements($content),
            'js', 'ts', 'jsx', 'tsx' => $this->removeJavaScriptDebugStatements($content),
            'py' => $this->removePythonDebugStatements($content),
            default => $content,
        };
    }

    private function removePhpDebugStatements(string $content): string
    {
        $debugPatterns = [
            '/\b(dd|dump|var_dump|print_r|var_export)\s*\([^)]*\)\s*;?/i',
            '/\berror_log\s*\([^)]*\)\s*;?/i',
            '/\becho\s+[\'"]DEBUG:.*$/m',
            '/\bprint\s+[\'"]DEBUG:.*$/m',
        ];
        
        return collect($debugPatterns)
            ->reduce(fn(string $content, string $pattern): string => 
                preg_replace($pattern, '', $content), $content);
    }

    private function removeJavaScriptDebugStatements(string $content): string
    {
        $debugPatterns = [
            '/\bconsole\.(log|debug|info|warn|error)\s*\([^)]*\)\s*;?/i',
            '/\bdebugger\s*;?/i',
            '/\balert\s*\([^)]*\)\s*;?/i',
        ];
        
        return collect($debugPatterns)
            ->reduce(fn(string $content, string $pattern): string => 
                preg_replace($pattern, '', $content), $content);
    }

    private function removePythonDebugStatements(string $content): string
    {
        $debugPatterns = [
            '/\bprint\s*\([^)]*\)\s*$/m',
            '/\bpdb\.set_trace\s*\(\s*\)\s*$/m',
            '/\bbreakpoint\s*\(\s*\)\s*$/m',
        ];
        
        return collect($debugPatterns)
            ->reduce(fn(string $content, string $pattern): string => 
                preg_replace($pattern, '', $content), $content);
    }

    private function optimizeImports(string $content, string $fileType): string
    {
        return match ($fileType) {
            'php' => $this->optimizePhpImports($content),
            'js', 'ts', 'jsx', 'tsx' => $this->optimizeJavaScriptImports($content),
            'py' => $this->optimizePythonImports($content),
            default => $content,
        };
    }

    private function optimizePhpImports(string $content): string
    {
        // Remove unused use statements (basic implementation)
        $lines = explode("\n", $content);
        $useStatements = [];
        $otherLines = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^\s*use\s+/', $line)) {
                $useStatements[] = $line;
            } else {
                $otherLines[] = $line;
            }
        }
        
        // Sort use statements
        sort($useStatements);
        
        return implode("\n", array_merge($useStatements, $otherLines));
    }

    private function optimizeJavaScriptImports(string $content): string
    {
        // Basic import optimization - sort imports
        $lines = explode("\n", $content);
        $imports = [];
        $otherLines = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^\s*(import|const.*require)/', $line)) {
                $imports[] = $line;
            } else {
                $otherLines[] = $line;
            }
        }
        
        sort($imports);
        
        return implode("\n", array_merge($imports, $otherLines));
    }

    private function optimizePythonImports(string $content): string
    {
        // Basic Python import optimization
        $lines = explode("\n", $content);
        $imports = [];
        $fromImports = [];
        $otherLines = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^\s*import\s+/', $line)) {
                $imports[] = $line;
            } elseif (preg_match('/^\s*from\s+/', $line)) {
                $fromImports[] = $line;
            } else {
                $otherLines[] = $line;
            }
        }
        
        sort($imports);
        sort($fromImports);
        
        return implode("\n", array_merge($imports, $fromImports, $otherLines));
    }

    private function compressRepetitivePatterns(string $content): string
    {
        // Simple pattern compression - reduce repetitive empty lines and spaces
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = preg_replace('/\s{4,}/', '    ', $content);
        
        return $content;
    }

    private function intelligentTruncation(string $content, array $options): string
    {
        $maxLength = Arr::get($options, 'max_length', 10000);
        
        if (strlen($content) <= $maxLength) {
            return $content;
        }
        
        // Try to truncate at natural boundaries
        $truncated = substr($content, 0, $maxLength);
        
        // Find last complete line
        $lastNewline = strrpos($truncated, "\n");
        if ($lastNewline !== false && $lastNewline > $maxLength * 0.8) {
            $truncated = substr($truncated, 0, $lastNewline);
        }
        
        return $truncated . "\n\n... [truncated]";
    }

    private function minifyContent(string $content, string $fileType): string
    {
        return match ($fileType) {
            'css' => $this->minifyCss($content),
            'js' => $this->minifyJavaScript($content),
            'json' => $this->minifyJson($content),
            default => $content,
        };
    }

    private function minifyCss(string $content): string
    {
        // Basic CSS minification
        $content = preg_replace('/\s+/', ' ', $content);
        $content = str_replace(['; ', ' {', '{ ', ' }', '} '], [';', '{', '{', '}', '}'], $content);
        
        return trim($content);
    }

    private function minifyJavaScript(string $content): string
    {
        // Very basic JS minification - real minification would need proper parsing
        $content = preg_replace('/\s+/', ' ', $content);
        $content = str_replace(['; ', ' {', '{ ', ' }', '} '], [';', '{', '{', '}', '}'], $content);
        
        return trim($content);
    }

    private function minifyJson(string $content): string
    {
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_SLASHES);
        }
        
        return $content;
    }

    private function calculateCompressionRatio(string $original, string $optimized): float
    {
        $originalSize = strlen($original);
        $optimizedSize = strlen($optimized);
        
        if ($originalSize === 0) {
            return 0.0;
        }
        
        return round((($originalSize - $optimizedSize) / $originalSize) * 100, 2);
    }

    private function generateMetadata(string $original, string $optimized, array $options): array
    {
        return [
            'optimization_level' => Arr::get($options, 'level', 'moderate'),
            'strategies_applied' => $this->getStrategiesForLevel(Arr::get($options, 'level', 'moderate')),
            'original_lines' => substr_count($original, "\n") + 1,
            'optimized_lines' => substr_count($optimized, "\n") + 1,
            'compression_ratio' => $this->calculateCompressionRatio($original, $optimized),
            'timestamp' => now()->toISOString(),
        ];
    }

    private function restoreWhitespace(string $content, array $metadata): string
    {
        // Basic whitespace restoration - would need more sophisticated tracking
        return $content;
    }

    private function restoreComments(string $content, array $metadata): string
    {
        // Basic comment restoration - would need to store removed comments
        return $content;
    }
}
