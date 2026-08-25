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

        return $model instanceof User && $model->bookings()->exists()
            ? Response::deny('Pengguna tidak dapat dihapus karena masih memiliki riwayat reservasi.')
            : Response::allow();
    }
}
