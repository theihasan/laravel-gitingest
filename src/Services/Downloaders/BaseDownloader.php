<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Services\Downloaders;

use Ihasan\LaravelGitingest\Contracts\DownloaderInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use React\Stream\WritableResourceStream;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Support\Collection;

abstract class BaseDownloader implements DownloaderInterface
{
    protected array $progress = [
        'downloaded' => 0,
        'total' => 0,
        'percentage' => 0,
        'speed' => 0,
        'status' => 'idle',
    ];

    public function __construct(
        protected readonly LoopInterface $loop,
        protected readonly Browser $browser,
        protected readonly int $timeout = 300,
    ) {}

    public function getProgress(): array
    {
        return $this->progress;
    }

    protected function downloadWithProgress(string $url, string $destination): PromiseInterface
    {
        $this->resetProgress();
        
        return $this->browser->get($url)
            ->then(function (ResponseInterface $response) use ($destination): PromiseInterface {
                return $this->streamResponseToFile($response, $destination);
            });
    }

    protected function streamResponseToFile(ResponseInterface $response, string $destination): PromiseInterface
    {
        $contentLength = (int) $response->getHeaderLine('Content-Length');
        $stream = new WritableResourceStream(fopen($destination, 'w'), $this->loop);
        
        $this->progress['total'] = $contentLength;
        $this->progress['status'] = 'downloading';

        return $this->trackProgressAndStream($response, $stream, $contentLength);
    }

    protected function trackProgressAndStream(
        ResponseInterface $response, 
        WritableResourceStream $stream, 
        int $contentLength
    ): PromiseInterface {
        $startTime = microtime(true);
        
        $response->getBody()->on('data', function (string $chunk) use ($startTime, $contentLength): void {
            $this->progress['downloaded'] += strlen($chunk);
            $this->updateProgressMetrics($startTime, $contentLength);
        });

        $response->getBody()->pipe($stream);
        
        return $stream->promise()->then(function () {
            $this->progress['status'] = 'completed';
            return $this->progress;
        });
    }

    private function updateProgressMetrics(float $startTime, int $contentLength): void
    {
        $elapsed = microtime(true) - $startTime;
        $this->progress['speed'] = $elapsed > 0 ? $this->progress['downloaded'] / $elapsed : 0;
        $this->progress['percentage'] = $contentLength > 0 ? 
            round(($this->progress['downloaded'] / $contentLength) * 100, 2) : 0;
    }

    private function resetProgress(): void
    {
        $this->progress = [
            'downloaded' => 0,
            'total' => 0,
            'percentage' => 0,
            'speed' => 0,
            'status' => 'idle',
        ];
    }

    abstract protected function buildHeaders(?string $token = null): array;
}
