<?php

namespace Database\Seeders;

use App\Enums\ApartmentStatus;
use App\Enums\UserRole;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Users
        // Credentials are read from env so no default password is committed.
        // In local dev a random password is generated and printed once;
        // in production set ADMIN_EMAIL / ADMIN_PASSWORD in .env.
        $adminEmail = env('ADMIN_EMAIL', 'admin@apart.com');
        $adminPassword = env('ADMIN_PASSWORD', Str::password(16));
        $userEmail = env('USER_EMAIL', 'user@apart.com');
        $userPassword = env('USER_PASSWORD', Str::password(16));

        $admin = User::create([
            'name' => 'Admin Apart',
            'email' => $adminEmail,
            'phone' => '081234567890',
            'role' => UserRole::Admin,
            'password' => Hash::make($adminPassword),
        ]);

        $user = User::create([
            'name' => 'John Customer',
            'email' => $userEmail,
            'phone' => '089876543210',
            'role' => UserRole::User,
            'password' => Hash::make($userPassword),
        ]);

        if ($this->command && ! app()->environment('testing')) {
            $this->command->warn("Seeded admin: {$adminEmail} / {$adminPassword}");
            $this->command->warn("Seeded user:  {$userEmail} / {$userPassword}");
        }

        // 2. Facilities
        $facilitiesData = [
            ['name' => 'High-Speed Wi-Fi', 'icon' => 'wifi', 'description' => 'Dedicated fiber optic internet up to 300 Mbps.'],
            ['name' => 'Private Swimming Pool', 'icon' => 'pool', 'description' => 'Infinity rooftop pool with panoramic sunset view.'],
            ['name' => 'Fitness Gym Center', 'icon' => 'gym', 'description' => 'Fully equipped gym with modern cardio & weight machines.'],
            ['name' => 'Smart TV & Netflix', 'icon' => 'tv', 'description' => '55 inch 4K OLED TV with free premium streaming access.'],
            ['name' => '24/7 Security & CCTV', 'icon' => 'shield', 'description' => 'Card keycard access with 24-hour security guards.'],
            ['name' => 'Underground Parking', 'icon' => 'parking', 'description' => 'Secure indoor parking slot included for guests.'],
            ['name' => 'Fully Equipped Kitchen', 'icon' => 'kitchen', 'description' => 'Microwave, stove, refrigerator, cookware & tableware.'],
            ['name' => 'Private Balcony', 'icon' => 'balcony', 'description' => 'Spacious outdoor balcony with city skyline views.'],
        ];

        $facilityModels = [];
        foreach ($facilitiesData as $f) {
            $facilityModels[] = Facility::create($f);
        }

        // 3. Apartments
        $apartments = [
            [
                'title' => 'The Grand Luxury Suite at Sudirman Central',
                'slug' => 'grand-luxury-suite-sudirman',
                'description' => 'Experience ultimate luxury living in the heart of Jakarta CBD. Featuring elegant interior design, Italian marble flooring, panoramic city views from the 35th floor, and direct access to Pacific Place Mall.',
                'price_per_night' => 1250000,
                'address' => 'Jl. Jend. Sudirman Kav. 52-53, SCBD',
                'city' => 'Jakarta',
                'bedrooms' => 2,
                'bathrooms' => 2,
                'area_sqm' => 85,
                'capacity' => 4,
                'main_image' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1200&q=80',
                'images' => [
                    'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=1200&q=80',
                ],
                'is_featured' => true,
                'status' => ApartmentStatus::Available,
            ],
            [
                'title' => 'Canggu Ocean View Loft Penthouse',
                'slug' => 'canggu-ocean-view-loft',
                'description' => 'Tropical modern aesthetic loft nestled in popular Echo Beach, Canggu Bali. Private plunge pool, floor-to-ceiling glass windows, custom teak furniture, and magnificent ocean breeze.',
                'price_per_night' => 1850000,
                'address' => 'Jl. Pantai Batu Mejan, Canggu',
                'city' => 'Bali',
                'bedrooms' => 3,
                'bathrooms' => 3,
                'area_sqm' => 140,
                'capacity' => 6,
                'main_image' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1200&q=80',
                'images' => [
                    'https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=1200&q=80',
                ],
                'is_featured' => true,
                'status' => ApartmentStatus::Available,
            ],
            [
                'title' => 'Minimalist Studio Apartment Dago Highlands',
                'slug' => 'minimalist-studio-dago',
                'description' => 'Cozy and modern studio apartment surrounded by cool mountain air in Dago, Bandung. Ideal for staycations, digital nomads, and young couples seeking peace and inspiration.',
                'price_per_night' => 650000,
                'address' => 'Jl. Ir. H. Juanda No. 185, Dago',
                'city' => 'Bandung',
                'bedrooms' => 1,
                'bathrooms' => 1,
                'area_sqm' => 38,
                'capacity' => 2,
                'main_image' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=1200&q=80',
                'images' => [
                    'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1200&q=80',
                ],
                'is_featured' => true,
                'status' => ApartmentStatus::Available,
            ],
            [
                'title' => 'Executive Residence Surabaya Townsquare',
                'slug' => 'executive-residence-surabaya',
                'description' => 'Premium 2-bedroom executive apartment connected to SUTOS mall. High-speed internet, working desk, King Koil mattresses, and 24-hour room service available.',
                'price_per_night' => 850000,
                'address' => 'Jl. Adityawarman No. 55',
                'city' => 'Surabaya',
                'bedrooms' => 2,
                'bathrooms' => 1,
                'area_sqm' => 62,
                'capacity' => 3,
                'main_image' => 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=1200&q=80',
                'images' => [
                    'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=1200&q=80',
                ],
                'is_featured' => false,
                'status' => ApartmentStatus::Available,
            ],
            [
                'title' => 'Ubud Sanctuary Villa & Apartment',
                'slug' => 'ubud-sanctuary-villa-apartment',
                'description' => 'Serene lush green jungle view residence in Penestanan Ubud. Features open air bath, yoga deck space, organic kitchen garden, and silent ambient surroundings.',
                'price_per_night' => 1450000,
                'address' => 'Jl. Raya Penestanan, Ubud',
                'city' => 'Bali',
                'bedrooms' => 2,
                'bathrooms' => 2,
                'area_sqm' => 95,
                'capacity' => 4,
                'main_image' => 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=1200&q=80',
                'images' => [
                    'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1200&q=80',
                ],
                'is_featured' => true,
                'status' => ApartmentStatus::Available,
            ],
            [
                'title' => 'Senopati Chic Residence & Skyline',
                'slug' => 'senopati-chic-residence',
                'description' => 'Trendy modern 1-bedroom apartment right in the heart of Senopati culinary district. Walking distance to famous cafes, fine dining, and boutique gyms.',
                'price_per_night' => 950000,
                'address' => 'Jl. Senopati No. 41, Kebayoran Baru',
                'city' => 'Jakarta',
                'bedrooms' => 1,
                'bathrooms' => 1,
                'area_sqm' => 45,
                'capacity' => 2,
                'main_image' => 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=1200&q=80',
                'images' => [
                    'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1200&q=80',
                ],
                'is_featured' => false,
                'status' => ApartmentStatus::Available,
            ],
        ];

        foreach ($apartments as $index => $aptData) {
            $apt = Apartment::create($aptData);
            // Attach random facilities
            $apt->facilities()->attach(array_unique([
                $facilityModels[0]->id,
                $facilityModels[3]->id,
                $facilityModels[4]->id,
                $facilityModels[$index % count($facilityModels)]->id,
            ]));

            // Create sample booking & payment for the first 2 apartments
            if ($index < 2) {
                $booking = Booking::create([
                    'booking_code' => 'APT-'.date('Ymd').'-'.strtoupper(Str::random(4)),
                    'user_id' => $user->id,
                    'apartment_id' => $apt->id,
                    'check_in' => now()->addDays(2),
                    'check_out' => now()->addDays(5),
                    'total_nights' => 3,
                    'total_price' => $apt->price_per_night * 3,
                    'status' => $index === 0 ? 'confirmed' : 'pending',
                    'notes' => 'Tolong siapkan ekstra bantal dan late check-in jam 8 malam.',
                ]);

                Payment::create([
                    'booking_id' => $booking->id,
                    'transaction_id' => 'TRX-'.Str::upper(Str::random(10)),
                    'snap_token' => 'DEMO-SNAP-TOKEN-'.Str::random(8),
                    'payment_type' => 'gopay',
                    'gross_amount' => $booking->total_price,
                    'status' => $index === 0 ? 'settlement' : 'pending',
                ]);
            }
        }
    }
}
