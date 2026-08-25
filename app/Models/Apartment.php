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

    /**
     * Placeholder statis untuk unit tanpa foto. Aset biasa di public/, bukan
     * lewat disk: ia harus tetap tampil justru ketika storage bermasalah.
     * resources/js/app.js memakai path yang sama untuk file yang hilang.
     */
    public const PLACEHOLDER_IMAGE = 'images/unit-placeholder.svg';

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

        /*
         * Disk 'public' dipatok di sini, bukan mengikuti FILESYSTEM_DISK:
         * unggahannya juga dipatok ke disk yang sama (ApartmentResource), jadi
         * penulis file dan pembuat URL tidak bisa lagi berbeda pendapat hanya
         * karena satu variabel .env. Sebelumnya deploy dengan
         * FILESYSTEM_DISK=local menyimpan foto ke storage/app/private
         * sementara URL-nya tetap /storage/... — bentuk URL-nya benar, filenya
         * tidak pernah ada di sana.
         */
        return Storage::disk('public')->url($path);
    }

    public function getMainImageUrlAttribute(): ?string
    {
        return static::resolveImageUrl($this->main_image);
    }

    /**
     * URL yang selalu aman dipasang di atribut src. main_image_url tetap boleh
     * null (hero beranda memakainya untuk memutuskan apakah ada foto yang layak
     * jadi latar); src="" tidak boleh — browser memperlakukannya sebagai
     * permintaan ke URL halaman itu sendiri, jadi satu unit tanpa foto berarti
     * satu request HTML ekstra per kartu.
     *
     * File yang hilang dari disk tidak terdeteksi di sini (butuh stat per
     * gambar, satu I/O per kartu); itu ditangani listener error di
     * resources/js/app.js yang menukar src ke placeholder yang sama.
     */
    public function getDisplayImageUrlAttribute(): string
    {
        return $this->main_image_url ?? asset(self::PLACEHOLDER_IMAGE);
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
