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

        $bar = $this->output->createProgressBar(count($availableSystems));
        $bar->start();

        $imported = 0;

        foreach ($availableSystems as $systemName => $datFile) {
            try {
                $importer->importSystem($datFile);
                $imported++;
            } catch (\Exception $e) {
                $this->error("\nFailed to import {$systemName}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Successfully imported {$imported} systems");

        return Command::SUCCESS;
    }
}
