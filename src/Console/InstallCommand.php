<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Console;

use Illuminate\Console\Command;

/**
 * Install command.
 */
class InstallCommand extends Command
{
    protected $signature = 'payzephyr:install {--force : Overwrite existing files}';

    protected $description = 'Install PayZephyr package';

    /**
     * Execute command.
     */
    public function handle(): int
    {
        $this->info('Installing PayZephyr...');

        $this->call('vendor:publish', [
            '--tag' => 'payments-config',
            '--force' => $this->option('force'),
        ]);

        $this->info('✓ Configuration file published');

        $this->call('vendor:publish', [
            '--tag' => 'payments-migrations',
        ]);

        $this->info('✓ Migration files published');

        if ($this->option('no-interaction')) {
        } elseif ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
            $this->info('✓ Migrations completed');
        }

        $this->newLine();
        $this->info('PayZephyr installed successfully!');
        $this->comment('Please configure your providers in .env');
        $this->newLine();
        $this->line('Example environment variables:');
        $this->line('  PAYMENTS_DEFAULT_PROVIDER=paystack');
        $this->line('  PAYSTACK_SECRET_KEY=your_secret_key');
        $this->line('  PAYSTACK_PUBLIC_KEY=your_public_key');

        return self::SUCCESS;
    }
}
