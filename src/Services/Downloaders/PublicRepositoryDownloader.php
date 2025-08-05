<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Services\Downloaders;

use React\Promise\PromiseInterface;
use Ihasan\LaravelGitingest\Data\RepositoryInfo;
use Illuminate\Support\Str;

final class PublicRepositoryDownloader extends BaseDownloader
{
    public function download(string $url, ?string $token = null): PromiseInterface
    {
        if (!$this->supports($url)) {
            throw new \InvalidArgumentException("URL not supported by PublicRepositoryDownloader");
        }

        $archiveUrl = $this->buildArchiveUrl($url);
        $destination = $this->generateTempFilePath();

        return $this->downloadWithProgress($archiveUrl, $destination)
            ->then(fn() => $destination);
    }

    public function supports(string $url): bool
    {
        return Str::contains($url, 'github.com') && !$this->requiresAuthentication($url);
    }

    protected function buildHeaders(?string $token = null): array
    {
        $headers = [
            'User-Agent' => 'Laravel-GitIngest/1.0',
            'Accept' => 'application/vnd.github+json',
        ];

        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $headers;
    }

    private function buildArchiveUrl(string $repoUrl): string
    {
        // Convert github.com/user/repo to archive URL
        $pattern = '#github\.com/([^/]+)/([^/]+)#';
        
        if (preg_match($pattern, $repoUrl, $matches)) {
            $owner = $matches[1];
            $repo = Str::replace('.git', '', $matches[2]);
            return "https://github.com/{$owner}/{$repo}/archive/refs/heads/main.zip";
        }

        throw new \InvalidArgumentException("Invalid GitHub URL format");
    }

    private function generateTempFilePath(): string
    {
        $tempDir = sys_get_temp_dir();
        $filename = 'gitingest_' . uniqid() . '.zip';
        return "{$tempDir}/{$filename}";
    }

    private function requiresAuthentication(string $url): bool
    {
        // For now, assume all URLs are public
        return false;
    }
}
