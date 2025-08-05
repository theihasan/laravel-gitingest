<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Ihasan\LaravelGitingest\Commands\GitIngestCommand;

final class LaravelGitingestServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-gitingest')
            ->hasConfigFile()
            ->hasCommand(GitIngestCommand::class);
    }

    public function packageRegistered(): void
    {
        // Register core services here
        $this->registerCoreServices();
    }

    private function registerCoreServices(): void
    {
        // Service registrations will be added in later steps
    }
}
