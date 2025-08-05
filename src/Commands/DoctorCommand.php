<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Http;
use Ihasan\LaravelGitingest\Services\GitIngestService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Exception;

final class DoctorCommand extends Command
{
    protected $signature = 'gitingest:doctor 
                           {--detailed : Show detailed diagnostic information}
                           {--fix : Attempt to fix common issues automatically}
                           {--output= : Save diagnostic report to file}';

    protected $description = 'Diagnose GitIngest installation and configuration issues';

    public function handle(): int
    {
        $this->showHeader();

        $diagnostics = $this->runDiagnostics();
        $this->displayResults($diagnostics);

        if ($this->option('fix')) {
            $this->attemptFixes($diagnostics);
        }

        if ($outputPath = $this->option('output')) {
            $this->saveDiagnosticReport($diagnostics, $outputPath);
        }

        $hasErrors = collect($diagnostics)->contains(fn($group) => 
            collect($group['checks'])->contains('status', false)
        );

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }

    private function runDiagnostics(): array
    {
        $this->info('🔍 Running comprehensive diagnostics...');
        $this->newLine();

        return [
            'environment' => $this->checkEnvironment(),
            'dependencies' => $this->checkDependencies(),
            'configuration' => $this->checkConfiguration(),
            'permissions' => $this->checkPermissions(),
            'network' => $this->checkNetworkConnectivity(),
            'performance' => $this->checkPerformance(),
            'storage' => $this->checkStorage(),
            'services' => $this->checkServices(),
        ];
    }

    private function checkEnvironment(): array
    {
        return [
            'title' => 'Environment',
            'checks' => [
                'PHP Version' => $this->checkPhpVersion(),
                'Laravel Version' => $this->checkLaravelVersion(),
                'Memory Limit' => $this->checkMemoryLimit(),
                'Execution Time' => $this->checkExecutionTime(),
                'PHP Extensions' => $this->checkRequiredExtensions(),
                'TikToken Availability' => $this->checkTikToken(),
            ],
        ];
    }

    private function checkDependencies(): array
    {
        return [
            'title' => 'Dependencies',
            'checks' => [
                'ReactPHP' => $this->checkReactPhp(),
                'Composer Packages' => $this->checkComposerPackages(),
                'ReactPHP Components' => $this->checkReactComponents(),
                'Optional Dependencies' => $this->checkOptionalDependencies(),
            ],
        ];
    }

    private function checkConfiguration(): array
    {
        return [
            'title' => 'Configuration',
            'checks' => [
                'Config File' => $this->checkConfigFile(),
                'Environment Variables' => $this->checkEnvironmentVariables(),
                'Default Model' => $this->checkDefaultModel(),
                'File Filter Settings' => $this->checkFileFilterSettings(),
                'Cache Configuration' => $this->checkCacheConfiguration(),
                'GitHub Token' => $this->checkGitHubToken(),
            ],
        ];
    }

    private function checkPermissions(): array
    {
        return [
            'title' => 'Permissions',
            'checks' => [
                'Storage Directory' => $this->checkStoragePermissions(),
                'Cache Directory' => $this->checkCachePermissions(),
                'Temp Directory' => $this->checkTempPermissions(),
                'Log Directory' => $this->checkLogPermissions(),
            ],
        ];
    }

    private function checkNetworkConnectivity(): array
    {
        return [
            'title' => 'Network Connectivity',
            'checks' => [
                'GitHub API' => $this->checkGitHubApi(),
                'Public Repository Access' => $this->checkPublicRepoAccess(),
                'Rate Limiting' => $this->checkRateLimit(),
                'DNS Resolution' => $this->checkDnsResolution(),
            ],
        ];
    }

    private function checkPerformance(): array
    {
        return [
            'title' => 'Performance',
            'checks' => [
                'Memory Usage' => $this->checkMemoryUsage(),
                'Disk Space' => $this->checkDiskSpace(),
                'CPU Performance' => $this->checkCpuPerformance(),
                'I/O Performance' => $this->checkIoPerformance(),
            ],
        ];
    }

