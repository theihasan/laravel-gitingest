<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Ihasan\LaravelGitingest\Commands\GitIngestCommand;
use Ihasan\LaravelGitingest\Commands\AnalyzeRepositoryCommand;
use Ihasan\LaravelGitingest\Services\GitIngestService;
use Ihasan\LaravelGitingest\Services\Downloaders\PublicRepositoryDownloader;
use Ihasan\LaravelGitingest\Services\Downloaders\PrivateRepositoryDownloader;
use Ihasan\LaravelGitingest\Services\Processors\ZipProcessor;
use Ihasan\LaravelGitingest\Services\Processors\ContentProcessor;
use Ihasan\LaravelGitingest\Services\FileFilter;
use Ihasan\LaravelGitingest\Services\TokenCounter;
use Ihasan\LaravelGitingest\Services\Optimizers\ContentOptimizer;
use Ihasan\LaravelGitingest\Services\ContentChunker;
use React\EventLoop\LoopInterface;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Filesystem\Filesystem;

final class LaravelGitingestServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-gitingest')
            ->hasConfigFile()
            ->hasCommands([
                GitIngestCommand::class,
                AnalyzeRepositoryCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register core services here
        $this->registerCoreServices();
    }

    private function registerCoreServices(): void
    {
        // Register ReactPHP dependencies as singletons
        $this->app->singleton(LoopInterface::class, fn() => Loop::get());
        
        $this->app->singleton(Browser::class, function ($app) {
            return new Browser($app->make(LoopInterface::class));
        });

        $this->app->singleton(Filesystem::class, function ($app) {
            return Filesystem::create($app->make(LoopInterface::class));
        });

        // Register core services
        $this->app->singleton(PublicRepositoryDownloader::class);
        $this->app->singleton(PrivateRepositoryDownloader::class);
        $this->app->singleton(ZipProcessor::class);
        $this->app->singleton(FileFilter::class);
        $this->app->singleton(ContentProcessor::class);
        $this->app->singleton(TokenCounter::class);
        $this->app->singleton(ContentOptimizer::class);
        $this->app->singleton(ContentChunker::class);

        // Register main service
        $this->app->singleton(GitIngestService::class);
    }
}
