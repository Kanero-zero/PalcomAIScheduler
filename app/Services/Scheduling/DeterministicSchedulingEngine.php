<?php

namespace App\Services\Scheduling;

use App\Models\CurriculumVersion;
use Illuminate\Support\Carbon;

/**
 * G3-03: read-only alternative generator. This is NOT a reservation or an approval.
 * G4 must independently revalidate and lock all resources before publication.
 */
class DeterministicSchedulingEngine
{
    private const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

    private const MAX_CANDIDATES = 600;

    public function __construct(
        private SharedDraftNormalizationService $normalizer,
        private MasterDataReferenceService $references,
    ) {}

    /**
     * @return array{alternatives_count:int, options:array, reasons:array, search_exhausted:bool}
     */
    public function generate(array $input): array
    {
        $draft = $this->normalizer->normalize($input);
        $subjects = $this->subjectPlan($draft);
        if (isset($subjects['error'])) {
            return $this->emptyResult([$subjects['error']]);
        }

        $refs = $this->references->forDepartment(
            $draft['department'], $draft['student_count'], $draft['required_facility_codes']
        );
        $rooms = $refs['rooms'];
        $instructors = $refs['instructors'];

        if ($draft['preferred_room_id'] !== null) {
            $rooms = $rooms->where('id', $draft['preferred_room_id']);
        }
        if ($draft['preferred_instructor_id'] !== null) {
            $instructors = $instructors->where('id', $draft['preferred_instructor_id']);
        }
        if ($rooms->isEmpty()) {
            return $this->emptyResult(['Tidak ada ruangan aktif yang sesuai bidang, kapasitas, fasilitas, dan preferensi.']);
        }
        if ($instructors->isEmpty()) {
            return $this->emptyResult(['Tidak ada instruktur aktif dengan kompetensi terdaftar sesuai bidang dan preferensi.']);
        }

        $state = $this->references->blockingState($draft['start_date'], $draft['end_date']);
        $schedules = $state['schedules']->groupBy(fn ($row) => $row->date->format('Y-m-d'));
        $leaves = $state['leaves']->groupBy(fn ($row) => $row->date->format('Y-m-d'));
        $patterns = $this->weekdayPatterns($draft['preferred_weekdays'], $draft['frequency_per_week']);
        $times = $this->candidateTimes($draft['preferred_time_window'], $draft['session_duration_minutes']);
        $rejected = [];
        $candidates = [];
        $signatures = [];
        $attempts = 0;
        $exhausted = false;
        $atLeastOneCalendar = false;

        foreach ($patterns as $pattern) {
            $dates = $this->datesForPattern($draft, $pattern);
            if (count($dates) !== $draft['total_meetings']) {
                continue;
            }
            $atLeastOneCalendar = true;

            foreach ($times as $startTime) {
                $endTime = $this->formatMinute($this->minutes($startTime) + $draft['session_duration_minutes']);
                foreach ($rooms->sortBy('id') as $room) {
                    foreach ([0, 1, 2] as $rotation) {
                        if (++$attempts > self::MAX_CANDIDATES) {
                            $exhausted = true;
                            break 4;
                        }

                        $slots = [];
                        $levels = [];
                        $failure = null;
                        foreach ($dates as $index => $date) {
                            $day = self::DAYS[Carbon::parse($date, 'Asia/Jakarta')->dayOfWeekIso];
                            $subject = $subjects[$index];
                            if ($this->overlapsResource($schedules->get($date, collect()), 'room_id', $room->id, $startTime, $endTime)) {
                                $failure = 'Bentrok dengan jadwal resmi ruangan pada salah satu pertemuan.';
                                break;
                            }

                            $qualified = [];
                            foreach ($instructors as $instructor) {
                                $level = $this->competencyLevel($instructor, array_values(array_unique(array_merge(
                                    $draft['required_competency_ids'], $subject['competency_ids']
                                ))), $draft['department']);
                                if ($level === null) {
                                    continue;
                                }
                                if (! $this->hasAvailability($instructor, $day, $startTime, $endTime)) {
                                    continue;
                                }
                                if ($this->overlapsResource($schedules->get($date, collect()), 'instructor_id', $instructor->id, $startTime, $endTime)) {
                                    continue;
                                }
                                if ($this->overlapsResource($leaves->get($date, collect()), 'instructor_id', $instructor->id, $startTime, $endTime)) {
                                    continue;
                                }
                                $qualified[] = ['instructor' => $instructor, 'level' => $level];
                            }
                            usort($qualified, fn ($a, $b) => ($b['level'] <=> $a['level'])
                                ?: ($a['instructor']->id <=> $b['instructor']->id));
                            if ($qualified === []) {
                                $failure = 'Tidak ada instruktur kompeten dan tersedia, atau terdapat jadwal resmi/izin pada salah satu pertemuan.';
                                break;
                            }
                            $chosen = $qualified[$rotation % count($qualified)];
                            $levels[] = $chosen['level'];
                            $slots[] = [
                                'meeting_sequence' => $index + 1,
                                'date' => $date,
                                'day_name' => $day,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'room_id' => (int) $room->id,
                                'room_name' => $room->name,
                                'instructor_id' => (int) $chosen['instructor']->id,
                                'instructor_name' => $chosen['instructor']->name,
                                'curriculum_subject_id' => $subject['id'],
                                'subject_name' => $subject['name'],
                            ];
                        }
                        if ($failure !== null) {
                            $rejected[$failure] = ($rejected[$failure] ?? 0) + 1;

                            continue;
                        }

                        $signature = json_encode(array_map(fn ($slot) => [
                            $slot['date'], $slot['start_time'], $slot['room_id'],
                            $slot['instructor_id'], $slot['curriculum_subject_id'],
                        ], $slots), JSON_THROW_ON_ERROR);
                        if (isset($signatures[$signature])) {
                            continue;
                        }
                        $signatures[$signature] = true;

                        $timePenalty = intdiv($this->minutes($startTime) - $this->minutes($times[0]), 30) * 3;
                        $seniorityBonus = (int) floor(array_sum($levels) / count($levels));
                        $sameInstructor = count(array_unique(array_column($slots, 'instructor_id'))) === 1;
                        $consistencyBonus = ($sameInstructor ? 2 : 0) + 2; // same room across ALL meetings
                        $score = max(0, min(100, 100 - $timePenalty + $seniorityBonus + $consistencyBonus));

                        $candidates[] = [
                            'score' => $score,
                            'conflicts_count' => 0,
                            'explanation' => 'Seluruh '.count($slots).' pertemuan bebas bentrok pada '.
                                $room->name.' ('.$startTime.'–'.$endTime.' WIB); '.
                                ($sameInstructor ? 'instruktur konsisten.' : 'instruktur sesuai kompetensi tiap materi.'),
                            'proposed_schedules_payload' => $slots,
                            '_tie' => sprintf('%s:%05d:%05d:%s', $startTime, $room->id, $slots[0]['instructor_id'], $signature),
                        ];
                    }
                }
            }
        }

        usort($candidates, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['_tie'], $b['_tie']));
        $options = [];
        foreach (array_slice($candidates, 0, 3) as $index => $candidate) {
            unset($candidate['_tie']);
            $candidate['option_label'] = ['A', 'B', 'C'][$index];
            $options[] = $candidate;
        }

