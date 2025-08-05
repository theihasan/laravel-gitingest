<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Contracts;

interface ParserInterface
{
    public function parse(string $url): array;
    public function isValidGitHubUrl(string $url): bool;
    public function extractRepositoryInfo(string $url): array;
}