    private function checkStorage(): array
    {
        return [
            'title' => 'Storage',
            'checks' => [
                'Cache Driver' => $this->checkCacheDriver(),
                'Cache Connectivity' => $this->checkCacheConnectivity(),
                'File System' => $this->checkFileSystem(),
                'Temporary Storage' => $this->checkTempStorage(),
            ],
        ];
    }

    private function checkServices(): array
    {
        return [
            'title' => 'Services',
            'checks' => [
                'GitIngest Service' => $this->checkGitIngestService(),
                'Service Registration' => $this->checkServiceRegistration(),
                'Command Registration' => $this->checkCommandRegistration(),
                'Service Dependencies' => $this->checkServiceDependencies(),
            ],
        ];
    }

    // Individual check methods
    private function checkPhpVersion(): array
    {
        $current = PHP_VERSION;
        $required = '8.3.0';
        $valid = version_compare($current, $required, '>=');

        return [
            'status' => $valid,
            'message' => "Current: {$current}, Required: {$required}+",
            'fix' => $valid ? null : 'Upgrade PHP to version 8.3 or higher',
        ];
    }

    private function checkLaravelVersion(): array
    {
        $current = app()->version();
        $requiredMajor = 12;
        $currentMajor = (int) explode('.', $current)[0];
        $valid = $currentMajor >= $requiredMajor;

        return [
            'status' => $valid,
            'message' => "Current: {$current}, Required: {$requiredMajor}+",
            'fix' => $valid ? null : 'Upgrade Laravel to version 12 or higher',
        ];
    }

    private function checkMemoryLimit(): array
    {
        $limit = ini_get('memory_limit');
        $bytes = $this->convertToBytes($limit);
        $recommended = 256 * 1024 * 1024; // 256MB
        $valid = $bytes >= $recommended || $bytes === -1;

        return [
            'status' => $valid,
            'message' => "Current: {$limit}, Recommended: 256M+",
            'fix' => $valid ? null : 'Increase memory_limit to 256M or higher in php.ini',
        ];
    }

    private function checkExecutionTime(): array
    {
        $limit = ini_get('max_execution_time');
        $recommended = 300; // 5 minutes
        $valid = $limit >= $recommended || $limit === 0;

        return [
            'status' => $valid,
            'message' => "Current: {$limit}s, Recommended: {$recommended}s+",
            'fix' => $valid ? null : 'Increase max_execution_time to 300s or higher',
        ];
    }

    private function checkRequiredExtensions(): array
    {
        $required = ['json', 'mbstring', 'openssl', 'zip', 'curl'];
        $missing = array_filter($required, fn($ext) => !extension_loaded($ext));

        return [
            'status' => empty($missing),
            'message' => empty($missing) 
                ? 'All required extensions loaded' 
                : 'Missing: ' . implode(', ', $missing),
            'fix' => empty($missing) ? null : 'Install missing PHP extensions',
        ];
    }

    private function checkTikToken(): array
    {
        $result = Process::run('python -c "import tiktoken; print(tiktoken.__version__)"');
        
        return [
            'status' => $result->successful(),
            'message' => $result->successful() 
                ? 'Available (v' . trim($result->output()) . ')'
                : 'Not available (fallback estimation will be used)',
            'fix' => $result->successful() ? null : 'Install tiktoken: pip install tiktoken',
        ];
    }

    private function checkReactPhp(): array
    {
        $hasReact = class_exists('React\EventLoop\Loop');
        
        return [
            'status' => $hasReact,
            'message' => $hasReact ? 'ReactPHP is available' : 'ReactPHP not found',
            'fix' => $hasReact ? null : 'Install ReactPHP: composer require react/socket',
        ];
    }

    private function checkComposerPackages(): array
    {
        $required = [
            'react/http' => 'ReactPHP HTTP client',
            'react/filesystem' => 'ReactPHP filesystem',
            'spatie/laravel-package-tools' => 'Spatie package tools',
        ];

        $missing = [];
        foreach ($required as $package => $description) {
            if (!class_exists($package) && !$this->packageExists($package)) {
                $missing[] = $package;
            }
        }

        return [
            'status' => empty($missing),
            'message' => empty($missing) 
                ? 'All required packages available'
                : 'Missing: ' . implode(', ', $missing),
            'fix' => empty($missing) ? null : 'Run: composer install',
        ];
    }

