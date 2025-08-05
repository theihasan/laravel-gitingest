<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;

final class SetupCommand extends Command
{
    protected $signature = 'gitingest:setup 
                           {--publish-config : Publish configuration file}
                           {--check-dependencies : Check system dependencies}
                           {--setup-env : Setup environment variables}
                           {--test-installation : Test installation with sample repository}
                           {--all : Run all setup steps}';

    protected $description = 'Setup and configure Laravel GitIngest package';

    public function handle(): int
    {
        $this->showHeader();

        if ($this->option('all')) {
            return $this->runAllSetupSteps();
        }

        if ($this->option('publish-config')) {
            $this->publishConfiguration();
        }

        if ($this->option('check-dependencies')) {
            $this->checkDependencies();
        }

        if ($this->option('setup-env')) {
            $this->setupEnvironment();
        }

        if ($this->option('test-installation')) {
            $this->testInstallation();
        }

        // If no specific options, run interactive setup
        if (!$this->hasAnyOption()) {
            return $this->runInteractiveSetup();
        }

        $this->info('✅ Setup completed successfully!');
        return self::SUCCESS;
    }

    private function runAllSetupSteps(): int
    {
        $this->info('🚀 Running complete GitIngest setup...');
        $this->newLine();

        $steps = [
            'Publishing configuration' => fn() => $this->publishConfiguration(),
            'Checking dependencies' => fn() => $this->checkDependencies(),
            'Setting up environment' => fn() => $this->setupEnvironment(),
            'Testing installation' => fn() => $this->testInstallation(),
        ];

        foreach ($steps as $description => $callback) {
            $this->info("📋 {$description}...");
            $success = $callback();
            
            if (!$success) {
                $this->error("❌ Failed: {$description}");
                return self::FAILURE;
            }
            
            $this->comment("✅ Completed: {$description}");
            $this->newLine();
        }

        $this->showCompletionMessage();
        return self::SUCCESS;
    }

    private function runInteractiveSetup(): int
    {
        $this->info('🎯 Interactive GitIngest Setup');
        $this->newLine();

        // Step 1: Configuration
        if ($this->confirm('Publish configuration file?', true)) {
            $this->publishConfiguration();
        }

        // Step 2: Dependencies
        if ($this->confirm('Check system dependencies?', true)) {
            $this->checkDependencies();
        }

        // Step 3: Environment
        if ($this->confirm('Setup environment variables?', false)) {
            $this->setupEnvironment();
        }

        // Step 4: Testing
        if ($this->confirm('Test installation with a sample repository?', true)) {
            $this->testInstallation();
        }

        $this->showCompletionMessage();
        return self::SUCCESS;
    }

    private function publishConfiguration(): bool
    {
        try {
            if (File::exists(config_path('gitingest.php'))) {
                if (!$this->confirm('Configuration file already exists. Overwrite?', false)) {
                    $this->comment('Skipping configuration publishing.');
                    return true;
                }
            }

            Artisan::call('vendor:publish', [
                '--tag' => 'laravel-gitingest-config',
                '--force' => true,
            ]);

            $this->info('✅ Configuration file published to config/gitingest.php');
            return true;

        } catch (Exception $e) {
            $this->error("❌ Failed to publish configuration: {$e->getMessage()}");
            return false;
        }
    }

    private function checkDependencies(): bool
    {
        $this->info('🔍 Checking system dependencies...');
        $this->newLine();

        $checks = [
            'PHP Version' => $this->checkPhpVersion(),
            'Laravel Version' => $this->checkLaravelVersion(),
            'Required Extensions' => $this->checkPhpExtensions(),
            'ReactPHP' => $this->checkReactPhp(),
            'TikToken (Optional)' => $this->checkTikToken(),
            'Memory Limit' => $this->checkMemoryLimit(),
            'File Permissions' => $this->checkFilePermissions(),
        ];

        $table = new Table($this->output);
        $table->setHeaders(['Dependency', 'Status', 'Details']);

        $allPassed = true;
        foreach ($checks as $name => $result) {
            $status = $result['status'] ? '✅ Pass' : '❌ Fail';
            $table->addRow([$name, $status, $result['message']]);
            
            if (!$result['status']) {
                $allPassed = false;
            }
        }

        $table->render();
        $this->newLine();

        if (!$allPassed) {
            $this->warn('⚠️  Some dependency checks failed. Please address the issues above.');
            return false;
        }

        $this->info('✅ All dependency checks passed!');
        return true;
    }