        $reasons = [];
        if ($options === []) {
            if (! $atLeastOneCalendar) {
                $reasons[] = 'Tanggal, pilihan hari, dan frekuensi tidak cukup untuk seluruh '.$draft['total_meetings'].' pertemuan.';
            } else {
                $reasons = array_keys($rejected) ?: ['Tidak ada kombinasi ruang, jam, dan instruktur yang memenuhi semua constraint.'];
            }
        }
        if ($exhausted) {
            $reasons[] = 'Batas pencarian kombinasi tercapai; opsi lain mungkin ada dan belum diperiksa.';
        }

        return [
            'alternatives_count' => count($options),
            'options' => $options,
            'reasons' => $reasons,
            'search_exhausted' => $exhausted,
        ];
    }

    /** @return list<array{id:?int,name:string,competency_ids:list<int>}>|array{error:string} */
    private function subjectPlan(array $draft): array
    {
        $total = $draft['total_meetings'];
        if ($draft['curriculum_version_id'] === null) {
            if ($draft['required_competency_ids'] === []) {
                return ['error' => 'Wajib memilih kompetensi atau kurikulum published; materi tanpa kompetensi tidak dijadwalkan.'];
            }

            return array_fill(0, $total, ['id' => null, 'name' => $draft['title'], 'competency_ids' => []]);
        }

        $version = CurriculumVersion::with('periods.subjects.modules', 'periods.subjects.competency')
            ->find($draft['curriculum_version_id']);
        if (! $version || $version->status !== 'published') {
            return ['error' => 'Kurikulum tidak ditemukan atau tidak published.'];
        }
        $plan = [];
        foreach ($version->periods as $period) {
            foreach ($period->subjects as $subject) {
                if (! $subject->competency || ! $subject->competency->is_active
                    || $subject->competency->department !== $draft['department']) {
                    return ['error' => 'Subjek kurikulum tidak memiliki kompetensi aktif sesuai bidang.'];
                }
                if ($subject->modules->isEmpty()) {
                    return ['error' => 'Subjek kurikulum belum memiliki alokasi modul/pertemuan resmi.'];
                }
                foreach ($subject->modules as $module) {
                    if ((int) $module->target_completed_meetings <= 0) {
                        return ['error' => 'Target pertemuan modul kurikulum tidak valid.'];
                    }
                    for ($i = 0; $i < $module->target_completed_meetings; $i++) {
                        $plan[] = ['id' => (int) $subject->id, 'name' => $subject->name,
                            'competency_ids' => [(int) $subject->competency_id]];
                    }
                }
            }
        }
        if (count($plan) !== $total) {
            return ['error' => 'Alokasi modul kurikulum ('.count($plan).') tidak sama dengan total pertemuan yang diminta ('.$total.'). Perlu keputusan kurikulum, bukan pengulangan otomatis.'];
        }

        return $plan;
    }

    /** @return list<list<string>> */
    private function weekdayPatterns(array $days, int $frequency): array
    {
        $result = [[]];
        foreach ($days as $day) {
            $next = [];
            foreach ($result as $item) {
                $next[] = $item;
                if (count($item) < $frequency) {
                    $next[] = [...$item, $day];
                }
            }
            $result = $next;
        }

        return array_values(array_filter($result, fn ($item) => count($item) === $frequency));
    }

    /** @return list<string> */
    private function datesForPattern(array $draft, array $days): array
    {
        $dates = [];
        $date = Carbon::createFromFormat('!Y-m-d', $draft['start_date'], 'Asia/Jakarta');
        $end = Carbon::createFromFormat('!Y-m-d', $draft['end_date'], 'Asia/Jakarta');
        while ($date->lte($end) && count($dates) < $draft['total_meetings']) {
            $day = self::DAYS[$date->dayOfWeekIso] ?? null;
            if (in_array($day, $days, true)) {
                $dates[] = $date->format('Y-m-d');
            }
            $date->addDay();
        }

        return $dates;
    }

    /** @return list<string> */
    private function candidateTimes(array $window, int $duration): array
    {
        $start = $this->minutes($window['start']);
        $lastStart = $this->minutes($window['end']) - $duration;
        $times = [];
        for ($minute = $start; $minute <= $lastStart; $minute += 30) {
            $times[] = $this->formatMinute($minute);
        }
        if ($times !== [] && $this->minutes(end($times)) !== $lastStart) {
            $times[] = $this->formatMinute($lastStart);
        }

        return $times;
    }

    private function competencyLevel($instructor, array $competencies, string $department): ?int
    {
        if ($competencies === []) {
            return null;
        }
        $scores = [];
        foreach ($competencies as $competencyId) {
            $skills = $instructor->skills->filter(fn ($skill) => (int) $skill->competency_id === $competencyId
                && $skill->competency && $skill->competency->is_active
                && $skill->competency->department === $department);
            if ($skills->isEmpty()) {
                return null;
            }
            $scores[] = $skills->map(fn ($skill) => match (strtolower($skill->level)) {
                'advanced' => 3, 'intermediate' => 2, 'beginner' => 1, default => 0,
            })->max();
        }

        return min($scores);
    }

    private function hasAvailability($instructor, string $day, string $start, string $end): bool
    {
        // Missing availability is unknown, not permission to book.
        return $instructor->availabilities->contains(function ($slot) use ($day, $start, $end): bool {
            $stored = mb_strtolower(trim((string) $slot->day));
            $match = $stored === mb_strtolower($day) || $stored === match ($day) {
                'Senin' => 'monday', 'Selasa' => 'tuesday', 'Rabu' => 'wednesday',
                'Kamis' => 'thursday', 'Jumat' => 'friday', 'Sabtu' => 'saturday', default => '-',
            };

            return $match && substr($slot->start_time, 0, 5) <= substr($start, 0, 5)
                && substr($slot->end_time, 0, 5) >= substr($end, 0, 5);
        });
    }

    private function overlapsResource($rows, string $column, int $resourceId, string $start, string $end): bool
    {
        return $rows->contains(fn ($row) => (int) $row->{$column} === $resourceId
            && substr($row->start_time, 0, 8) < $end && substr($row->end_time, 0, 8) > $start);
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $h * 60 + $m;
    }

    private function formatMinute(int $minute): string
    {
        return sprintf('%02d:%02d:00', intdiv($minute, 60), $minute % 60);
    }

    /** @return array{alternatives_count:int,options:array,reasons:array,search_exhausted:bool} */
    private function emptyResult(array $reasons): array
    {
        return ['alternatives_count' => 0, 'options' => [], 'reasons' => $reasons, 'search_exhausted' => false];
    }
}
