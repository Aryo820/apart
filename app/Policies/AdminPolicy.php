<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Base policy for panel resources: every ability requires an admin user.
 *
 * Panel access is already gated by User::canAccessPanel(); this adds
 * defense-in-depth at the Gate level so no resource is ever reachable
 * by a non-admin, even if panel access rules change later.
 *
 * Setiap ability yang mungkin ditanyakan panel HARUS punya method di sini.
 * Panel berjalan dalam strict authorization mode (AdminPanelProvider), jadi
 * ability yang tidak terdefinisi melempar LogicException — bukan menjadi ALLOW
 * seperti perilaku default Filament. Kelalaian menjadi kegagalan yang terlihat,
 * bukan izin yang tidak pernah diputuskan.
 */
abstract class AdminPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, mixed $model): bool|Response
    {
        return $user->isAdmin();
    }

    /**
     * Bulk delete. Filament menanyakan ability *Any untuk aksi massal dan TIDAK
     * memeriksa per baris, jadi ini harus diputuskan sendiri, bukan diwarisi
     * dari delete().
     *
     * Diizinkan untuk admin karena DeleteBulkAction memang terdaftar di
     * BookingResource, ApartmentResource, dan FacilityResource — jadi ini
     * menuliskan kemampuan yang sudah ada, bukan menambah yang baru.
     * PaymentPolicy menolaknya.
     */
    public function deleteAny(User $user): bool|Response
    {
        return $user->isAdmin();
    }

    public function restore(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    /*
     * Ability di bawah ini ditolak karena tidak ada satu pun jalur di project
     * ini yang menggunakannya, bukan karena "belum diputuskan":
     *
     *   restoreAny / forceDeleteAny — tidak ada model yang memakai SoftDeletes,
     *       jadi tidak ada yang bisa dipulihkan atau dihapus permanen secara
     *       massal. Versi tunggalnya (restore/forceDelete) sudah eksplisit di
     *       atas sejak awal dan dibiarkan apa adanya.
     *   replicate — tidak ada ReplicateAction; menduplikasi booking atau payment
     *       akan menghasilkan baris yang tidak pernah lewat guard integritas.
     *   reorder — tidak ada resource yang punya kolom urutan.
     *
     * Bila salah satu aksi itu kelak ditambahkan, penolakan ini akan langsung
     * terlihat di UI dan keputusannya diambil saat itu — jauh lebih baik
     * daripada aksi baru yang otomatis terizinkan.
     */
    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, mixed $model): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
