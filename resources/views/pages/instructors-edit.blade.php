<x-layouts::app :title="__('Edit Instruktur')">
    @php
        $formSeed = [
            'name' => old('name', $instructor->name),
            'department' => old('department', $instructor->department ?? ''),
            'status' => old('status', $instructor->status),
            'notes' => old('notes', $instructor->notes ?? ''),
            'skills' => $instructor->skills->map(fn ($skill) => [
                'competency_id' => $skill->competency_id,
                'skill' => $skill->skill,
                'level' => $skill->level,
            ])->values()->all(),
            'availabilities' => $instructor->availabilities->map(fn ($avail) => [
                'day' => $avail->day,
                'start_time' => $avail->start_time,
                'end_time' => $avail->end_time,
            ])->values()->all(),
        ];
    @endphp
    @include('pages.partials.instructor-form', [
        'isEditing' => true,
        'formSeed' => $formSeed,
        'instructor' => $instructor,
        'competencies' => $competencies ?? \App\Models\Competency::active()->orderBy('department')->orderBy('name')->get(),
    ])
</x-layouts::app>
