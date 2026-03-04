<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EducationalModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'content',
        'category',
        'image',
        'content_images',
        'view_count',
        'is_active',
        'is_maintenance_guide',
        'watering_info',
        'light_info',
        'humidity_info',
        'difficulty',
        'maintenance_steps_json',
    ];

    protected $casts = [
        'view_count' => 'integer',
        'is_active' => 'boolean',
        'is_maintenance_guide' => 'boolean',
        'content_images' => 'array',
        'maintenance_steps_json' => 'array',
    ];

    /**
     * Relasi dengan users melalui bookmarks
     */
    public function bookmarkedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookmarks')
            ->withTimestamps();
    }
}



