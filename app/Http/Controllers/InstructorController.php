<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInstructorRequest;
use App\Http\Requests\UpdateInstructorRequest;
use App\Models\Competency;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\InstructorSkill;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InstructorController extends Controller
{
    /**
     * Tampilkan form pembuatan instruktur baru.
     */
    public function create(): View
    {
        Gate::authorize('create', Instructor::class);

        $competencies = Competency::active()->orderBy('department')->orderBy('name')->get();

        return view('pages.instructors-create', [
            'competencies' => $competencies,
        ]);
    }

    /**
     * Simpan instruktur baru secara atomik.
     */
    public function store(StoreInstructorRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request) {
            $instructor = Instructor::create([
                'name' => $validated['name'],
                'department' => $validated['department'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Simpan Kompetensi
            $competenciesInput = (array) $request->input('competencies', []);
            foreach ($competenciesInput as $item) {
                $comp = Competency::find($item['id'] ?? null);
                if ($comp) {
                    InstructorSkill::create([
                        'instructor_id' => $instructor->id,
                        'competency_id' => $comp->id,
                        'skill' => $comp->name,
                        'level' => $item['level'],
                    ]);
                }
            }

            // Simpan Ketersediaan
            $availabilitiesInput = (array) $request->input('availabilities', []);
            foreach ($availabilitiesInput as $dayData) {
                if (empty($dayData['enabled']) || empty($dayData['slots'])) {
                    continue;
                }
                $dayName = $dayData['label'];
                foreach ($dayData['slots'] as $slot) {
                    if (! empty($slot['start']) && ! empty($slot['end'])) {
                        InstructorAvailability::create([
                            'instructor_id' => $instructor->id,
                            'day' => $dayName,
                            'start_time' => trim($slot['start']),
                            'end_time' => trim($slot['end']),
                        ]);
                    }
                }
            }
        });

        return redirect()->route('instructors.index')
            ->with('success', 'Instruktur baru berhasil ditambahkan.');
    }

    /**
     * Tampilkan form edit instruktur.
     */
    public function edit(Instructor $instructor): View
    {
        Gate::authorize('update', $instructor);

        $instructor->load(['skills.competency', 'availabilities']);
        $competencies = Competency::active()->orderBy('department')->orderBy('name')->get();

        return view('pages.instructors-edit', [
            'instructor' => $instructor,
            'competencies' => $competencies,
        ]);
    }

    /**
     * Perbarui data instruktur secara atomik.
     */
    public function update(UpdateInstructorRequest $request, Instructor $instructor): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request, $instructor) {
            $instructor->update([
                'name' => $validated['name'],
                'department' => $validated['department'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Sinkronkan Kompetensi
            $competenciesInput = (array) $request->input('competencies', []);

            // Hapus kompetensi yang sudah berelasi master yang tidak lagi dipilih
            $instructor->skills()->whereNotNull('competency_id')->delete();

            // Insert kompetensi baru yang dipilih
            foreach ($competenciesInput as $item) {
                $comp = Competency::find($item['id'] ?? null);
                if ($comp) {
                    $existingLegacy = $instructor->skills()->whereNull('competency_id')
                        ->whereRaw('LOWER(TRIM(skill)) = ?', [strtolower(trim($comp->name))])
                        ->first();

                    if ($existingLegacy) {
                        $existingLegacy->update([
                            'competency_id' => $comp->id,
                            'skill' => $comp->name,
                            'level' => $item['level'],
                        ]);
                    } else {
                        InstructorSkill::create([
                            'instructor_id' => $instructor->id,
                            'competency_id' => $comp->id,
                            'skill' => $comp->name,
                            'level' => $item['level'],
                        ]);
                    }
                }
            }

            // Sinkronkan Ketersediaan
            $instructor->availabilities()->delete();

            $availabilitiesInput = (array) $request->input('availabilities', []);
            foreach ($availabilitiesInput as $dayData) {
                if (empty($dayData['enabled']) || empty($dayData['slots'])) {
                    continue;
                }
                $dayName = $dayData['label'];
                foreach ($dayData['slots'] as $slot) {
                    if (! empty($slot['start']) && ! empty($slot['end'])) {
                        InstructorAvailability::create([
                            'instructor_id' => $instructor->id,
                            'day' => $dayName,
                            'start_time' => trim($slot['start']),
                            'end_time' => trim($slot['end']),
                        ]);
                    }
                }
            }
        });

        return redirect()->route('instructors.index')
            ->with('success', 'Perubahan instruktur berhasil disimpan.');
    }
}
