<?php

namespace App\Policies;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FacilityPolicy extends AdminPolicy
{
    public function delete(User $user, mixed $model): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        return $model instanceof Facility && $model->apartments()->exists()
            ? Response::deny('Fasilitas tidak dapat dihapus karena masih digunakan oleh apartemen.')
            : Response::allow();
    }
}
