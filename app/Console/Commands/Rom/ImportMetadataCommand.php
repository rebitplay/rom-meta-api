<?php

namespace App\Console\Commands\Rom;

use App\Models\System;
use App\Services\RomImporter;
use Illuminate\Console\Command;

class ImportMetadataCommand extends Command
{
    protected $signature = 'rom:import-metadata
                            {type? : Metadata type (developer, publisher, genre, releaseyear, all)}
                            {system? : System name or slug}
                            {--all-systems : Import metadata for all systems}';

    protected $description = 'Import metadata (developer, publisher, genre, release year) for games';

    public function handle(RomImporter $importer)
    {
        $type = $this->argument('type') ?? 'all';
        $validTypes = ['developer', 'publisher', 'genre', 'releaseyear', 'all'];

        if (!in_array($type, $validTypes)) {
            $this->error("Invalid type. Must be one of: " . implode(', ', $validTypes));
            return Command::FAILURE;
        }

        if ($this->option('all-systems')) {
            $this->importForAllSystems($importer, $type);
        } else {
            $systemInput = $this->argument('system');

            if (!$systemInput) {
                $this->error('Please provide a system name or use --all-systems flag');
                return Command::FAILURE;
            }

            $this->importForSingleSystem($systemInput, $importer, $type);
        }

        return Command::SUCCESS;
    }

    protected function importForAllSystems(RomImporter $importer, string $type)
    {
        $systems = System::all();

        if ($systems->isEmpty()) {
            $this->error('No systems found. Run rom:import-systems first.');
            return;
        }

        $this->info("Importing {$type} metadata for {$systems->count()} systems...");

        foreach ($systems as $system) {
            $this->info("\nProcessing: {$system->name}");

            if ($type === 'all') {
                $results = $importer->importAllMetadata($system);
                foreach ($results as $metaType => $count) {
                    $this->info("  {$metaType}: {$count} games updated");
                }
            } else {
                $updated = $importer->importMetadata($system, $type);
                $this->info("  Updated {$updated} games");
            }
        }
    }

    protected function importForSingleSystem(string $systemInput, RomImporter $importer, string $type)
    {
        $system = System::where('name', $systemInput)
            ->orWhere('slug', $systemInput)
            ->first();

        if (!$system) {
            $this->error("System not found: {$systemInput}");
            return;
        }

        $this->info("Importing {$type} metadata for: {$system->name}");

        if ($type === 'all') {
            $results = $importer->importAllMetadata($system);
            foreach ($results as $metaType => $count) {
                $this->info("{$metaType}: {$count} games updated");
            }
        } else {
            $updated = $importer->importMetadata($system, $type);
            $this->info("Successfully updated {$updated} games");
        }
    }
}
