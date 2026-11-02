<?php

namespace App\Services\Scheduling;

use App\Models\Competency;
use App\Models\CurriculumVersion;
use App\Models\Facility;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Room;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * G3-02: one trusted validation/normalization boundary for both structured input modes.
 * Read-only; this service does not invoke an AI API or reserve a scheduling slot.
 */
class SharedDraftNormalizationService
{
    private const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    private const ALLOWED_FIELDS = [
        'input_mode', 'title', 'department', 'class_kind', 'program_id',
        'curriculum_version_id', 'student_count', 'start_date', 'end_date',
        'total_meetings', 'frequency_per_week', 'session_duration_minutes',
        'preferred_weekdays', 'preferred_time_window', 'preferred_instructor_id',
        'preferred_room_id', 'required_competency_ids', 'required_facility_codes',
        'facility_notes', 'ai_prompt',
    ];

    /**
     * @return array<string, mixed> Canonical and allowlisted draft payload.
     *
     * @throws ValidationException
     */
    public function normalize(array $input): array
    {
        $data = array_intersect_key($input, array_flip(self::ALLOWED_FIELDS));

        foreach (['input_mode', 'title', 'department', 'class_kind', 'start_date', 'end_date', 'facility_notes', 'ai_prompt'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = trim($data[$key]);
            }
        }

        foreach (['program_id', 'curriculum_version_id', 'preferred_instructor_id', 'preferred_room_id', 'facility_notes', 'ai_prompt'] as $key) {
            if (($data[$key] ?? null) === '') {
                $data[$key] = null;
            }
        }

        if (isset($data['preferred_weekdays']) && is_array($data['preferred_weekdays'])) {
            $data['preferred_weekdays'] = array_map(
                fn ($day) => is_string($day) ? trim($day) : $day,
                $data['preferred_weekdays']
            );
        }

        if (isset($data['required_facility_codes']) && is_array($data['required_facility_codes'])) {
            $data['required_facility_codes'] = array_map(
                fn ($code) => is_string($code) ? trim($code) : $code,
                $data['required_facility_codes']
            );
        }

        if (isset($data['preferred_time_window']) && is_array($data['preferred_time_window'])) {
            foreach (['start', 'end'] as $side) {
                $time = $data['preferred_time_window'][$side] ?? null;
                if (is_string($time)) {
                    $time = trim($time);
                    if (preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time)) {
                        $time .= ':00';
                    }
                    $data['preferred_time_window'][$side] = $time;
                }
            }
        }

