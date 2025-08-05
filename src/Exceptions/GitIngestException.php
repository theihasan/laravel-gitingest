<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Exceptions;

use Exception;

class GitIngestException extends Exception
{
    protected array $context = [];

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
