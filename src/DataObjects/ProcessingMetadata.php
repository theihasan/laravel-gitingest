<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\DataObjects;

use Carbon\Carbon;
use JsonSerializable;

final readonly class ProcessingMetadata implements JsonSerializable
{
    public function __construct(
        public array $processingOptions,
        public string $packageVersion,
        public float $processingTime,
        public Carbon $processedAt,
        public int $memoryUsage,
        public int $peakMemoryUsage,
        public string $phpVersion,
        public string $laravelVersion,
        public array $timings = [],
    ) {}

    public function toArray(): array
    {
        return [
            'processing_options' => $this->processingOptions,
            'package_version' => $this->packageVersion,
            'processing_time' => $this->processingTime,
            'processed_at' => $this->processedAt->toISOString(),
            'memory_usage' => $this->memoryUsage,
            'peak_memory_usage' => $this->peakMemoryUsage,
            'php_version' => $this->phpVersion,
            'laravel_version' => $this->laravelVersion,
            'timings' => $this->timings,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        $memory = $this->formatBytes($this->memoryUsage);
        $peakMemory = $this->formatBytes($this->peakMemoryUsage);
        return "Processed in {$this->processingTime}s (Memory: {$memory}, Peak: {$peakMemory})";
    }

    public function getFormattedProcessingTime(): string
    {
        if ($this->processingTime < 1) {
            return round($this->processingTime * 1000) . 'ms';
        }
        
        if ($this->processingTime < 60) {
            return round($this->processingTime, 2) . 's';
        }
        
        $minutes = floor($this->processingTime / 60);
        $seconds = $this->processingTime % 60;
        return "{$minutes}m " . round($seconds, 1) . 's';
    }

    public function getFormattedMemoryUsage(): string
    {
        return $this->formatBytes($this->memoryUsage);
    }

    public function getFormattedPeakMemoryUsage(): string
    {
        return $this->formatBytes($this->peakMemoryUsage);
    }

    public function getTiming(string $stage): ?float
    {
        return $this->timings[$stage] ?? null;
    }

    public function hasTimings(): bool
    {
        return !empty($this->timings);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }

    public static function create(
        array $processingOptions,
        string $packageVersion,
        float $processingTime,
        array $timings = []
    ): self {
        return new self(
            processingOptions: $processingOptions,
            packageVersion: $packageVersion,
            processingTime: $processingTime,
            processedAt: Carbon::now(),
            memoryUsage: memory_get_usage(true),
            peakMemoryUsage: memory_get_peak_usage(true),
            phpVersion: PHP_VERSION,
            laravelVersion: app()->version(),
            timings: $timings,
        );
    }
}
