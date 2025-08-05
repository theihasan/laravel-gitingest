<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Data;

use Ihasan\LaravelGitingest\Enums\RepositoryType;

final readonly class RepositoryInfo
{
    public function __construct(
        public string $owner,
        public string $name,
        public string $branch,
        public string $fullName,
        public string $cloneUrl,
        public string $archiveUrl,
        public RepositoryType $type = RepositoryType::PUBLIC,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            owner: $data['owner'],
            name: $data['name'],
            branch: $data['branch'],
            fullName: $data['full_name'],
            cloneUrl: $data['clone_url'],
            archiveUrl: $data['archive_url'],
            type: RepositoryType::from($data['type'] ?? 'public'),
        );
    }

    public function toArray(): array
    {
        return [
            'owner' => $this->owner,
            'name' => $this->name,
            'branch' => $this->branch,
            'full_name' => $this->fullName,
            'clone_url' => $this->cloneUrl,
            'archive_url' => $this->archiveUrl,
            'type' => $this->type->value,
        ];
    }

    public function __toString(): string
    {
        return $this->fullName;
    }

    public function requiresAuthentication(): bool
    {
        return $this->type->requiresAuthentication();
    }

    public function getApiUrl(): string
    {
        return "https://api.github.com/repos/{$this->fullName}";
    }
}
