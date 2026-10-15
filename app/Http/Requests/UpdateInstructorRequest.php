<?php

namespace App\Http\Requests;

use App\Models\Instructor;

class UpdateInstructorRequest extends StoreInstructorRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $instructor = $this->route('instructor');

        return $this->user()?->can('update', $instructor instanceof Instructor ? $instructor : Instructor::findOrFail($instructor)) ?? false;
    }

    /**
     * Tentukan apakah penugasan kompetensi ini merupakan penugasan baru.
     * Jika instruktur sudah memiliki kompetensi ini secara historis, penugasan ini diizinkan dipertahankan.
     */
    protected function isNewCompetencyAssignment(int $competencyId): bool
    {
        $instructor = $this->route('instructor');
        if (! $instructor instanceof Instructor) {
            $instructor = Instructor::find($instructor);
        }

        if (! $instructor) {
            return true;
        }

        return ! $instructor->skills()->where('competency_id', $competencyId)->exists();
    }
}
