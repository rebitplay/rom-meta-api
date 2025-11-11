<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Publisher extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($publisher) {
            if (empty($publisher->slug)) {
                $slug = Str::slug($publisher->name);
                $originalSlug = $slug;
                $counter = 1;

                // Handle duplicate slugs
                while (static::where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }

                $publisher->slug = $slug;
            }
        });
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_publisher');
    }
}
