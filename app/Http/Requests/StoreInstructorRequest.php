<?php

namespace App\Http\Requests;

use App\Models\Competency;
use App\Models\Instructor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInstructorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Instructor::class) ?? false;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->competencies)) {
            $merge['competencies'] = json_decode($this->competencies, true) ?? [];
        }

        if (is_string($this->availabilities)) {
            $merge['availabilities'] = json_decode($this->availabilities, true) ?? [];
        }

        $rawAvailabilities = $merge['availabilities'] ?? $this->availabilities;
        if (is_array($rawAvailabilities) && ! empty($rawAvailabilities)) {
            $first = reset($rawAvailabilities);
            if (is_array($first) && (isset($first['day']) || (isset($first['start_time']) && isset($first['end_time'])))) {
                $grouped = [];
                foreach ($rawAvailabilities as $item) {
                    $day = $item['day'] ?? $item['label'] ?? null;
                    if (! $day) {
                        continue;
                    }
                    if (! isset($grouped[$day])) {
                        $grouped[$day] = [
                            'id' => strtolower((string) $day),
                            'label' => $day,
                            'enabled' => true,
                            'slots' => [],
                        ];
                    }
                    $start = $item['start_time'] ?? $item['start'] ?? '';
                    $end = $item['end_time'] ?? $item['end'] ?? '';
                    $grouped[$day]['slots'][] = [
                        'start' => $start,
                        'end' => $end,
                    ];
                }
                $merge['availabilities'] = array_values($grouped);
            }
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'department' => ['required', 'string', 'in:computer,speakout'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:300'],
            'competencies' => ['nullable', 'array'],
            'competencies.*.id' => ['required'],
            'competencies.*.level' => ['required', 'string', 'in:beginner,intermediate,advanced'],
            'availabilities' => ['nullable', 'array'],
        ];
    }

    /**
     * Configure the validator instance with custom domain checks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $department = $this->input('department');
            $competencies = (array) $this->input('competencies', []);

            // 1. Validasi Kompetensi: Cek duplikasi dan kecocokan bidang
            $seenIds = [];
            foreach ($competencies as $index => $comp) {
                $id = $comp['id'] ?? null;
                if (! $id) {
                    continue;
                }

                if (in_array($id, $seenIds, true)) {
                    $v->errors()->add("competencies.{$index}.id", 'Kompetensi tidak boleh didaftarkan duplikat pada instruktur yang sama.');
                    $v->errors()->add('competencies', 'Kompetensi tidak boleh didaftarkan duplikat pada instruktur yang sama.');
                }
                $seenIds[] = $id;

                $master = Competency::find($id);
                if (! $master) {
                    $v->errors()->add("competencies.{$index}.id", 'Kompetensi tidak ditemukan di master katalog.');
                    $v->errors()->add('competencies', 'Kompetensi tidak ditemukan di master katalog.');
                } elseif ($department && $master->department !== $department) {
                    $v->errors()->add("competencies.{$index}.id", "Kompetensi '{$master->name}' tidak sesuai dengan bidang pembelajaran instruktur ({$department}).");
                    $v->errors()->add('competencies', "Kompetensi '{$master->name}' tidak sesuai dengan bidang pembelajaran instruktur ({$department}).");
                }
            }

            // 2. Validasi Ketersediaan: Cek rentang waktu, format, start < end, dan overlap
            $availabilities = (array) $this->input('availabilities', []);
            $validDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

            foreach ($availabilities as $dayData) {
                $dayName = $dayData['label'] ?? null;
                $enabled = ! empty($dayData['enabled']);
                $slots = (array) ($dayData['slots'] ?? []);

                if (! $enabled || empty($slots)) {
                    continue;
                }

                if (! in_array($dayName, $validDays, true)) {
                    $v->errors()->add('availabilities', "Nama hari '{$dayName}' tidak valid.");

                    continue;
                }

                $parsedSlots = [];
                foreach ($slots as $slotIdx => $slot) {
                    $start = trim((string) ($slot['start'] ?? ''));
                    $end = trim((string) ($slot['end'] ?? ''));

                    if ($start === '' || $end === '') {
                        $v->errors()->add('availabilities', "Jam mulai dan jam selesai untuk hari {$dayName} wajib diisi.");

                        continue;
                    }

                    if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end)) {
                        $v->errors()->add('availabilities', "Format jam untuk hari {$dayName} harus HH:MM.");

                        continue;
                    }

                    if ($start >= $end) {
                        $v->errors()->add('availabilities', "Rentang jam pada hari {$dayName} tidak valid: jam mulai ({$start}) harus sebelum jam selesai ({$end}).");

                        continue;
                    }

                    // Cek overlap terhadap slot lain pada hari yang sama (rentang bersebelahan start == other.end diperbolehkan)
                    foreach ($parsedSlots as $other) {
                        if ($start < $other['end'] && $end > $other['start']) {
                            $v->errors()->add('availabilities', "Rentang jam {$start}-{$end} bertabrakan (overlap) dengan rentang {$other['start']}-{$other['end']} pada hari {$dayName}.");
                            break;
                        }
                    }

                    $parsedSlots[] = ['start' => $start, 'end' => $end];
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap tidak boleh lebih dari 150 karakter.',
            'department.required' => 'Bidang pembelajaran wajib dipilih.',
            'department.in' => 'Bidang pembelajaran harus salah satu dari: Komputer atau SpeakOut.',
            'status.required' => 'Status instruktur wajib dipilih.',
            'status.in' => 'Status instruktur harus aktif atau tidak aktif.',
            'notes.max' => 'Catatan tidak boleh lebih dari 300 karakter.',
        ];
    }
}
