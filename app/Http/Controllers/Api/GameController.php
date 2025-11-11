<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /**
     * Search for games by hash or name
     * Automatically detects hash type (CRC, MD5, SHA1, Serial)
     */
    public function search(Request $request)
    {
        $search = $request->input('search');

        if (empty($search)) {
            return response()->json([
                'error' => 'Search parameter is required',
                'message' => 'Please provide a search value (CRC, MD5, SHA1, Serial, or game name)'
            ], 400);
        }

        // Detect hash type and search
        $hashType = $this->detectHashType($search);

        // Only allow valid hash types (CRC, MD5, SHA1, Serial)
        if ($hashType === 'invalid') {
            return response()->json([
                'error' => 'Invalid search format',
                'message' => 'Search must be a valid CRC (8 hex), MD5 (32 hex), SHA1 (40 hex), or Serial (XXXX-12345) format'
            ], 400);
        }

        $query = Game::with(['system', 'developers', 'publishers', 'genres']);

        // Normalize hash input (remove spaces/dashes, uppercase)
        $normalizedSearch = strtoupper(str_replace([' ', '-'], '', trim($search)));

        switch ($hashType) {
            case 'crc':
                $game = $query->byCrc($normalizedSearch)->first();
                break;

            case 'md5':
                $game = $query->byMd5($normalizedSearch)->first();
                break;

            case 'sha1':
                $game = $query->bySha1($normalizedSearch)->first();
                break;

            case 'serial':
                $game = $query->bySerial($normalizedSearch)->first();
                break;
        }

        if (!$game) {
            return response()->json([
                'found' => false,
                'detected_type' => $hashType,
                'search' => $search,
                'message' => 'No game found'
            ], 404);
        }

        return response()->json([
            'found' => true,
            'detected_type' => $hashType,
            'search' => $search,
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'description' => $game->description,
                'region' => $game->region,
                'release_year' => $game->release_year,
                'system' => [
                    'id' => $game->system->id,
                    'name' => $game->system->name,
                    'slug' => $game->system->slug,
                ],
                'hashes' => [
                    'crc' => $game->crc,
                    'md5' => $game->md5,
                    'sha1' => $game->sha1,
                    'serial' => $game->serial,
                ],
                'file' => [
                    'filename' => $game->filename,
                    'size' => $game->size,
                ],
                'developers' => $game->developers->map(fn($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'slug' => $d->slug,
                ]),
                'publishers' => $game->publishers->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                ]),
                'genres' => $game->genres->map(fn($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'slug' => $g->slug,
                ]),
            ]
        ]);
    }

    /**
     * Detect the hash type based on the input string
     */
    protected function detectHashType(string $input): string
    {
        $input = trim($input);

        // Remove any spaces or dashes
        $cleaned = str_replace([' ', '-'], '', $input);

        // CRC32: 8 hex characters
        if (preg_match('/^[A-Fa-f0-9]{8}$/', $cleaned)) {
            return 'crc';
        }

        // MD5: 32 hex characters
        if (preg_match('/^[A-Fa-f0-9]{32}$/', $cleaned)) {
            return 'md5';
        }

        // SHA1: 40 hex characters
        if (preg_match('/^[A-Fa-f0-9]{40}$/', $cleaned)) {
            return 'sha1';
        }

        // Serial number pattern (e.g., SLPS-01204, SCUS-94163)
        if (preg_match('/^[A-Z]{4}[-_]?\d{5}$/i', $cleaned)) {
            return 'serial';
        }

        // Invalid format
        return 'invalid';
    }

    /**
     * Get a single game by ID
     */
    public function show($id)
    {
        $game = Game::with(['system', 'developers', 'publishers', 'genres'])
            ->find($id);

        if (!$game) {
            return response()->json([
                'error' => 'Game not found',
                'message' => "No game found with ID: {$id}"
            ], 404);
        }

        return response()->json([
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'description' => $game->description,
                'region' => $game->region,
                'release_year' => $game->release_year,
                'system' => [
                    'id' => $game->system->id,
                    'name' => $game->system->name,
                    'slug' => $game->system->slug,
                ],
                'hashes' => [
                    'crc' => $game->crc,
                    'md5' => $game->md5,
                    'sha1' => $game->sha1,
                    'serial' => $game->serial,
                ],
                'file' => [
                    'filename' => $game->filename,
                    'size' => $game->size,
                ],
                'developers' => $game->developers->map(fn($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'slug' => $d->slug,
                ]),
                'publishers' => $game->publishers->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                ]),
                'genres' => $game->genres->map(fn($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'slug' => $g->slug,
                ]),
            ]
        ]);
    }
}
