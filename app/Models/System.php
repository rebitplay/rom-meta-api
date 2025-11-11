<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class System extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($system) {
            if (empty($system->slug)) {
                $slug = Str::slug($system->name);
                $originalSlug = $slug;
                $counter = 1;

                // Handle duplicate slugs
                while (static::where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }

                $system->slug = $slug;
            }
        });
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
