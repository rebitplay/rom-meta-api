<?php

namespace App\Console\Commands\Rom;

use App\Models\System;
use App\Services\RomImporter;
use Illuminate\Console\Command;

class ImportGamesCommand extends Command
{
    protected $signature = 'rom:import-games
                            {system? : System name or slug}
                            {--all : Import games for all systems}
                            {--source=* : Specific sources to import (dat, no-intro, redump, tosec)}';

    protected $description = 'Import games for a system or all systems';

    public function handle(RomImporter $importer)
    {
        $sources = $this->option('source') ?: config('rom.source_precedence');

        if ($this->option('all')) {
            $this->importAllSystems($importer, $sources);
        } else {
            $systemInput = $this->argument('system');

            if (!$systemInput) {
                $this->error('Please provide a system name or use --all flag');
                return Command::FAILURE;
            }

            $this->importSingleSystem($systemInput, $importer, $sources);
        }

        return Command::SUCCESS;
    }

    protected function importAllSystems(RomImporter $importer, array $sources)
    {
        $systems = System::all();

        if ($systems->isEmpty()) {
            $this->error('No systems found. Run rom:import-systems first.');
            return;
        }

        $this->info("Importing games for {$systems->count()} systems...");

        foreach ($systems as $system) {
            $this->info("\nProcessing: {$system->name}");
            $imported = $importer->importGames($system, $sources);
            $this->info("Imported {$imported} games");
        }
    }

    protected function importSingleSystem(string $systemInput, RomImporter $importer, array $sources)
    {
        $system = System::where('name', $systemInput)
            ->orWhere('slug', $systemInput)
            ->first();

        if (!$system) {
            $this->error("System not found: {$systemInput}");
            return;
        }

        $this->info("Importing games for: {$system->name}");

        $imported = $importer->importGames($system, $sources);

        $this->info("Successfully imported {$imported} games");
    }
}
