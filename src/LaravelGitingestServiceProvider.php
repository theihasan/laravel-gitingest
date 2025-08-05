<?php

namespace Ihasan\LaravelGitingest;

use Ihasan\LaravelGitingest\Commands\LaravelGitingestCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelGitingestServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-gitingest')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_gitingest_table')
            ->hasCommand(LaravelGitingestCommand::class);
    }
}