    private function setupEnvironment(): bool
    {
        $this->info('🔧 Setting up environment variables...');
        $this->newLine();

        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        // Read current .env file
        $envContent = File::exists($envPath) ? File::get($envPath) : '';

        $variables = [
            'GITINGEST_DEFAULT_MODEL' => [
                'description' => 'Default AI model for optimization',
                'default' => 'gpt-4',
                'options' => ['gpt-4', 'gpt-4-turbo', 'claude-3-opus', 'claude-3-sonnet', 'gpt-3.5-turbo'],
            ],
            'GITINGEST_MAX_FILE_SIZE' => [
                'description' => 'Maximum file size in bytes',
                'default' => '1048576',
                'validation' => fn($value) => is_numeric($value) && $value > 0,
            ],
            'GITINGEST_OPTIMIZATION_LEVEL' => [
                'description' => 'Default optimization level (0-3)',
                'default' => '1',
                'options' => ['0', '1', '2', '3'],
            ],
            'GITHUB_TOKEN' => [
                'description' => 'GitHub Personal Access Token (for private repos)',
                'default' => '',
                'secret' => true,
                'optional' => true,
            ],
        ];

        $updates = [];
        foreach ($variables as $key => $config) {
            // Skip if already exists and user doesn't want to update
            if (str_contains($envContent, $key) && !$this->confirm("Update existing {$key}?", false)) {
                continue;
            }

            $description = $config['description'];
            $default = $config['default'];
            $secret = $config['secret'] ?? false;
            $optional = $config['optional'] ?? false;

            if ($optional && !$this->confirm("Set {$description}?", false)) {
                continue;
            }

            if (isset($config['options'])) {
                $value = $this->choice($description, $config['options'], $default);
            } else {
                $value = $this->ask($description, $default);
                
                if (isset($config['validation']) && !$config['validation']($value)) {
                    $this->error("Invalid value for {$key}");
                    continue;
                }
            }

            if ($secret && $value) {
                $displayValue = str_repeat('*', min(strlen($value), 8));
                $this->line("Set {$key}={$displayValue}");
            }

            $updates[$key] = $value;
        }

        // Update .env file
        if (!empty($updates)) {
            $this->updateEnvFile($envPath, $updates);
            $this->info('✅ Environment variables updated!');
        } else {
            $this->comment('No environment variables were updated.');
        }

        return true;
    }

    private function testInstallation(): bool
    {
        $this->info('🧪 Testing installation with sample repository...');
        $this->newLine();

        $testRepo = 'https://github.com/spatie/laravel-package-tools';
        
        try {
            $this->comment("Testing with: {$testRepo}");
            
            // Use the analyze command for quick testing
            $exitCode = Artisan::call('gitingest:analyze', [
                'repositories' => [$testRepo],
                '--sample-size' => 10,
            ]);

            if ($exitCode === 0) {
                $this->info('✅ Installation test successful!');
                $this->comment('GitIngest is properly configured and ready to use.');
                return true;
            } else {
                $this->error('❌ Installation test failed.');
                $this->line('Check the error messages above for details.');
                return false;
            }

        } catch (Exception $e) {
            $this->error("❌ Installation test failed: {$e->getMessage()}");
            return false;
        }
    }

    private function checkPhpVersion(): array
    {
        $currentVersion = PHP_VERSION;
        $requiredVersion = '8.3.0';
        $isValid = version_compare($currentVersion, $requiredVersion, '>=');

        return [
            'status' => $isValid,
            'message' => $isValid 
                ? "Current: {$currentVersion} (Required: {$requiredVersion}+)"
                : "Current: {$currentVersion}, Required: {$requiredVersion}+",
        ];
    }

