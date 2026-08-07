<?php

namespace App\Models;

use App\Enums\ApartmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'apartment_facility');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
