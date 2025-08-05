<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Services;

use Ihasan\LaravelGitingest\Contracts\ParserInterface;
use Ihasan\LaravelGitingest\Exceptions\InvalidUrlException;
use Illuminate\Support\Str;

final readonly class GitHubUrlParser implements ParserInterface
{
    private const GITHUB_PATTERNS = [
        '#^https?://github\.com/([^/]+)/([^/]+?)(?:\.git)?/?(?:\?.*)?$#',
        '#^git@github\.com:([^/]+)/([^/]+?)(?:\.git)?$#',
        '#^https?://github\.com/([^/]+)/([^/]+)/tree/([^/]+)/?#',
    ];

    public function parse(string $url): array
    {
        if (!$this->isValidGitHubUrl($url)) {
            throw InvalidUrlException::invalidFormat($url);
        }

        return collect(self::GITHUB_PATTERNS)
            ->map(fn(string $pattern): ?array => $this->matchPattern($pattern, $url))
            ->filter()
            ->first() ?? throw InvalidUrlException::missingRepository($url);
    }

    public function isValidGitHubUrl(string $url): bool
    {
        return collect(self::GITHUB_PATTERNS)
            ->contains(fn(string $pattern): bool => preg_match($pattern, $url) === 1);
    }

    public function extractRepositoryInfo(string $url): array
    {
        $matches = $this->parse($url);
        
        return [
            'owner' => $matches['owner'],
            'name' => Str::replace(['.git'], '', $matches['name']),
            'branch' => $matches['branch'] ?? 'main',
            'full_name' => "{$matches['owner']}/{$matches['name']}",
            'clone_url' => $this->buildCloneUrl($matches['owner'], $matches['name']),
            'archive_url' => $this->buildArchiveUrl($matches['owner'], $matches['name'], $matches['branch'] ?? 'main'),
        ];
    }

    private function matchPattern(string $pattern, string $url): ?array
    {
        if (preg_match($pattern, $url, $matches)) {
            return [
                'owner' => $matches[1],
                'name' => $matches[2],
                'branch' => $matches[3] ?? null,
            ];
        }
        
        return null;
    }

    private function buildCloneUrl(string $owner, string $name): string
    {
        return "https://github.com/{$owner}/{$name}.git";
    }

    private function buildArchiveUrl(string $owner, string $name, string $branch): string
    {
        return "https://github.com/{$owner}/{$name}/archive/refs/heads/{$branch}.zip";
    }
}
