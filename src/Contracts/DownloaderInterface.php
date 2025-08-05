<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Contracts;

use React\Promise\PromiseInterface;

interface DownloaderInterface
{
    public function download(string $url, ?string $token = null): PromiseInterface;
    public function supports(string $url): bool;
    public function getProgress(): array;
}