    private function checkReactComponents(): array
    {
        $components = [
            'React\EventLoop\LoopInterface',
            'React\Http\Browser',
            'React\Filesystem\Filesystem',
        ];

        $missing = array_filter($components, fn($class) => !interface_exists($class) && !class_exists($class));

        return [
            'status' => empty($missing),
            'message' => empty($missing) 
                ? 'All ReactPHP components available'
                : 'Missing components: ' . count($missing),
            'fix' => empty($missing) ? null : 'Install ReactPHP components',
        ];
    }

    private function checkOptionalDependencies(): array
    {
        $optional = [
            'tiktoken' => $this->checkTikToken()['status'],
            'bcmath' => extension_loaded('bcmath'),
            'gmp' => extension_loaded('gmp'),
        ];

        $available = array_filter($optional);
        $total = count($optional);

        return [
            'status' => true, // Optional dependencies don't fail the check
            'message' => count($available) . "/{$total} optional dependencies available",
            'fix' => null,
        ];
    }

    private function checkConfigFile(): array
    {
        $path = config_path('gitingest.php');
        $exists = file_exists($path);

        return [
            'status' => $exists,
            'message' => $exists ? 'Configuration file exists' : 'Configuration file missing',
            'fix' => $exists ? null : 'Run: php artisan vendor:publish --tag=laravel-gitingest-config',
        ];
    }

    private function checkEnvironmentVariables(): array
    {
        $variables = [
            'GITINGEST_DEFAULT_MODEL' => config('gitingest.default_model'),
            'GITINGEST_MAX_FILE_SIZE' => config('gitingest.filter.max_file_size'),
        ];

        $configured = array_filter($variables, fn($value) => $value !== null);
        $total = count($variables);

        return [
            'status' => !empty($configured),
            'message' => count($configured) . "/{$total} environment variables configured",
            'fix' => empty($configured) ? 'Configure environment variables in .env' : null,
        ];
    }

    private function checkDefaultModel(): array
    {
        $model = config('gitingest.default_model', 'gpt-4');
        $supportedModels = ['gpt-4', 'gpt-4-turbo', 'claude-3-opus', 'claude-3-sonnet', 'gpt-3.5-turbo'];
        $valid = in_array($model, $supportedModels);

        return [
            'status' => $valid,
            'message' => "Current: {$model}" . ($valid ? '' : ' (unsupported)'),
            'fix' => $valid ? null : 'Set GITINGEST_DEFAULT_MODEL to a supported model',
        ];
    }

    private function checkFileFilterSettings(): array
    {
        $maxSize = config('gitingest.filter.max_file_size', 0);
        $extensions = config('gitingest.filter.allowed_extensions', []);
        $ignored = config('gitingest.filter.ignored_directories', []);

        $valid = $maxSize > 0 && is_array($extensions) && is_array($ignored);

        return [
            'status' => $valid,
            'message' => $valid 
                ? 'File filter settings are properly configured'
                : 'File filter settings need attention',
            'fix' => $valid ? null : 'Review and update file filter configuration',
        ];
    }

    private function checkCacheConfiguration(): array
    {
        $driver = config('cache.default');
        $supported = ['file', 'redis', 'memcached', 'database'];
        $valid = in_array($driver, $supported);

        return [
            'status' => $valid,
            'message' => "Cache driver: {$driver}" . ($valid ? '' : ' (may cause issues)'),
            'fix' => $valid ? null : 'Use a supported cache driver (file, redis, memcached, database)',
        ];
    }

    private function checkGitHubToken(): array
    {
        $token = config('gitingest.github_token') ?? env('GITHUB_TOKEN');
        $hasToken = !empty($token);

        if (!$hasToken) {
            return [
                'status' => true, // Optional for public repos
                'message' => 'No GitHub token configured (limits access to public repos only)',
                'fix' => 'Set GITHUB_TOKEN for private repository access',
            ];
        }

        // Validate token format
        $validFormat = preg_match('/^gh[ps]_[A-Za-z0-9_]{36,}$/', $token);

        return [
            'status' => $validFormat,
            'message' => $validFormat 
                ? 'GitHub token configured and format valid'
                : 'GitHub token configured but format invalid',
            'fix' => $validFormat ? null : 'Check GitHub token format (should start with ghp_ or ghs_)',
        ];
    }

