<?php

namespace App\Services\Scheduling;

use App\Models\Competency;
use App\Models\CurriculumVersion;
use App\Models\Facility;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Program;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * G3-01: referensi database untuk penjadwalan kelas baru.
 * Read-only, tanpa reservasi slot dan tanpa ketergantungan AI eksternal.
 */
class MasterDataReferenceService
{
    /**
     * @return array{programs: mixed, curriculum_versions: mixed, competencies: mixed, instructors: mixed, rooms: mixed, facilities: mixed}
     */
    public function forDepartment(
        string $department,
        int $studentCount = 1,
        array $requiredFacilityCodes = []
    ): array {
        Validator::make([
            'department' => $department,
            'student_count' => $studentCount,
            'required_facility_codes' => $requiredFacilityCodes,
        ], [
            'department' => ['required', 'in:computer,speakout'],
            'student_count' => ['required', 'integer', 'min:1'],
            'required_facility_codes' => ['array'],
            'required_facility_codes.*' => ['required', 'string', 'distinct'],
        ])->validate();

        $codes = array_values(array_unique($requiredFacilityCodes));

        $rooms = Room::query()
            ->where('department', $department)
            ->where('status', 'available')
            ->where('capacity', '>=', $studentCount);

        foreach ($codes as $code) {
            $rooms->whereHas('facilities', function ($query) use ($code): void {
                $query->where('facilities.code', $code)
                    ->where('facilities.is_active', true)
                    ->where('room_facilities.quantity', '>', 0)
                    ->where('room_facilities.condition', '!=', 'maintenance');
            });
        }

        return [
            'programs' => Program::query()
                ->where('department', $department)
                ->where('is_active', true)
                ->orderBy('id')->get(),
            'curriculum_versions' => CurriculumVersion::query()
                ->where('status', 'published')
                ->whereHas('program', fn ($query) => $query
                    ->where('department', $department)
                    ->where('is_active', true))
                ->with(['program', 'periods.subjects.competency', 'periods.subjects.modules'])
                ->orderBy('id')->get(),
            'competencies' => Competency::query()
                ->where('department', $department)
                ->where('is_active', true)
                ->orderBy('id')->get(),
            'instructors' => Instructor::query()
                ->where('department', $department)
                ->where('status', 'active')
                ->whereHas('skills', fn ($query) => $query->whereHas('competency', fn ($competency) => $competency
                    ->where('department', $department)
                    ->where('is_active', true)))
                ->with(['skills.competency', 'availabilities'])
                ->orderBy('id')->get(),
            'rooms' => $rooms
                ->with(['facilities' => fn ($query) => $query
                    ->where('facilities.is_active', true)
                    ->where('room_facilities.quantity', '>', 0)
                    ->where('room_facilities.condition', '!=', 'maintenance')])
                ->orderBy('id')->get(),
            'facilities' => Facility::query()
                ->where('is_active', true)
                ->orderBy('code')->get(),
        ];
    }

    /**
     * Read-only, bounded by date. A pending proposal never reserves a slot.
     *
     * @return array{schedules: mixed, leaves: mixed}
     */
    public function blockingState(string $startDate, string $endDate): array
    {
        $validator = Validator::make([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return [
            'schedules' => Schedule::query()
                ->whereIn('status', ['scheduled', 'rescheduled'])
                ->whereDate('date', '>=', $startDate)
                ->whereDate('date', '<=', $endDate)
                ->orderBy('date')->orderBy('start_time')->orderBy('id')->get(),
            // Ikuti kebijakan fail-closed legacy: izin yang belum ditolak tetap diperhitungkan.
            'leaves' => InstructorLeave::query()
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->whereDate('date', '>=', $startDate)
                ->whereDate('date', '<=', $endDate)
                ->orderBy('date')->orderBy('start_time')->orderBy('id')->get(),
        ];
    }
}
