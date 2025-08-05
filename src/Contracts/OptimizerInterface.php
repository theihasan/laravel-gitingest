<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Contracts;

interface OptimizerInterface
{
    public function optimize(string $content, string $model): string;
    public function countTokens(string $content): int;
    public function canOptimize(string $content, int $maxTokens): bool;
}
