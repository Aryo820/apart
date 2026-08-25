<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookingPolicy extends AdminPolicy
{
    public function delete(User $user, mixed $model): Response
    {
        return Response::deny('Riwayat reservasi tidak dapat dihapus dari panel admin.');
    }

    public function deleteAny(User $user): Response
    {
        return Response::deny('Riwayat reservasi tidak dapat dihapus secara massal.');
    }

    /**
     * Pembatalan oleh tamu: hanya pemilik booking, dan hanya jika aturannya
     * memang mengizinkan (belum dibayar, belum kedaluwarsa). Admin sengaja
     * TIDAK diikutkan di sini — perubahan status oleh admin jalurnya lewat
     * panel Filament, bukan endpoint customer-facing ini.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id
            && $booking->isCancellableByGuest();
    }
}
