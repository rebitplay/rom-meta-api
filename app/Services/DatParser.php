<?php

namespace App\Services;

class DatParser
{
    /**
     * Parse a clrmamepro DAT file
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("DAT file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);

        return [
            'header' => $this->parseHeader($content),
            'games' => $this->parseGames($content),
        ];
    }

    /**
     * Parse only the header of a DAT file (much faster for system imports)
     */
    public function parseHeaderOnly(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("DAT file not found: {$filePath}");
        }

        // Read only first 2KB which should contain the header
        $handle = fopen($filePath, 'r');
        $content = fread($handle, 2048);
        fclose($handle);

        return $this->parseHeader($content);
    }

    /**
     * Parse the clrmamepro header
     */
    public function parseHeader(string $content): array
    {
        $header = [];

        if (preg_match('/clrmamepro\s*\((.*?)\)/s', $content, $matches)) {
            $headerContent = $matches[1];

            // Parse name
            if (preg_match('/name\s+"([^"]+)"/', $headerContent, $nameMatch)) {
                $header['name'] = $nameMatch[1];
            }

            // Parse description
            if (preg_match('/description\s+"([^"]+)"/', $headerContent, $descMatch)) {
                $header['description'] = $descMatch[1];
            }

            // Parse version
            if (preg_match('/version\s+"([^"]+)"/', $headerContent, $versionMatch)) {
                $header['version'] = $versionMatch[1];
            }

            // Parse homepage
            if (preg_match('/homepage\s+"([^"]+)"/', $headerContent, $homepageMatch)) {
                $header['homepage'] = $homepageMatch[1];
            }
        }

        return $header;
    }

    /**
     * Parse all game entries
     */
    public function parseGames(string $content): array
    {
        $games = [];

        // Match all game blocks - handle both inline and multi-line closing
        preg_match_all('/game\s*\((.*?)\n\s*\)/s', $content, $matches);

        foreach ($matches[1] as $gameBlock) {
            $game = $this->parseGameBlock($gameBlock);
            if (!empty($game)) {
                $games[] = $game;
            }
        }

        return $games;
    }

    /**
     * Parse a single game block
     */
    protected function parseGameBlock(string $block): array
    {
        $game = [];

        // Parse name
        if (preg_match('/name\s+"([^"]+)"/', $block, $match)) {
            $game['name'] = $match[1];
        }

        // Parse comment (used as name in metadata files)
        if (preg_match('/comment\s+"([^"]+)"/', $block, $match)) {
            $game['comment'] = $match[1];
        }

        // Parse description
        if (preg_match('/description\s+"([^"]+)"/', $block, $match)) {
            $game['description'] = $match[1];
        }

        // Parse region
        if (preg_match('/region\s+"([^"]+)"/', $block, $match)) {
            $game['region'] = $match[1];
        }

        // Parse serial
        if (preg_match('/serial\s+"([^"]+)"/', $block, $match)) {
            $game['serial'] = $match[1];
        }

        // Parse developer
        if (preg_match('/developer\s+"([^"]+)"/', $block, $match)) {
            $game['developer'] = $match[1];
        }

        // Parse publisher
        if (preg_match('/publisher\s+"([^"]+)"/', $block, $match)) {
            $game['publisher'] = $match[1];
        }

        // Parse genre
        if (preg_match('/genre\s+"([^"]+)"/', $block, $match)) {
            $game['genre'] = $match[1];
        }

        // Parse release year
        if (preg_match('/releaseyear\s+"([^"]+)"/', $block, $match)) {
            $game['releaseyear'] = $match[1];
        }

        // Parse ROM data - extract the rom block with balanced parentheses
        if (preg_match('/rom\s*\(/', $block, $match, PREG_OFFSET_CAPTURE)) {
            $start = $match[0][1] + strlen($match[0][0]);
            $romBlock = $this->extractBalancedParens(substr($block, $start));
            if ($romBlock !== null) {
                $romData = $this->parseRomBlock($romBlock);
                $game = array_merge($game, $romData);
            }
        }

        return $game;
    }

    /**
     * Extract content within balanced parentheses
     */
    protected function extractBalancedParens(string $str): ?string
    {
        $depth = 1;
        $len = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            if ($str[$i] === '(') {
                $depth++;
            } elseif ($str[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    return substr($str, 0, $i);
                }
            }
        }

        return null;
    }

    /**
     * Parse ROM block
     */
    protected function parseRomBlock(string $romBlock): array
    {
        $rom = [];

        // Parse filename
        if (preg_match('/name\s+"([^"]+)"/', $romBlock, $match)) {
            $rom['filename'] = $match[1];
        }

        // Parse size
        if (preg_match('/size\s+(\d+)/', $romBlock, $match)) {
            $rom['size'] = (int) $match[1];
        }

        // Parse CRC (case insensitive)
        if (preg_match('/crc\s+([A-Fa-f0-9]+)/', $romBlock, $match)) {
            $rom['crc'] = strtoupper($match[1]);
        }

        // Parse MD5 (case insensitive)
        if (preg_match('/md5\s+([A-Fa-f0-9]+)/', $romBlock, $match)) {
            $rom['md5'] = strtoupper($match[1]);
        }

        // Parse SHA1 (case insensitive)
        if (preg_match('/sha1\s+([A-Fa-f0-9]+)/', $romBlock, $match)) {
            $rom['sha1'] = strtoupper($match[1]);
        }

        // Parse serial from ROM block (some formats have it here)
        if (preg_match('/serial\s+"([^"]+)"/', $romBlock, $match)) {
            $rom['serial'] = $match[1];
        }

        return $rom;
    }

    /**
     * Get all DAT files for a specific system
     */
    public function getSystemDatFiles(string $systemName, string $libretroDbPath): array
    {
        $datFiles = [];

        // Check in /dat folder
        $datFile = $libretroDbPath . '/dat/' . $systemName . '.dat';
        if (file_exists($datFile)) {
            $datFiles['dat'] = $datFile;
        }

        // Check in /metadat/no-intro
        $noIntroFile = $libretroDbPath . '/metadat/no-intro/' . $systemName . '.dat';
        if (file_exists($noIntroFile)) {
            $datFiles['no-intro'] = $noIntroFile;
        }

        // Check in /metadat/redump
        $redumpFile = $libretroDbPath . '/metadat/redump/' . $systemName . '.dat';
        if (file_exists($redumpFile)) {
            $datFiles['redump'] = $redumpFile;
        }

        // Check in /metadat/tosec
        $tosecFile = $libretroDbPath . '/metadat/tosec/' . $systemName . '.dat';
        if (file_exists($tosecFile)) {
            $datFiles['tosec'] = $tosecFile;
        }

        return $datFiles;
    }

    /**
     * Get all metadata DAT files for a system
     */
    public function getMetadataDatFiles(string $systemName, string $libretroDbPath): array
    {
        $metadataFiles = [];

        $metadataTypes = [
            'developer',
            'publisher',
            'genre',
            'releaseyear',
        ];

        foreach ($metadataTypes as $type) {
            $file = $libretroDbPath . '/metadat/' . $type . '/' . $systemName . '.dat';
            if (file_exists($file)) {
                $metadataFiles[$type] = $file;
            }
        }

        return $metadataFiles;
    }
}
