<?php

declare(strict_types=1);

return [
    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'timeout' => env('GITINGEST_TIMEOUT', 300),
        'max_file_size' => env('GITINGEST_MAX_FILE_SIZE', 1024 * 1024), // 1MB
        'max_total_size' => env('GITINGEST_MAX_TOTAL_SIZE', 50 * 1024 * 1024), // 50MB
        'rate_limit_per_hour' => env('GITINGEST_RATE_LIMIT', 5000),
    ],
    
    'filtering' => [
        'respect_gitignore' => true,
        'exclude_extensions' => ['.jpg', '.png', '.gif', '.pdf', '.zip', '.exe'],
        'exclude_directories' => ['node_modules', 'vendor', '.git', 'storage', 'bootstrap/cache'],
        'include_patterns' => ['*.php', '*.js', '*.vue', '*.blade.php', '*.md', '*.json'],
        'max_file_lines' => 1000,
    ],
    
    'optimization' => [
        'max_tokens' => [
            'gpt-4' => 128000,
            'gpt-3.5-turbo' => 16000,
            'claude-3-opus' => 200000,
            'claude-3-sonnet' => 200000,
            'default' => 100000,
        ],
        'compression_level' => 'medium', // none, basic, medium, aggressive
        'remove_comments' => false,
        'remove_empty_lines' => true,
        'chunk_large_repositories' => true,
    ],
    
    'output' => [
        'default_format' => 'text', // text, markdown, json
        'include_file_tree' => true,
        'include_statistics' => true,
        'add_file_separators' => true,
        'output_directory' => storage_path('app/gitingest'),
    ],
    
    'reactphp' => [
        'concurrent_downloads' => 5,
        'chunk_size' => 8192,
        'memory_limit' => '512M',
    ],
];
