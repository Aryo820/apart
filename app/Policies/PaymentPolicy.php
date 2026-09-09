<?php

namespace App\Policies;

use App\Models\User;

/**
 * Payment hanya boleh DIBACA dari panel.
 *
 * Baris payment ditulis oleh jalur yang sudah dikeraskan — PaymentStatusApplier
 * (webhook + rekonsiliasi), BookingController saat sesi pembayaran dibuka atau
 * ditutup bersama pembatalan, dan bookings:expire-pending. Semua penulis itu
 * memakai Eloquent langsung dan tidak melewati Gate, jadi penolakan di sini
 * tidak menyentuh mereka sama sekali; yang ditutup hanyalah jalur manual.
 *
 * Ditempatkan di policy, bukan hanya dengan menghapus tombol di
 * PaymentResource: visibilitas UI bukan authorization. Filament menanyakan
 * ability ini pada setiap create/edit/delete action, termasuk yang dipanggil
 * lewat request Livewire buatan sendiri.
 */
class PaymentPolicy extends AdminPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $model): bool
    {
        return false;
    }

    public function delete(User $user, mixed $model): bool
    {
        return false;
    }

    /**
     * Menandai insiden finansial "Perlu Tindakan" selesai: mencatat bahwa
     * operator telah menyelesaikan follow-up manual (mis. refund di
     * dashboard Midtrans). Admin-only, seperti seluruh aksi panel payment.
     *
     * Ini BUKAN update() biasa: ability update ditolak permanen di bawah
     * karena form edit payment tidak boleh ada. Resolve attention punya
     * jalur sendiri yang hanya menyentuh kolom resolusi operasional —
     * bukan status payment gateway.
     */
    public function resolveAttention(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Bulk delete ditolak eksplisit, bukan hanya "tidak ada tombolnya".
     *
     * AdminPolicy::deleteAny mengizinkan admin karena resource lain memang punya
     * DeleteBulkAction; tanpa penolakan di sini, menambahkan aksi massal ke
     * PaymentResource kelak akan langsung terizinkan — dan delete() di atas
     * tidak akan menahannya, karena aksi massal menanyakan deleteAny.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, mixed $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return false;
    }
}