        $validator = Validator::make($data, [
            'input_mode' => ['required', 'string', 'in:ai_assistant,manual_input'],
            'title' => ['required', 'string', 'max:150'],
            'department' => ['required', 'string', 'in:computer,speakout'],
            'class_kind' => ['required', 'string', 'in:regular,private'],
            'program_id' => ['nullable', 'integer', 'min:1'],
            'curriculum_version_id' => ['nullable', 'integer', 'min:1'],
            'student_count' => ['required', 'integer', 'min:1', 'max:100'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'total_meetings' => ['required', 'integer', 'min:1', 'max:100'],
            'frequency_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'session_duration_minutes' => ['required', 'integer', 'in:60,90,120,180'],
            'preferred_weekdays' => ['required', 'array', 'min:1', 'max:6'],
            'preferred_weekdays.*' => ['required', 'string', 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu', 'distinct'],
            'preferred_time_window' => ['required', 'array:start,end'],
            'preferred_time_window.start' => ['required', 'date_format:H:i:s'],
            'preferred_time_window.end' => ['required', 'date_format:H:i:s'],
            'preferred_instructor_id' => ['nullable', 'integer', 'min:1'],
            'preferred_room_id' => ['nullable', 'integer', 'min:1'],
            'required_competency_ids' => ['sometimes', 'array', 'max:100'],
            'required_competency_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'required_facility_codes' => ['sometimes', 'array', 'max:100'],
            'required_facility_codes.*' => ['required', 'string', 'max:100', 'distinct'],
            'facility_notes' => ['nullable', 'string', 'max:2000'],
            'ai_prompt' => ['nullable', 'string', 'max:5000'],
        ]);

        $validator->after(function ($validator) use ($data): void {
            $errors = $validator->errors();

            if (! $errors->has('start_date') && is_string($data['start_date'] ?? null)
                && $data['start_date'] < now('Asia/Jakarta')->toDateString()) {
                $errors->add('start_date', 'Tanggal mulai tidak boleh berada di masa lalu (WIB).');
            }

            $window = $data['preferred_time_window'] ?? null;
            if (is_array($window) && ! $errors->has('preferred_time_window.start')
                && ! $errors->has('preferred_time_window.end')) {
                $start = $window['start'];
                $end = $window['end'];
                if ($end <= $start) {
                    $errors->add('preferred_time_window.end', 'Jam selesai harus setelah jam mulai.');
                } elseif (! $errors->has('session_duration_minutes')
                    && (strtotime("1970-01-01 {$end} UTC") - strtotime("1970-01-01 {$start} UTC")) < ((int) $data['session_duration_minutes'] * 60)) {
                    $errors->add('preferred_time_window', 'Rentang jam lebih pendek daripada durasi sesi.');
                }
            }

            if (! $errors->has('frequency_per_week') && ! $errors->has('preferred_weekdays')
                && ! $errors->has('preferred_weekdays.*') && is_array($data['preferred_weekdays'] ?? null)
                && (int) $data['frequency_per_week'] > count($data['preferred_weekdays'])) {
                $errors->add('frequency_per_week', 'Frekuensi melebihi jumlah hari preferensi yang tersedia.');
            }

            if ($errors->any() || ! is_string($data['department'] ?? null)) {
                return;
            }

            $department = $data['department'];
            $programId = $data['program_id'] ?? null;
            $versionId = $data['curriculum_version_id'] ?? null;

            if ($programId !== null && ! Program::query()->whereKey($programId)
                ->where('department', $department)->where('is_active', true)->exists()) {
                $errors->add('program_id', 'Program tidak aktif atau tidak sesuai bidang.');
            }

            if ($versionId !== null) {
                if ($programId === null) {
                    $errors->add('curriculum_version_id', 'Pilih program sebelum versi kurikulum.');
                } elseif (! CurriculumVersion::query()->whereKey($versionId)->where('program_id', $programId)
                    ->where('status', 'published')->whereHas('program', fn ($query) => $query
                    ->where('department', $department)->where('is_active', true))->exists()) {
                    $errors->add('curriculum_version_id', 'Versi kurikulum tidak published atau tidak sesuai program.');
                }
            }

            $competencyIds = $data['required_competency_ids'] ?? [];
            if ($competencyIds !== []) {
                $found = Competency::query()->whereIn('id', $competencyIds)->where('department', $department)
                    ->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
                if (count($found) !== count($competencyIds)) {
                    $errors->add('required_competency_ids', 'Kompetensi tidak aktif atau berbeda bidang.');
                }
            }

            $codes = $data['required_facility_codes'] ?? [];
            if ($codes !== []) {
                $found = Facility::query()->whereIn('code', $codes)->where('is_active', true)->pluck('code')->all();
                if (count($found) !== count($codes)) {
                    $errors->add('required_facility_codes', 'Fasilitas tidak ditemukan atau tidak aktif.');
                }
            }

            $instructorId = $data['preferred_instructor_id'] ?? null;
            if ($instructorId !== null && ! Instructor::query()->whereKey($instructorId)
                ->where('department', $department)->where('status', 'active')->exists()) {
                $errors->add('preferred_instructor_id', 'Instruktur tidak aktif atau berbeda bidang.');
            }

            $roomId = $data['preferred_room_id'] ?? null;
            if ($roomId !== null && ! Room::query()->whereKey($roomId)->where('department', $department)
                ->where('status', 'available')->where('capacity', '>=', (int) $data['student_count'])->exists()) {
                $errors->add('preferred_room_id', 'Ruangan tidak tersedia, berbeda bidang, atau kapasitas tidak cukup.');
            }
        });

        $validated = $validator->validate();
        $validated['preferred_weekdays'] = array_values(array_filter(
            self::DAYS, fn ($day) => in_array($day, $validated['preferred_weekdays'], true)
        ));
        $validated['required_competency_ids'] = array_values(array_map('intval', $validated['required_competency_ids'] ?? []));
        sort($validated['required_competency_ids'], SORT_NUMERIC);
        $validated['required_facility_codes'] = $validated['required_facility_codes'] ?? [];
        sort($validated['required_facility_codes'], SORT_STRING);

        foreach (['program_id', 'curriculum_version_id', 'preferred_instructor_id', 'preferred_room_id'] as $key) {
            $validated[$key] = isset($validated[$key]) ? (int) $validated[$key] : null;
        }
        foreach (['student_count', 'total_meetings', 'frequency_per_week', 'session_duration_minutes'] as $key) {
            $validated[$key] = (int) $validated[$key];
        }

        return $validated;
    }
}
