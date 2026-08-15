<?php

namespace App\Models;

use App\Enums\ApartmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Apartment extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'price_per_night',
        'address',
        'city',
        'bedrooms',
        'bathrooms',
        'area_sqm',
        'capacity',
        'main_image',
        'images',
        'is_featured',
        'status',
    ];

    protected $casts = [
        'images' => 'array',
        'price_per_night' => 'decimal:2',
        'is_featured' => 'boolean',
        'status' => ApartmentStatus::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($apartment) {
            if (empty($apartment->slug)) {
                $apartment->slug = Str::slug($apartment->title).'-'.Str::random(5);
            }
        });
    }

    /**
     * Resolve a stored image path into a usable URL. Filament writes relative
     * disk paths ('apartments/main/x.jpg') while the seeder writes absolute
     * URLs — both have to render, so views go through here instead of
     * printing the raw column (which browsers resolve against the page URL).
     */
    public static function resolveImageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        if (Str::startsWith($path, ['/', 'storage/'])) {
            return asset(ltrim($path, '/'));
        }

        return Storage::url($path);
    }

    public function getMainImageUrlAttribute(): ?string
    {
        return static::resolveImageUrl($this->main_image);
    }

    /** @return array<int, string> */
    public function getGalleryUrlsAttribute(): array
    {
        return collect($this->images ?? [])
            ->map(fn ($path) => static::resolveImageUrl($path))
            ->filter()
            ->values()
            ->all();
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'apartment_facility');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
