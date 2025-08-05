<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Model
    |--------------------------------------------------------------------------
    |
    | The default AI model to optimize content for. This affects token counting,
    | content optimization strategies, and chunking decisions.
    |
    | Supported models: gpt-4, gpt-4-turbo, claude-3-opus, claude-3-sonnet, gpt-3.5-turbo
    |
    */
    'default_model' => env('GITINGEST_DEFAULT_MODEL', 'gpt-4'),

    /*
    |--------------------------------------------------------------------------
    | File Filtering Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control which files are included in repository processing.
    | Proper filtering can significantly improve performance and reduce costs.
    |
    */
    'filter' => [
        // Maximum file size in bytes (default: 1MB)
        'max_file_size' => env('GITINGEST_MAX_FILE_SIZE', 1024 * 1024),

        // Allowed file extensions (empty array = allow all)
        'allowed_extensions' => [
            // Programming languages
            'php', 'js', 'ts', 'jsx', 'tsx', 'vue', 'svelte',
            'py', 'java', 'go', 'rs', 'cpp', 'c', 'h', 'hpp',
            'cs', 'rb', 'swift', 'kt', 'scala', 'dart', 'lua',
            
            // Web technologies
            'html', 'css', 'scss', 'sass', 'less', 'xml',
            
            // Configuration and data
            'json', 'yaml', 'yml', 'toml', 'ini', 'env',
            'sql', 'graphql', 'gql',
            
            // Documentation
            'md', 'rst', 'txt', 'adoc',
            
            // Shell scripts
            'sh', 'bash', 'zsh', 'fish', 'ps1', 'bat',
        ],

        // Directories to ignore
        'ignored_directories' => [
            // Dependencies
            'node_modules', 'vendor', 'packages',
            
            // Build outputs
            'dist', 'build', 'target', 'out', 'bin',
            
            // Version control
            '.git', '.svn', '.hg',
            
            // IDE and editor files
            '.vscode', '.idea', '.vs',
            
            // Cache and temporary
            'cache', 'tmp', 'temp', '.cache',
            
            // Logs
            'logs', 'log',
            
            // Framework specific
            'storage', 'bootstrap/cache',
        ],

        // File patterns to ignore (gitignore style)
        'ignored_patterns' => [
            '*.log',
            '*.tmp',
            '*.temp',
            '*.cache',
            '*.lock',
            '*.pid',
            '.DS_Store',
            'Thumbs.db',
            '*.min.js',
            '*.min.css',
        ],

        // Apply gitignore rules from repository
        'respect_gitignore' => true,

        // Skip binary files
        'skip_binary_files' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Optimization
    |--------------------------------------------------------------------------
    |
    | Content optimization helps reduce token usage by removing unnecessary
    | content while preserving code functionality and readability.
    |
    */
    'optimization' => [
        // Default optimization level (0-3)
        'default_level' => env('GITINGEST_OPTIMIZATION_LEVEL', 1),

        // Preserve code structure and formatting
        'preserve_structure' => true,

        // Remove comments at higher optimization levels
        'remove_comments' => false,

        // Remove debug statements and logs
        'remove_debug_statements' => true,

        // Minify whitespace (level 2+)
        'minify_whitespace' => false,

        // Custom optimization rules
        'custom_rules' => [
            // Add custom optimization patterns here
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Chunking
    |--------------------------------------------------------------------------
    |
    | Large repositories are automatically chunked to fit within AI model
    | token limits. These settings control the chunking behavior.
    |
    */
    'chunking' => [
        // Default chunking strategy
        'default_strategy' => 'semantic',

        // Maximum tokens per chunk
        'max_tokens_per_chunk' => 100000,

        // Overlap between chunks (for context preservation)
        'overlap_tokens' => 1000,

        // Minimum chunk size (avoid tiny chunks)
        'min_chunk_size' => 5000,

        // Chunking strategies configuration
        'strategies' => [
            'semantic' => [
                'preserve_file_boundaries' => true,
                'group_related_files' => true,
                'maintain_dependencies' => true,
            ],
            'file' => [
                'files_per_chunk' => 50,
                'respect_directory_structure' => true,
            ],
            'size' => [
                'target_size_ratio' => 0.9, // 90% of max tokens
                'allow_file_splitting' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Formatting
    |--------------------------------------------------------------------------
    |
    | Configure how processed repository content is formatted for output.
    |
    */
    'format' => [
        // Default output format
        'default' => 'markdown',

        // Include directory tree in output
        'include_tree' => true,

        // Add file separators
        'add_separators' => true,

        // Include file metadata (size, lines, etc.)
        'include_metadata' => true,

        // Format-specific settings
        'markdown' => [
            'syntax_highlighting' => true,
            'table_of_contents' => true,
            'file_links' => true,
        ],
        'json' => [
            'pretty_print' => true,
            'include_raw_content' => true,
        ],
        'text' => [
            'line_numbers' => false,
            'file_headers' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Caching helps avoid reprocessing identical repositories and can
    | significantly improve performance for repeated operations.
    |
    */
    'cache' => [
        // Enable caching
        'enabled' => env('GITINGEST_CACHE_ENABLED', true),

        // Cache TTL in seconds (default: 1 hour)
        'ttl' => env('GITINGEST_CACHE_TTL', 3600),

        // Cache key prefix
        'key_prefix' => 'gitingest',

        // Cache driver (uses Laravel's default if null)
        'driver' => env('GITINGEST_CACHE_DRIVER'),

        // What to cache
        'cache_downloads' => true,
        'cache_processing' => true,
        'cache_token_counts' => true,
        'cache_analysis' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | GitHub Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for GitHub API access and repository handling.
    |
    */
    'github' => [
        // GitHub API base URL
        'api_url' => 'https://api.github.com',

        // GitHub personal access token (for private repos)
        'token' => env('GITHUB_TOKEN'),

        // API request timeout in seconds
        'timeout' => 30,

        // Retry failed requests
        'retry_attempts' => 3,

        // Rate limiting
        'respect_rate_limits' => true,
        'rate_limit_buffer' => 100, // Keep 100 requests as buffer

        // User agent for API requests
        'user_agent' => 'Laravel-GitIngest/1.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Settings that affect performance and resource usage.
    |
    */
    'performance' => [
        // Memory limit for processing (null = use PHP default)
        'memory_limit' => env('GITINGEST_MEMORY_LIMIT'),

        // Maximum execution time (null = use PHP default)
        'max_execution_time' => env('GITINGEST_MAX_EXECUTION_TIME'),

        // Parallel processing (when supported)
        'parallel_processing' => false,
        'max_parallel_jobs' => 4,

        // Progress reporting interval (in percentage)
        'progress_interval' => 5,

        // Cleanup temporary files
        'cleanup_temp_files' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how GitIngest logs its operations and errors.
    |
    */
    'logging' => [
        // Log channel (uses Laravel's default if null)
        'channel' => env('GITINGEST_LOG_CHANNEL'),

        // Log levels for different operations
        'levels' => [
            'processing' => 'info',
            'errors' => 'error',
            'performance' => 'debug',
            'api_requests' => 'debug',
        ],

        // Log processing statistics
        'log_statistics' => true,

        // Log GitHub API usage
        'log_api_usage' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security-related settings for safe repository processing.
    |
    */
    'security' => [
        // Validate repository URLs
        'validate_urls' => true,

        // Allowed domains for repository URLs
        'allowed_domains' => [
            'github.com',
            'api.github.com',
        ],

        // Maximum repository size (in bytes)
        'max_repository_size' => 100 * 1024 * 1024, // 100MB

        // Scan for sensitive content
        'scan_for_secrets' => true,

        // Secret patterns to detect
        'secret_patterns' => [
            '/[a-zA-Z0-9_-]*password[a-zA-Z0-9_-]*\s*[:=]\s*["\']?([^"\'\s]+)/i',
            '/[a-zA-Z0-9_-]*secret[a-zA-Z0-9_-]*\s*[:=]\s*["\']?([^"\'\s]+)/i',
            '/[a-zA-Z0-9_-]*token[a-zA-Z0-9_-]*\s*[:=]\s*["\']?([^"\'\s]+)/i',
            '/[a-zA-Z0-9_-]*key[a-zA-Z0-9_-]*\s*[:=]\s*["\']?([^"\'\s]+)/i',
        ],

        // Quarantine suspicious files
        'quarantine_suspicious_files' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Model-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for different AI models and their specific requirements.
    |
    */
    'models' => [
        'gpt-4' => [
            'max_tokens' => 128000,
            'cost_per_1k_input' => 0.03,
            'cost_per_1k_output' => 0.06,
            'supports_function_calling' => true,
        ],
        'gpt-4-turbo' => [
            'max_tokens' => 128000,
            'cost_per_1k_input' => 0.01,
            'cost_per_1k_output' => 0.03,
            'supports_function_calling' => true,
        ],
        'claude-3-opus' => [
            'max_tokens' => 200000,
            'cost_per_1k_input' => 0.015,
            'cost_per_1k_output' => 0.075,
            'supports_function_calling' => false,
        ],
        'claude-3-sonnet' => [
            'max_tokens' => 200000,
            'cost_per_1k_input' => 0.003,
            'cost_per_1k_output' => 0.015,
            'supports_function_calling' => false,
        ],
        'gpt-3.5-turbo' => [
            'max_tokens' => 16385,
            'cost_per_1k_input' => 0.0015,
            'cost_per_1k_output' => 0.002,
            'supports_function_calling' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Configuration
    |--------------------------------------------------------------------------
    |
    | Settings useful during development and debugging.
    |
    */
    'development' => [
        // Enable debug mode
        'debug' => env('GITINGEST_DEBUG', false),

        // Save intermediate processing results
        'save_intermediate_results' => false,

        // Detailed error reporting
        'detailed_errors' => env('APP_DEBUG', false),

        // Performance profiling
        'enable_profiling' => false,

        // Test mode (use mock data)
        'test_mode' => env('GITINGEST_TEST_MODE', false),
    ],
];
