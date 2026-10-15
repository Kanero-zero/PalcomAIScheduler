<x-layouts::app :title="__('Edit Instruktur')">
    @php
        $formSeed = [
            'name' => $instructor->name,
            'status' => $instructor->status,
            'skills' => $instructor->skills->map(fn ($skill) => [
                'skill' => $skill->skill,
                'level' => $skill->level,
            ])->values()->all(),
        ];
    @endphp
    @include('pages.partials.instructor-form', ['isEditing' => true, 'formSeed' => $formSeed, 'instructor' => $instructor])
</x-layouts::app>
