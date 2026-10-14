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
}
