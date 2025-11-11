<?php

namespace App\Services;

use App\Models\Developer;
use App\Models\Game;
use App\Models\Genre;
use App\Models\Publisher;
use App\Models\System;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $header = $this->parser->parseHeaderOnly($datFilePath);

        // If no name in header, use filename
        $name = $header['name'] ?? basename($datFilePath, '.dat');

        $system = System::updateOrCreate(
            ['name' => $name],
            ['name' => $name, 'slug' => Str::slug($name)]
        );

        return $system;
    }

    /**
     * Batch import systems (much faster)
     */
    public function importSystemsBatch(array $availableSystems, ?callable $progressCallback = null): int
    {
        $systemsToImport = [];
        $total = count($availableSystems);
        $processed = 0;

        foreach ($availableSystems as $systemName => $datFile) {
            // Skip Mobile - J2ME systems
            if ($systemName === 'Mobile - J2ME') {
                $processed++;
                if ($progressCallback) {
                    $progressCallback($processed, $total);
                }
                continue;
            }

            try {
                $header = $this->parser->parseHeaderOnly($datFile);
                $name = $header['name'] ?? basename($datFile, '.dat');

                $systemsToImport[] = [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } catch (\Exception $e) {
                Log::error("Failed to parse {$systemName}: " . $e->getMessage());
            }

            $processed++;
            if ($progressCallback) {
                $progressCallback($processed, $total);
            }
        }

        // Batch upsert all systems at once
        if (!empty($systemsToImport)) {
            System::upsert(
                $systemsToImport,
                ['name'], // unique key
                ['slug', 'updated_at'] // columns to update
            );
        }

        Log::info("Imported " . count($systemsToImport) . " systems in batch");

        return count($systemsToImport);
    }

    /**
     * Import games for a system with precedence handling
     */
    public function importGames(System $system, array $sources = ['dat', 'no-intro', 'redump', 'tosec']): int
    {
        $datFiles = $this->parser->getSystemDatFiles($system->name, $this->libretroDbPath);
        $gamesToUpsert = [];

        // Process in order of precedence
        foreach ($sources as $source) {
            if (!isset($datFiles[$source])) {
                continue;
            }

            $filePath = $datFiles[$source];
            Log::info("Processing {$source} for {$system->name}: {$filePath}");

            $data = $this->parser->parse($filePath);

            foreach ($data['games'] as $gameData) {
                $processedGame = $this->processGameData($system, $gameData);
                if ($processedGame) {
                    // Use CRC as key to deduplicate
                    $key = $processedGame['crc'] ?? md5($processedGame['name'] . $processedGame['serial']);
                    $gamesToUpsert[$key] = $processedGame;
                }
            }
        }

        // Batch insert/update all games
        $imported = 0;
        if (!empty($gamesToUpsert)) {
            // Process in chunks to avoid memory/query size limits (smaller chunks for SQL limit)
            $chunks = array_chunk(array_values($gamesToUpsert), 100);

            DB::transaction(function () use ($chunks, &$imported) {
                foreach ($chunks as $chunk) {
                    // Prepare data for upsert
                    $upsertData = [];
                    foreach ($chunk as $game) {
                        if (!empty($game['crc'])) {
                            $upsertData[] = $game;
                        }
                    }

                    if (!empty($upsertData)) {
                        Game::upsert(
                            $upsertData,
                            ['system_id', 'crc'], // unique composite key
                            ['name', 'description', 'region', 'release_year', 'md5', 'sha1', 'serial', 'size', 'filename', 'updated_at']
                        );
                        $imported += count($upsertData);
                    }
                }
            });
        }

        Log::info("Imported {$imported} games for {$system->name}");

        return $imported;
    }

    /**
     * Process game data without DB queries
     */
    protected function processGameData(System $system, array $gameData): ?array
    {
        // We need at least a name and a CRC or serial
        if (empty($gameData['name']) && empty($gameData['comment'])) {
            return null;
        }

        if (empty($gameData['crc']) && empty($gameData['serial'])) {
            return null;
        }

        $name = $gameData['name'] ?? $gameData['comment'] ?? 'Unknown';

        return [
            'system_id' => $system->id,
            'name' => $name,
            'description' => $gameData['description'] ?? null,
            'region' => $gameData['region'] ?? null,
            'release_year' => !empty($gameData['releaseyear']) ? (int) $gameData['releaseyear'] : null,
            'crc' => $gameData['crc'] ?? null,
            'md5' => $gameData['md5'] ?? null,
            'sha1' => $gameData['sha1'] ?? null,
            'serial' => $gameData['serial'] ?? null,
            'size' => $gameData['size'] ?? null,
            'filename' => $gameData['filename'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
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
