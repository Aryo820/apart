<?php

namespace App\Http\Controllers;

use App\Enums\ApartmentStatus;
use App\Enums\BookingStatus;
use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Facility;
use Illuminate\Http\Request;

class ApartmentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0',
            'capacity' => 'nullable|integer|min:0',
            // 'sort' sengaja tidak divalidasi ketat: nilai tak dikenal
            // ditangani switch di bawah dengan fallback default.
        ]);

        $query = Apartment::with('facilities')->where('status', ApartmentStatus::Available);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        if ($request->filled('min_price')) {
            $query->where('price_per_night', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_night', '<=', $request->max_price);
        }

        if ($request->filled('bedrooms')) {
            $query->where('bedrooms', '>=', $request->bedrooms);
        }

        if ($request->filled('capacity')) {
            $query->where('capacity', '>=', $request->capacity);
        }

        if ($request->filled('sort')) {
            switch ($request->sort) {
                case 'price_low':
                    $query->orderBy('price_per_night', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('price_per_night', 'desc');
                    break;
                case 'newest':
                    $query->latest();
                    break;
                default:
                    $query->latest();
            }
        } else {
            $query->latest();
        }

        $apartments = $query->paginate(9)->withQueryString();
        $cities = Apartment::distinct()->pluck('city');
        $facilities = Facility::all();

        return view('apartments.index', compact('apartments', 'cities', 'facilities'));
    }

    public function show($slug)
    {
        $apartment = Apartment::with('facilities')
            ->where('slug', $slug)
            ->where('status', ApartmentStatus::Available)
            ->firstOrFail();

        // Already booked dates
        $bookedDates = Booking::where('apartment_id', $apartment->id)
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Pending->value])
            ->get(['check_in', 'check_out'])
            ->map(function ($booking) {
                return [
                    'from' => $booking->check_in->format('Y-m-d'),
                    'to' => $booking->check_out->format('Y-m-d'),
                ];
            });

        return view('apartments.show', compact('apartment', 'bookedDates'));
    }

    public function checkAvailability(Request $request, $id)
    {
        $request->validate([
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        $apartmentAvailable = Apartment::where('id', $id)
            ->where('status', ApartmentStatus::Available)
            ->exists();

        if (! $apartmentAvailable) {
            return response()->json([
                'available' => false,
                'message' => 'Apartemen ini sedang tidak tersedia.',
            ]);
        }

        $conflict = Booking::conflicting((int) $id, $request->check_in, $request->check_out)->exists();

        return response()->json([
            'available' => ! $conflict,
            'message' => $conflict ? 'Tanggal yang dipilih sudah dipesan.' : 'Tanggal tersedia untuk dipesan!',
        ]);
    }
}
