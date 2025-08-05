<?php

namespace Ihasan\LaravelGitingest\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ihasan\LaravelGitingest\LaravelGitingest
 */
class LaravelGitingest extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ihasan\LaravelGitingest\LaravelGitingest::class;
    }
}