    private function checkLaravelVersion(): array
    {
        $currentVersion = app()->version();
        $requiredMajor = 12;
        $currentMajor = (int) explode('.', $currentVersion)[0];
        $isValid = $currentMajor >= $requiredMajor;

        return [
            'status' => $isValid,
            'message' => $isValid
                ? "Current: {$currentVersion} (Required: {$requiredMajor}+)"
                : "Current: {$currentVersion}, Required: {$requiredMajor}+",
        ];
    }

    private function checkPhpExtensions(): array
    {
        $required = ['json', 'mbstring', 'openssl', 'zip'];
        $missing = [];

        foreach ($required as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }

        $isValid = empty($missing);

        return [
            'status' => $isValid,
            'message' => $isValid
                ? 'All required extensions loaded'
                : 'Missing: ' . implode(', ', $missing),
        ];
    }

    private function checkReactPhp(): array
    {
        try {
            $hasReact = class_exists('React\EventLoop\Loop');
            return [
                'status' => $hasReact,
                'message' => $hasReact ? 'ReactPHP is available' : 'ReactPHP not found',
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'ReactPHP check failed',
            ];
        }
    }

    private function checkTikToken(): array
    {
        $result = Process::run('python -c "import tiktoken; print(tiktoken.__version__)"');
        
        if ($result->successful()) {
            return [
                'status' => true,
                'message' => 'tiktoken available (v' . trim($result->output()) . ')',
            ];
        }

        return [
            'status' => false,
            'message' => 'tiktoken not available (using fallback estimation)',
        ];
    }

    private function checkMemoryLimit(): array
    {
        $memoryLimit = ini_get('memory_limit');
        $bytes = $this->convertToBytes($memoryLimit);
        $recommendedBytes = 256 * 1024 * 1024; // 256MB
        
        $isValid = $bytes >= $recommendedBytes || $bytes === -1; // -1 means unlimited

        return [
            'status' => $isValid,
            'message' => $isValid
                ? "Current: {$memoryLimit} (Recommended: 256M+)"
                : "Current: {$memoryLimit}, Recommended: 256M+",
        ];
    }

    private function checkFilePermissions(): array
    {
        $paths = [
            storage_path(),
            storage_path('app'),
            storage_path('logs'),
        ];

        foreach ($paths as $path) {
            if (!is_writable($path)) {
                return [
                    'status' => false,
                    'message' => "Not writable: {$path}",
                ];
            }
        }

        return [
            'status' => true,
            'message' => 'Storage directories are writable',
        ];
    }

    private function updateEnvFile(string $envPath, array $updates): void
    {
        $envContent = File::exists($envPath) ? File::get($envPath) : '';

        foreach ($updates as $key => $value) {
            $pattern = "/^{$key}=.*$/m";
            $replacement = "{$key}={$value}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        File::put($envPath, $envContent);
    }

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

    private function hasAnyOption(): bool
    {
        return $this->option('publish-config') ||
               $this->option('check-dependencies') ||
               $this->option('setup-env') ||
               $this->option('test-installation');
    }

    private function showHeader(): void
    {
        $this->line('');
        $this->line(' ╭─────────────────────────────────────╮');
        $this->line(' │         GitIngest Setup             │');
        $this->line(' │    Package Installation Helper      │');
        $this->line(' ╰─────────────────────────────────────╯');
        $this->line('');
    }

    private function showCompletionMessage(): void
    {
        $this->newLine();
        $this->info('🎉 GitIngest setup completed successfully!');
        $this->newLine();
        
        $this->comment('Next steps:');
        $this->line('  • Run: php artisan gitingest:analyze <repository-url>');
        $this->line('  • Or: php artisan gitingest:process <repository-url>');
        $this->line('  • Check documentation: README.md');
        $this->newLine();
        
        $this->info('Happy coding! 🚀');
    }
}