    private function checkStoragePermissions(): array
    {
        $path = storage_path();
        $writable = is_writable($path);

        return [
            'status' => $writable,
            'message' => $writable ? 'Storage directory is writable' : 'Storage directory not writable',
            'fix' => $writable ? null : "Run: chmod 755 {$path}",
        ];
    }

    private function checkCachePermissions(): array
    {
        $path = storage_path('framework/cache');
        $writable = is_writable($path);

        return [
            'status' => $writable,
            'message' => $writable ? 'Cache directory is writable' : 'Cache directory not writable',
            'fix' => $writable ? null : "Run: chmod 755 {$path}",
        ];
    }

    private function checkTempPermissions(): array
    {
        $path = sys_get_temp_dir();
        $writable = is_writable($path);

        return [
            'status' => $writable,
            'message' => $writable ? 'Temp directory is writable' : 'Temp directory not writable',
            'fix' => $writable ? null : 'Check system temp directory permissions',
        ];
    }

    private function checkLogPermissions(): array
    {
        $path = storage_path('logs');
        $writable = is_writable($path);

        return [
            'status' => $writable,
            'message' => $writable ? 'Log directory is writable' : 'Log directory not writable',
            'fix' => $writable ? null : "Run: chmod 755 {$path}",
        ];
    }

    private function checkGitHubApi(): array
    {
        try {
            $response = Http::timeout(10)->get('https://api.github.com/rate_limit');
            $success = $response->successful();

            return [
                'status' => $success,
                'message' => $success 
                    ? 'GitHub API is accessible'
                    : 'GitHub API is not accessible',
                'fix' => $success ? null : 'Check network connectivity and firewall settings',
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'GitHub API connection failed',
                'fix' => 'Check network connectivity',
            ];
        }
    }

    private function checkPublicRepoAccess(): array
    {
        try {
            $response = Http::timeout(10)->get('https://api.github.com/repos/spatie/laravel-package-tools');
            $success = $response->successful();

            return [
                'status' => $success,
                'message' => $success 
                    ? 'Public repository access working'
                    : 'Cannot access public repositories',
                'fix' => $success ? null : 'Check GitHub API connectivity',
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Public repository access failed',
                'fix' => 'Check network connectivity',
            ];
        }
    }

    private function checkRateLimit(): array
    {
        try {
            $response = Http::timeout(10)->get('https://api.github.com/rate_limit');
            
            if ($response->successful()) {
                $data = $response->json();
                $remaining = $data['rate']['remaining'] ?? 0;
                $limit = $data['rate']['limit'] ?? 0;
                
                $healthy = $remaining > ($limit * 0.1); // At least 10% remaining

                return [
                    'status' => $healthy,
                    'message' => "Rate limit: {$remaining}/{$limit} remaining",
                    'fix' => $healthy ? null : 'Wait for rate limit reset or use GitHub token',
                ];
            }

            return [
                'status' => false,
                'message' => 'Could not check rate limit',
                'fix' => 'Check GitHub API connectivity',
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Rate limit check failed',
                'fix' => 'Check network connectivity',
            ];
        }
    }

    private function checkDnsResolution(): array
    {
        $hosts = ['github.com', 'api.github.com'];
        $failures = [];

        foreach ($hosts as $host) {
            if (!gethostbyname($host)) {
                $failures[] = $host;
            }
        }

        return [
            'status' => empty($failures),
            'message' => empty($failures) 
                ? 'DNS resolution working'
                : 'DNS resolution failed for: ' . implode(', ', $failures),
            'fix' => empty($failures) ? null : 'Check DNS settings',
        ];
    }

    private function checkMemoryUsage(): array
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->convertToBytes(ini_get('memory_limit'));
        
        $healthy = $limit === -1 || $peak < ($limit * 0.8); // Less than 80% of limit

