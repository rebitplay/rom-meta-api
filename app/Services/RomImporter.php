<?php

namespace App\Services;

use App\Models\Developer;
use App\Models\Game;
use App\Models\Genre;
use App\Models\Publisher;
use App\Models\System;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RomImporter
{
    protected DatParser $parser;
    protected string $libretroDbPath;

    public function __construct(DatParser $parser)
    {
        $this->parser = $parser;
        $this->libretroDbPath = config('rom.libretro_db_path', '/Users/daudau/Code/rebitplay/libretro-database');
    }

    /**
     * Import or update a system from DAT file
     */
    public function importSystem(string $datFilePath): System
    {
        $data = $this->parser->parse($datFilePath);
        $header = $data['header'];

        // If no name in header, use filename
        $name = $header['name'] ?? basename($datFilePath, '.dat');

        $system = System::updateOrCreate(
            ['name' => $name],
            ['name' => $name]
        );

        Log::info("Imported system: {$system->name}");

        return $system;
    }

    /**
     * Import games for a system with precedence handling
     */
    public function importGames(System $system, array $sources = ['dat', 'no-intro', 'redump', 'tosec']): int
    {
        $datFiles = $this->parser->getSystemDatFiles($system->name, $this->libretroDbPath);
        $imported = 0;

        // Process in order of precedence
        foreach ($sources as $source) {
            if (!isset($datFiles[$source])) {
                continue;
            }

            $filePath = $datFiles[$source];
            Log::info("Processing {$source} for {$system->name}: {$filePath}");

            $data = $this->parser->parse($filePath);

            foreach ($data['games'] as $gameData) {
                if ($this->importGame($system, $gameData, $source)) {
                    $imported++;
                }
            }
        }

        Log::info("Imported {$imported} games for {$system->name}");

        return $imported;
    }

    /**
     * Import a single game
     */
    protected function importGame(System $system, array $gameData, string $source): bool
    {
        // We need at least a name and a CRC or serial
        if (empty($gameData['name']) && empty($gameData['comment'])) {
            return false;
        }

        if (empty($gameData['crc']) && empty($gameData['serial'])) {
            return false;
        }

        $name = $gameData['name'] ?? $gameData['comment'] ?? 'Unknown';
        $description = $gameData['description'] ?? null;

        // Try to find existing game by CRC or serial
        $query = Game::where('system_id', $system->id);

        if (!empty($gameData['crc'])) {
            $query->where('crc', $gameData['crc']);
        } elseif (!empty($gameData['serial'])) {
            $query->where('serial', $gameData['serial']);
        }

        $existingGame = $query->first();

        $attributes = [
            'system_id' => $system->id,
            'name' => $name,
            'description' => $description,
            'region' => $gameData['region'] ?? null,
            'release_year' => !empty($gameData['releaseyear']) ? (int) $gameData['releaseyear'] : null,
            'crc' => $gameData['crc'] ?? null,
            'md5' => $gameData['md5'] ?? null,
            'sha1' => $gameData['sha1'] ?? null,
            'serial' => $gameData['serial'] ?? null,
            'size' => $gameData['size'] ?? null,
            'filename' => $gameData['filename'] ?? null,
        ];

        if ($existingGame) {
            // Update only if new data is not null (preserve existing data)
            foreach ($attributes as $key => $value) {
                if ($value !== null) {
                    $existingGame->$key = $value;
                }
            }
            $existingGame->save();
        } else {
            Game::create($attributes);
        }

        return true;
    }

    /**
     * Import metadata (developer, publisher, genre, release year)
     */
    public function importMetadata(System $system, string $type): int
    {
        $metadataFiles = $this->parser->getMetadataDatFiles($system->name, $this->libretroDbPath);

        if (!isset($metadataFiles[$type])) {
            Log::warning("No {$type} metadata file found for {$system->name}");
            return 0;
        }

        $filePath = $metadataFiles[$type];
        $data = $this->parser->parse($filePath);
        $updated = 0;

        foreach ($data['games'] as $gameData) {
            if (empty($gameData['crc'])) {
                continue;
            }

            $game = Game::where('system_id', $system->id)
                ->where('crc', $gameData['crc'])
                ->first();

            if (!$game) {
                continue;
            }

            switch ($type) {
                case 'developer':
                    if (!empty($gameData['developer'])) {
                        $developer = $this->getOrCreateDeveloper($gameData['developer']);
                        $game->developers()->syncWithoutDetaching([$developer->id]);
                        $updated++;
                    }
                    break;

                case 'publisher':
                    if (!empty($gameData['publisher'])) {
                        $publisher = $this->getOrCreatePublisher($gameData['publisher']);
                        $game->publishers()->syncWithoutDetaching([$publisher->id]);
                        $updated++;
                    }
                    break;

                case 'genre':
                    if (!empty($gameData['genre'])) {
                        $genre = $this->getOrCreateGenre($gameData['genre']);
                        $game->genres()->syncWithoutDetaching([$genre->id]);
                        $updated++;
                    }
                    break;

                case 'releaseyear':
                    if (!empty($gameData['releaseyear'])) {
                        $game->release_year = (int) $gameData['releaseyear'];
                        $game->save();
                        $updated++;
                    }
                    break;
            }
        }

        Log::info("Updated {$updated} games with {$type} metadata for {$system->name}");

        return $updated;
    }

    /**
     * Import all metadata types for a system
     */
    public function importAllMetadata(System $system): array
    {
        $results = [];
        $types = ['developer', 'publisher', 'genre', 'releaseyear'];

        foreach ($types as $type) {
            $results[$type] = $this->importMetadata($system, $type);
        }

        return $results;
    }

    /**
     * Get or create developer
     */
    protected function getOrCreateDeveloper(string $name): Developer
    {
        return Developer::firstOrCreate(
            ['name' => $name],
            ['name' => $name]
        );
    }

    /**
     * Get or create publisher
     */
    protected function getOrCreatePublisher(string $name): Publisher
    {
        return Publisher::firstOrCreate(
            ['name' => $name],
            ['name' => $name]
        );
    }

    /**
     * Get or create genre
     */
    protected function getOrCreateGenre(string $name): Genre
    {
        return Genre::firstOrCreate(
            ['name' => $name],
            ['name' => $name]
        );
    }

    /**
     * Get all available systems from libretro-database
     */
    public function getAvailableSystems(): array
    {
        $systems = [];
        $datPath = $this->libretroDbPath . '/dat';
        $noIntroPath = $this->libretroDbPath . '/metadat/no-intro';

        // Get from /dat
        if (is_dir($datPath)) {
            foreach (glob($datPath . '/*.dat') as $file) {
                $name = basename($file, '.dat');
                $systems[$name] = $file;
            }
        }

        // Get from /metadat/no-intro
        if (is_dir($noIntroPath)) {
            foreach (glob($noIntroPath . '/*.dat') as $file) {
                $name = basename($file, '.dat');
                if (!isset($systems[$name])) {
                    $systems[$name] = $file;
                }
            }
        }

        return $systems;
    }
}
