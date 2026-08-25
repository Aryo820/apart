<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy extends AdminPolicy
{
    public function delete(User $user, mixed $model): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        if (! $model instanceof User) {
            return Response::deny();
        }

        if ($model->bookings()->exists()) {
            return Response::deny('Pengguna tidak dapat dihapus karena masih memiliki riwayat reservasi.');
        }

        /*
         * Menghapus akun sendiri adalah satu-satunya jalur nyata menuju panel
         * tanpa admin: setiap penghapus lain yang lolos gate ini pasti admin,
         * jadi selalu ada minimal satu admin tersisa. Ditolak di sini, bukan
         * sekadar disembunyikan dari UI.
         */
        if ($model->is($user)) {
            return Response::deny('Anda tidak dapat menghapus akun Anda sendiri dari panel.');
        }

        return Response::allow();
    }
}
