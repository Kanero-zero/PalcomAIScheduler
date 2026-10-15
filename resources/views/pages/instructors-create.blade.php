<x-layouts::app :title="__('Tambah Instruktur')">
    @include('pages.partials.instructor-form', [
        'isEditing' => false,
        'formSeed' => null,
        'instructor' => null,
        'competencies' => $competencies ?? \App\Models\Competency::active()->orderBy('department')->orderBy('name')->get(),
    ])
</x-layouts::app>
