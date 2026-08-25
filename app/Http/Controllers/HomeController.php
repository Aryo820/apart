<?php

namespace App\Http\Controllers;

use App\Enums\ApartmentStatus;
use App\Models\Apartment;
use App\Models\Facility;

class HomeController extends Controller
{
    public function index()
    {
        $featuredApartments = Apartment::with('facilities')
            ->where('status', ApartmentStatus::Available)
            ->where('is_featured', true)
            ->take(3)
            ->get();

        // 6, bukan 8: grid fasilitas maksimal 3 kolom, jadi 6 mengisi dua baris
        // penuh. Sebelumnya 8 diambil sementara view hanya memakai 5.
        $facilities = Facility::take(6)->get();
        $cities = Apartment::distinct()->pluck('city');
        $maxCapacity = (int) Apartment::where('status', ApartmentStatus::Available)->max('capacity');

        return view('home', compact('featuredApartments', 'facilities', 'cities', 'maxCapacity'));
    }
}
