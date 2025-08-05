<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Contracts;

use Illuminate\Support\Collection;

interface ProcessorInterface
{
    public function process(string $path, array $options = []): array;
    public function getStatistics(): array;
    public function getProcessedFiles(): Collection;
}
