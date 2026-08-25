<?php

namespace App\Policies;

use App\Models\Apartment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ApartmentPolicy extends AdminPolicy
{
    public function delete(User $user, mixed $model): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        return $model instanceof Apartment && $model->bookings()->exists()
            ? Response::deny('Apartemen tidak dapat dihapus karena masih memiliki riwayat reservasi.')
            : Response::allow();
    }
}