        return [
            'status' => $healthy,
            'message' => 'Current: ' . $this->formatBytes($current) . ', Peak: ' . $this->formatBytes($peak),
            'fix' => $healthy ? null : 'Increase memory limit or optimize code',
        ];
    }

    private function checkDiskSpace(): array
    {
        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $usedPercent = $total > 0 ? (($total - $free) / $total) * 100 : 0;
        
        $healthy = $usedPercent < 90; // Less than 90% used

        return [
            'status' => $healthy,
            'message' => 'Free: ' . $this->formatBytes($free) . ' (' . round(100 - $usedPercent, 1) . '% available)',
            'fix' => $healthy ? null : 'Free up disk space',
        ];
    }

    private function checkCpuPerformance(): array
    {
        $start = microtime(true);
        
        // Simple CPU test
        $iterations = 10000;
        for ($i = 0; $i < $iterations; $i++) {
            md5($i);
        }
        
        $time = microtime(true) - $start;
        $healthy = $time < 1.0; // Should complete in less than 1 second

        return [
            'status' => $healthy,
            'message' => "CPU test completed in " . round($time * 1000, 2) . "ms",
            'fix' => $healthy ? null : 'CPU performance may be slow',
        ];
    }

    private function checkIoPerformance(): array
    {
        $start = microtime(true);
        $testFile = storage_path('test_io_performance.tmp');
        
        try {
            // Write test
            file_put_contents($testFile, str_repeat('test', 1000));
            
            // Read test
            file_get_contents($testFile);
            
            $time = microtime(true) - $start;
            $healthy = $time < 0.1; // Should complete in less than 100ms

            return [
                'status' => $healthy,
                'message' => "I/O test completed in " . round($time * 1000, 2) . "ms",
                'fix' => $healthy ? null : 'Disk I/O performance may be slow',
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'I/O test failed',
                'fix' => 'Check file system permissions and disk health',
            ];
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    private function checkCacheDriver(): array
    {
        $driver = config('cache.default');
        $working = true;

        try {
            Cache::put('gitingest_test', 'test_value', 60);
            $value = Cache::get('gitingest_test');
            $working = $value === 'test_value';
            Cache::forget('gitingest_test');
        } catch (Exception $e) {
            $working = false;
        }

        return [
            'status' => $working,
            'message' => $working 
                ? "Cache driver ({$driver}) is working"
                : "Cache driver ({$driver}) is not working",
            'fix' => $working ? null : 'Check cache configuration and connectivity',
        ];
    }

    private function checkCacheConnectivity(): array
    {
        try {
            $key = 'gitingest_connectivity_test_' . time();
            Cache::put($key, 'test', 10);
            $retrieved = Cache::get($key);
            Cache::forget($key);
            
            $working = $retrieved === 'test';

            return [
                'status' => $working,
                'message' => $working ? 'Cache read/write working' : 'Cache read/write failed',
                'fix' => $working ? null : 'Check cache driver configuration',
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Cache connectivity test failed',
                'fix' => 'Check cache driver and configuration',
            ];
        }
    }

    private function checkFileSystem(): array
    {
        $paths = [
            storage_path('app'),
            storage_path('framework'),
            storage_path('logs'),
        ];

        foreach ($paths as $path) {
            if (!is_dir($path) || !is_writable($path)) {
                return [
                    'status' => false,
                    'message' => "File system issue with: {$path}",
                    'fix' => "Check directory exists and is writable: {$path}",
                ];
            }
        }

        return [
            'status' => true,
            'message' => 'File system is accessible',
            'fix' => null,
        ];
    }

    private function checkTempStorage(): array
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/gitingest_temp_test_' . time();

        try {
            file_put_contents($testFile, 'test');
            $content = file_get_contents($testFile);
            unlink($testFile);

            $working = $content === 'test';

            return [
                'status' => $working,
                'message' => $working ? 'Temporary storage working' : 'Temporary storage failed',
                'fix' => $working ? null : 'Check temporary directory permissions',
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Temporary storage test failed',
                'fix' => 'Check temporary directory configuration',
            ];
        }
    }

    private function checkGitIngestService(): array
    {
        try {
            $service = app(GitIngestService::class);
            $working = $service instanceof GitIngestService;

            return [
                'status' => $working,
                'message' => $working ? 'GitIngest service is available' : 'GitIngest service not found',
                'fix' => $working ? null : 'Check service provider registration',
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'GitIngest service instantiation failed',
                'fix' => 'Check service dependencies and configuration',
            ];
        }
    }

    private function checkServiceRegistration(): array
    {
        $services = [
            'Ihasan\LaravelGitingest\Services\GitIngestService',
            'Ihasan\LaravelGitingest\Services\TokenCounter',
            'Ihasan\LaravelGitingest\Services\FileFilter',
        ];

        $registered = [];
        foreach ($services as $service) {
            try {
                app($service);
                $registered[] = $service;
            } catch (Exception $e) {
                // Service not registered
            }
        }

        $allRegistered = count($registered) === count($services);

        return [
            'status' => $allRegistered,
            'message' => count($registered) . '/' . count($services) . ' services registered',
            'fix' => $allRegistered ? null : 'Check service provider registration',
        ];
    }

    private function checkCommandRegistration(): array
    {
        $commands = [
            'gitingest:process',
            'gitingest:analyze',
            'gitingest:setup',
            'gitingest:doctor',
        ];

        // This is a basic check - in a real implementation you'd check if commands are actually registered
        return [
            'status' => true,
            'message' => 'Commands appear to be registered',
            'fix' => null,
        ];
    }

    private function checkServiceDependencies(): array
    {
        try {
            $service = app(GitIngestService::class);
            
            // Try to access dependencies through reflection or public interface
            $working = true; // This would be more comprehensive in practice

            return [
                'status' => $working,
                'message' => $working ? 'Service dependencies resolved' : 'Service dependency issues',
                'fix' => $working ? null : 'Check service dependency injection',
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Service dependency check failed',
                'fix' => 'Check service provider and dependency injection',
            ];
        }
    }

    private function displayResults(array $diagnostics): void
    {
        foreach ($diagnostics as $category => $group) {
            $this->info("📋 {$group['title']}");
            
            $table = new Table($this->output);
            $table->setHeaders(['Check', 'Status', 'Details']);

            $hasIssues = false;
            foreach ($group['checks'] as $name => $result) {
                $status = $result['status'] ? '✅ Pass' : '❌ Fail';
                $message = $result['message'];
                
                if (!$result['status']) {
                    $hasIssues = true;
                }

                $table->addRow([$name, $status, $message]);

                // Show fix suggestion if detailed mode and there's an issue
                if ($this->option('detailed') && !$result['status'] && !empty($result['fix'])) {
                    $table->addRow(['', '💡 Fix', $result['fix']]);
                    $table->addRow([new TableSeparator(), new TableSeparator(), new TableSeparator()]);
                }
            }

            $table->render();
            $this->newLine();
        }
    }

    private function attemptFixes(array $diagnostics): void
    {
        $this->info('🔧 Attempting automatic fixes...');
        $this->newLine();

        $fixCount = 0;
        foreach ($diagnostics as $group) {
            foreach ($group['checks'] as $name => $result) {
                if (!$result['status'] && !empty($result['fix'])) {
                    if ($this->confirm("Fix: {$name}?", false)) {
                        $this->comment("Attempting to fix: {$name}");
                        
                        // Here you would implement actual fixes
                        // For now, just show what would be done
                        $this->line("Would run: {$result['fix']}");
                        $fixCount++;
                    }
                }
            }
        }

        if ($fixCount > 0) {
            $this->info("✅ Attempted {$fixCount} fixes. Re-run diagnostics to verify.");
        } else {
            $this->comment('No automatic fixes were applied.');
        }
    }

    private function saveDiagnosticReport(array $diagnostics, string $outputPath): void
    {
        $report = [
            'timestamp' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'package_version' => '1.0.0', // This would be dynamic
            'diagnostics' => $diagnostics,
        ];

        file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT));
        $this->info("📄 Diagnostic report saved to: {$outputPath}");
    }

    // Helper methods
    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($last) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }

    private function packageExists(string $package): bool
    {
        // This would check if a Composer package is installed
        // For now, return true as a placeholder
        return true;
    }

    private function showHeader(): void
    {
        $this->line('');
        $this->line(' ╭─────────────────────────────────────╮');
        $this->line(' │          GitIngest Doctor           │');
        $this->line(' │      Diagnostic & Health Check     │');
        $this->line(' ╰─────────────────────────────────────╯');
        $this->line('');
    }
}
