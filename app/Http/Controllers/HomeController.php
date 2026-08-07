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
            ->take(6)
            ->get();

        $facilities = Facility::take(8)->get();
        $cities = Apartment::distinct()->pluck('city');
        $maxCapacity = (int) Apartment::where('status', ApartmentStatus::Available)->max('capacity');

        return view('home', compact('featuredApartments', 'facilities', 'cities', 'maxCapacity'));
    }
}
