# Laravel GitIngest

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ihasan/laravel-gitingest.svg?style=flat-square)](https://packagist.org/packages/ihasan/laravel-gitingest)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ihasan/laravel-gitingest/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ihasan/laravel-gitingest/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/ihasan/laravel-gitingest/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/ihasan/laravel-gitingest/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ihasan/laravel-gitingest.svg?style=flat-square)](https://packagist.org/packages/ihasan/laravel-gitingest)

A powerful Laravel package for ingesting and processing GitHub repositories for AI analysis. Extract, optimize, and chunk repository content efficiently with support for multiple AI models, advanced filtering, and comprehensive caching.

## ✨ Features

- 🚀 **Fast Repository Processing** - Asynchronous downloading and extraction using ReactPHP
- 🔐 **Public & Private Repositories** - Support for both public and private GitHub repositories
- 🎯 **Smart Content Filtering** - Advanced filtering by file types, sizes, and directories
- 🧮 **Token Optimization** - Intelligent content optimization and token counting for AI models
- 📦 **Automatic Chunking** - Split large repositories into manageable chunks
- 💰 **Cost Estimation** - Calculate API costs before processing
- 📊 **Rich Analytics** - Detailed analysis and statistics
- ⚡ **Caching Support** - Built-in caching to avoid reprocessing
- 🎨 **Multiple Output Formats** - Markdown, JSON, and plain text outputs
- 🛠️ **Artisan Commands** - Powerful CLI tools for analysis and processing

## 📋 Requirements

- PHP 8.3+
- Laravel 12+
- ReactPHP (automatically installed)
- Optional: tiktoken for accurate token counting

## 🚀 Installation

Install the package via Composer:

```bash
composer require ihasan/laravel-gitingest
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="laravel-gitingest-config"
```

Run the setup command to verify your installation:

```bash
php artisan gitingest:setup
```

## ⚙️ Configuration

The package configuration is published to `config/gitingest.php`. Here are the key settings:

```php
return [
    // Default AI model for token optimization
    'default_model' => env('GITINGEST_DEFAULT_MODEL', 'gpt-4'),
    
    // File filtering settings
    'filter' => [
        'max_file_size' => env('GITINGEST_MAX_FILE_SIZE', 1024 * 1024), // 1MB
        'allowed_extensions' => ['php', 'js', 'ts', 'py', 'java', 'go', 'rs', 'cpp', 'c', 'h'],
        'ignored_directories' => ['node_modules', 'vendor', '.git', 'dist', 'build'],
    ],
    
    // Content optimization
    'optimization' => [
        'default_level' => env('GITINGEST_OPTIMIZATION_LEVEL', 1),
        'preserve_structure' => true,
    ],
    
    // Chunking settings
    'chunking' => [
        'default_strategy' => 'semantic',
        'max_tokens_per_chunk' => 100000,
        'overlap_tokens' => 1000,
    ],
    
    // Output formatting
    'format' => [
        'default' => 'markdown',
        'include_tree' => true,
        'add_separators' => true,
    ],
    
    // Caching
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'key_prefix' => 'gitingest',
    ],
];
```

### Environment Variables

Add these to your `.env` file:

```env
# Default AI model
GITINGEST_DEFAULT_MODEL=gpt-4

# File filtering
GITINGEST_MAX_FILE_SIZE=1048576
GITINGEST_OPTIMIZATION_LEVEL=1

# GitHub token for private repositories (optional)
GITHUB_TOKEN=your_github_personal_access_token
```

## 🎯 Quick Start

### Basic Usage

```bash
# Process a public repository
php artisan gitingest:process https://github.com/laravel/framework

# Process with custom options
php artisan gitingest:process https://github.com/owner/repo \
    --format=markdown \
    --output=analysis.md \
    --model=gpt-4

# Interactive mode for guided setup
php artisan gitingest:process https://github.com/owner/repo --interactive
```

### Repository Analysis (without full processing)

```bash
# Analyze repository for statistics and estimates
php artisan gitingest:analyze https://github.com/laravel/framework

# Batch analysis of multiple repositories
php artisan gitingest:analyze \
    https://github.com/laravel/framework \
    https://github.com/symfony/symfony \
    --include-costs \
    --output=analysis-report.json
```

### Private Repositories

```bash
# Using command-line token
php artisan gitingest:process https://github.com/owner/private-repo \
    --token=ghp_your_token_here

# Using environment variable (recommended)
GITHUB_TOKEN=ghp_your_token_here php artisan gitingest:process https://github.com/owner/private-repo
```

## 💻 Programmatic Usage

### Using the GitIngest Service

```php
use Ihasan\LaravelGitingest\Services\GitIngestService;

class AnalysisController extends Controller
{
    public function analyzeRepository(GitIngestService $gitIngest)
    {
        $result = $gitIngest
            ->onProgress(function ($progress) {
                // Handle progress updates
                Log::info("Progress: {$progress['percentage']}% - {$progress['message']}");
            })
            ->setCacheEnabled(true)
            ->processRepository('https://github.com/laravel/framework', [
                'model' => 'gpt-4',
                'format' => ['type' => 'markdown'],
                'filter' => [
                    'allowed_extensions' => ['php', 'js'],
                    'max_file_size' => 500 * 1024, // 500KB
                ],
                'optimization' => [
                    'enabled' => true,
                    'level' => 2,
                ],
                'chunking' => [
                    'enabled' => true,
                    'max_tokens' => 50000,
                ],
            ]);

        // Work with the strongly-typed result
        if ($result->isSuccessful()) {
            $stats = $result->statistics;
            $content = $result->content;
            $chunks = $result->getChunks();
            
            return response()->json([
                'repository' => $result->repositoryUrl,
                'files' => $result->getFileCount(),
                'tokens' => $stats->totalTokens,
                'chunked' => $result->isChunked,
                'processing_time' => $result->metadata->getFormattedProcessingTime(),
            ]);
        }
        
        return response()->json(['errors' => $result->getErrors()], 422);
    }
}
```

### Working with Results

```php
use Ihasan\LaravelGitingest\DataObjects\ProcessingResult;

public function handleResult(ProcessingResult $result)
{
    // Check if processing was successful
    if (!$result->isSuccessful()) {
        foreach ($result->getErrors() as $error) {
            Log::error("Processing error: {$error}");
        }
        return;
    }

    // Access token statistics
    $stats = $result->statistics;
    echo "Total tokens: {$stats->totalTokens}\n";
    echo "Model: {$stats->model}\n";
    echo "Utilization: {$stats->getUtilizationPercentage()}%\n";

    // Access file content
    foreach ($result->content as $path => $fileData) {
        echo "File: {$path}\n";
        echo "Content: {$fileData['content']}\n\n";
    }

    // Handle chunks if repository was chunked
    if ($result->isChunked) {
        foreach ($result->getChunks() as $chunk) {
            echo "Chunk {$chunk->chunkId}: {$chunk->tokens} tokens\n";
            echo "Files: " . $chunk->getFileCount() . "\n";
            echo "Content preview: " . $chunk->getContentPreview(100) . "\n\n";
        }
    }

    // Export as different formats
    file_put_contents('result.json', $result->toPrettyJson());
    file_put_contents('summary.md', $result->getFormattedSummary());
}
```

## 🔧 Advanced Usage

### Custom File Filtering

```bash
# Filter by specific file extensions
php artisan gitingest:process https://github.com/owner/repo \
    --allowed-extensions=php,js,ts,vue \
    --ignored-directories=vendor,node_modules,storage

# Custom file size limits
php artisan gitingest:process https://github.com/owner/repo \
    --max-file-size=2097152  # 2MB
```

### Content Optimization Levels

```bash
# Level 0: No optimization
php artisan gitingest:process https://github.com/owner/repo --optimization-level=0

# Level 1: Basic (remove extra whitespace, comments)
php artisan gitingest:process https://github.com/owner/repo --optimization-level=1

# Level 2: Moderate (+ remove debug statements, logs)
php artisan gitingest:process https://github.com/owner/repo --optimization-level=2

# Level 3: Aggressive (+ minify code structure)
php artisan gitingest:process https://github.com/owner/repo --optimization-level=3
```

### Chunking Strategies

```bash
# Enable chunking for large repositories
php artisan gitingest:process https://github.com/owner/large-repo \
    --chunk \
    --chunk-size=75000

# Different chunking strategies (when using programmatically)
$result = $gitIngest->processRepository($url, [
    'chunking' => [
        'enabled' => true,
        'strategy' => 'semantic',     // 'semantic', 'file', 'size'
        'max_tokens' => 100000,
        'overlap_tokens' => 2000,
    ],
]);
```

## 📊 Cost Estimation

Get cost estimates before processing:

```bash
# Analyze with cost estimation
php artisan gitingest:analyze https://github.com/owner/repo \
    --include-costs \
    --model=gpt-4

# Sample output:
# Estimated Tokens: 85,432
# Estimated Cost: $2.56 (Input: $2.56, Output: $0.00)
# Model: gpt-4 ($0.03 per 1K tokens)
```

## 🎨 Output Formats

### Markdown (Default)

```bash
php artisan gitingest:process https://github.com/owner/repo \
    --format=markdown \
    --output=repository-analysis.md
```

### JSON (Structured Data)

```bash
php artisan gitingest:process https://github.com/owner/repo \
    --format=json \
    --output=repository-data.json
```

### Plain Text

```bash
php artisan gitingest:process https://github.com/owner/repo \
    --format=text \
    --output=repository.txt
```

## 🔍 Troubleshooting

### Verify Installation

```bash
# Check package setup and configuration
php artisan gitingest:doctor

# Validate environment and dependencies
php artisan gitingest:check-env

# Test GitHub token (if using private repos)
php artisan gitingest:test-token
```

### Performance Testing

```bash
# Test processing performance with sample repository
php artisan gitingest:benchmark

# Test with custom parameters
php artisan gitingest:benchmark \
    --repository=https://github.com/laravel/framework \
    --iterations=3
```

### Common Issues

#### 1. GitHub Rate Limiting

**Problem**: `GitHub API rate limit exceeded`

**Solution**: 
- Use a GitHub Personal Access Token
- Wait for rate limit reset
- Use smaller repositories for testing

```bash
# Generate token at: https://github.com/settings/tokens
export GITHUB_TOKEN=your_token_here
php artisan gitingest:process https://github.com/owner/repo
```

#### 2. Memory Issues

**Problem**: `Fatal error: Allowed memory size exhausted`

**Solution**:
- Increase PHP memory limit
- Use content optimization
- Enable chunking for large repositories

```bash
# Increase memory temporarily
php -d memory_limit=512M artisan gitingest:process https://github.com/owner/repo

# Use optimization and chunking
php artisan gitingest:process https://github.com/owner/repo \
    --optimization-level=2 \
    --chunk \
    --chunk-size=50000
```

#### 3. Token Counting Accuracy

**Problem**: Inaccurate token counts

**Solution**: Install tiktoken for precise counting:

```bash
# Install tiktoken (requires Python)
pip install tiktoken

# Verify installation
php artisan gitingest:doctor
```

## 🚀 Performance Tuning

### Optimization Tips

1. **Use Caching**: Enable caching to avoid reprocessing identical repositories
2. **Filter Aggressively**: Use specific file extensions and ignore unnecessary directories
3. **Optimize Content**: Use appropriate optimization levels for your use case
4. **Chunk Large Repos**: Enable chunking for repositories > 100K tokens
5. **Use Tokens Wisely**: Test with `gitingest:analyze` before full processing

### Memory Management

```php
// For large repositories, use chunking
$result = $gitIngest->processRepository($url, [
    'chunking' => [
        'enabled' => true,
        'max_tokens' => 75000, // Smaller chunks for memory efficiency
    ],
    'optimization' => [
        'level' => 2, // Remove unnecessary content
    ],
]);
```

### Batch Processing

```php
// Process multiple repositories efficiently
$repositories = [
    'https://github.com/owner/repo1',
    'https://github.com/owner/repo2',
    'https://github.com/owner/repo3',
];

foreach ($repositories as $repo) {
    try {
        $result = $gitIngest
            ->setCacheEnabled(true)
            ->processRepository($repo, $options);
        
        // Process result...
        
    } catch (Exception $e) {
        Log::error("Failed to process {$repo}: {$e->getMessage()}");
        continue;
    }
    
    // Memory cleanup between repositories
    gc_collect_cycles();
}
```

## 🔒 Security Best Practices

### Token Management

1. **Never commit tokens** to version control
2. **Use environment variables** for token storage
3. **Rotate tokens regularly** 
4. **Use minimal scope** tokens (only `repo` scope for private repos)

```env
# .env (never commit this file)
GITHUB_TOKEN=ghp_your_secure_token_here

# .env.example (safe to commit)
GITHUB_TOKEN=your_github_token_here
```

### Access Control

```php
// Validate repository access before processing
if (!$this->userCanAccessRepository($user, $repositoryUrl)) {
    throw new UnauthorizedException('Access denied to repository');
}

$result = $gitIngest->processRepository($repositoryUrl, $options);
```

### Content Sanitization

```php
// Sanitize output for public display
$sanitizedContent = $this->sanitizeRepositoryContent($result->content);
```

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run specific test types
composer test:unit
composer test:feature
composer test:performance
```

Test with real repositories:

```bash
# Test with public repository
php artisan gitingest:test \
    --repository=https://github.com/spatie/laravel-package-tools

# Test with your own repository
php artisan gitingest:test \
    --repository=https://github.com/yourusername/yourrepo \
    --token=your_token
```

## 📚 API Reference

### GitIngestService

#### Methods

- `processRepository(string $url, array $options = []): ProcessingResult`
- `onProgress(callable $callback): self`
- `setCacheEnabled(bool $enabled): self`
- `setCacheTime(int $seconds): self`

#### Options

```php
$options = [
    'model' => 'gpt-4',              // AI model to optimize for
    'token' => 'github_token',       // GitHub access token
    'format' => [
        'type' => 'markdown',        // Output format
        'include_tree' => true,      // Include directory tree
    ],
    'filter' => [
        'max_file_size' => 1048576,              // Max file size in bytes
        'allowed_extensions' => ['php', 'js'],   // Allowed extensions
        'ignored_directories' => ['vendor'],     // Directories to ignore
    ],
    'optimization' => [
        'enabled' => true,           // Enable optimization
        'level' => 1,               // Optimization level (0-3)
    ],
    'chunking' => [
        'enabled' => false,         // Enable chunking
        'strategy' => 'semantic',   // Chunking strategy
        'max_tokens' => 100000,     // Max tokens per chunk
        'overlap_tokens' => 1000,   // Overlap between chunks
    ],
];
```

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/ihasan/laravel-gitingest.git
cd laravel-gitingest

# Install dependencies
composer install

# Set up testing environment
cp .env.example .env.testing
php artisan key:generate --env=testing

# Run tests
composer test
```

## 📜 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🔐 Security

If you discover any security-related issues, please email security@example.com instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🙏 Credits

- [Ihasan](https://github.com/ihasan)
- [All Contributors](../../contributors)

Built with ❤️ using:
- [Spatie Laravel Package Tools](https://github.com/spatie/laravel-package-tools)
- [ReactPHP](https://reactphp.org/)
- [Laravel](https://laravel.com/)

---

## 📞 Support

- 📖 [Documentation](https://github.com/ihasan/laravel-gitingest/wiki)
- 🐛 [Issue Tracker](https://github.com/ihasan/laravel-gitingest/issues)
- 💬 [Discussions](https://github.com/ihasan/laravel-gitingest/discussions)
- 📧 [Email Support](mailto:support@example.com)

---

**Made with ❤️ for the Laravel community**
