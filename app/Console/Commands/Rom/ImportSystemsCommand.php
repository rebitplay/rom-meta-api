<?php

namespace App\Console\Commands\Rom;

use App\Services\RomImporter;
use Illuminate\Console\Command;

class ImportSystemsCommand extends Command
{
    protected $signature = 'rom:import-systems';
    protected $description = 'Import all available systems from libretro-database';

    public function handle(RomImporter $importer)
    {
        $this->info('Discovering available systems...');

        $availableSystems = $importer->getAvailableSystems();

        $this->info('Found ' . count($availableSystems) . ' systems');

        $imported = $importer->importSystemsBatch($availableSystems, function ($processed, $total) use (&$bar) {
            if (!isset($bar)) {
                $bar = $this->output->createProgressBar($total);
                $bar->start();
            }
            $bar->advance();
        });

        if (isset($bar)) {
            $bar->finish();
        }
        $this->newLine(2);
        $this->info("Successfully imported {$imported} systems");

        return Command::SUCCESS;
    }
}
