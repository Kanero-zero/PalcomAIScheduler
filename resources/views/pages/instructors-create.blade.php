<x-layouts::app :title="__('Tambah Instruktur')">
    @include('pages.partials.instructor-form', ['isEditing' => false, 'formSeed' => null, 'instructor' => null])
</x-layouts::app>
