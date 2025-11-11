<?php

namespace App\Console\Commands\Rom;

use Illuminate\Console\Command;

class ImportAllCommand extends Command
{
    protected $signature = 'rom:import-all
                            {--systems-only : Only import systems without games}
                            {--skip-metadata : Skip metadata import}';

    protected $description = 'Import everything: systems, games, and metadata';

    public function handle()
    {
        $this->info('Starting full ROM database import...');
        $this->newLine();

        // Step 1: Import systems
        $this->info('Step 1: Importing systems...');
        $this->call('rom:import-systems');
        $this->newLine();

        if ($this->option('systems-only')) {
            $this->info('Systems-only flag set. Skipping games and metadata.');
            return Command::SUCCESS;
        }

        // Step 2: Import games
        $this->info('Step 2: Importing games for all systems...');
        $this->call('rom:import-games', ['--all' => true]);
        $this->newLine();

        if ($this->option('skip-metadata')) {
            $this->info('Skip-metadata flag set. Skipping metadata import.');
            return Command::SUCCESS;
        }

        // Step 3: Import metadata
        $this->info('Step 3: Importing metadata for all systems...');
        $this->call('rom:import-metadata', [
            'type' => 'all',
            '--all-systems' => true
        ]);
        $this->newLine();

        $this->info('Full import completed successfully!');

        return Command::SUCCESS;
    }
}
