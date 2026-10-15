<?php

namespace App\Policies;

use App\Models\Instructor;
use App\Models\User;

class InstructorPolicy
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
    public function view(?User $user, Instructor $instructor): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can create models.
     * Hanya Admin dan BAAK yang berhak menambah instruktur.
     */
    public function create(User $user): bool
    {
        return $user->canManageInstructors();
    }

    /**
     * Determine whether the user can update the model.
     * Hanya Admin dan BAAK yang berhak mengedit instruktur.
     */
    public function update(User $user, Instructor $instructor): bool
    {
        return $user->canManageInstructors();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Instructor $instructor): bool
    {
        return $user->isAdmin();
    }
}
