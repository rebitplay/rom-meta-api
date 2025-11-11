<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Game extends Model
{
    protected $fillable = [
        'system_id',
        'name',
        'description',
        'region',
        'release_year',
        'crc',
        'md5',
        'sha1',
        'serial',
        'size',
        'filename',
    ];

    protected $casts = [
        'release_year' => 'integer',
        'size' => 'integer',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function developers(): BelongsToMany
    {
        return $this->belongsToMany(Developer::class, 'game_developer');
    }

    public function publishers(): BelongsToMany
    {
        return $this->belongsToMany(Publisher::class, 'game_publisher');
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'game_genre');
    }

    /**
     * Scope to find by CRC
     */
    public function scopeByCrc($query, string $crc)
    {
        return $query->where('crc', strtoupper($crc));
    }

    /**
     * Scope to find by MD5
     */
    public function scopeByMd5($query, string $md5)
    {
        return $query->where('md5', strtoupper($md5));
    }

    /**
     * Scope to find by SHA1
     */
    public function scopeBySha1($query, string $sha1)
    {
        return $query->where('sha1', strtoupper($sha1));
    }

    /**
     * Scope to find by serial
     */
    public function scopeBySerial($query, string $serial)
    {
        return $query->where('serial', $serial);
    }
}
