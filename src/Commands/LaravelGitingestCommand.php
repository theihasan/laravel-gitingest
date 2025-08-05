<?php

namespace Ihasan\LaravelGitingest\Commands;

use Illuminate\Console\Command;

class LaravelGitingestCommand extends Command
{
    public $signature = 'laravel-gitingest';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
