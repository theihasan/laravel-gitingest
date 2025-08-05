<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Services\Downloaders;

use React\Http\Browser;
use Illuminate\Support\Str;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Ihasan\LaravelGitingest\Services\GitHubApiClient;
use Ihasan\LaravelGitingest\Exceptions\AuthenticationException;

final class PrivateRepositoryDownloader extends BaseDownloader
{
    public function __construct(
        protected readonly LoopInterface $loop,
        protected readonly Browser $browser,
        protected readonly GitHubApiClient $apiClient,
        protected readonly int $timeout = 300,
    ) {
        parent::__construct($loop, $browser, $timeout);
    }

    public function download(string $url, ?string $token = null): PromiseInterface
    {
        if ($token === null) {
            throw new AuthenticationException('GitHub token required for private repositories');
        }

        if (!$this->supports($url)) {
            throw new \InvalidArgumentException("URL not supported by PrivateRepositoryDownloader");
        }

        return $this->validateTokenAndDownload($url, $token);
    }

    public function supports(string $url): bool
    {
        return Str::contains($url, 'github.com');
    }

    protected function buildHeaders(?string $token = null): array
    {
        if ($token === null) {
            throw new AuthenticationException('Token required for private repository access');
        }

        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Laravel-GitIngest/1.0',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }

    private function validateTokenAndDownload(string $url, string $token): PromiseInterface
    {
        return $this->apiClient->validateRepositoryAccess($url, $token)
            ->then(function (array $repoInfo) use ($token): PromiseInterface {
                return $this->downloadViaApi($repoInfo, $token);
            });
    }

    private function downloadViaApi(array $repoInfo, string $token): PromiseInterface
    {
        $archiveUrl = $repoInfo['archive_url'];
        $destination = $this->generateTempFilePath();

        // Use authenticated request for private repository
        $this->browser = $this->browser->withOptions([
            'headers' => $this->buildHeaders($token),
        ]);

        return $this->downloadWithProgress($archiveUrl, $destination)
            ->then(fn() => $destination);
    }

    private function generateTempFilePath(): string
    {
        $tempDir = sys_get_temp_dir();
        $filename = 'gitingest_private_' . uniqid() . '.zip';
        return "{$tempDir}/{$filename}";
    }
}
