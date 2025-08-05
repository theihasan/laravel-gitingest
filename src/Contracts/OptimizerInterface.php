<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Contracts;

use Illuminate\Support\Collection;

interface OptimizerInterface
{
    public function optimize(string $content, array $options = []): string;

    public function optimizeFiles(Collection $files, array $options = []): Collection;

    public function estimateTokenReduction(string $content, array $options = []): array;

    public function getOptimizationStrategies(): array;

    public function reverseOptimization(string $optimizedContent, array $metadata = []): string;
}
