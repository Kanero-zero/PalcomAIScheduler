<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Room $room): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can create models.
     * Hanya Admin dan BAAK yang berwenang menambah ruangan.
     */
    public function create(User $user): bool
    {
        return $user->canManageRooms();
    }

    /**
     * Determine whether the user can update the model.
     * Hanya Admin dan BAAK yang berwenang mengedit ruangan.
     */
    public function update(User $user, Room $room): bool
    {
        return $user->canManageRooms();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Room $room): bool
    {
        return $user->isAdmin();
    }
}
