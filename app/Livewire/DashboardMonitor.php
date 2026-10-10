<?php

namespace App\Livewire;

use App\Models\InstructorLeave;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardMonitor extends Component
{
    public string $month = '';

    public string $selectedDate = '';

    public function mount(): void
    {
        $today = CarbonImmutable::now('Asia/Jakarta');
        $this->month = $today->format('Y-m');
        $this->selectedDate = $today->toDateString();
    }

    public function previousMonth(): void
    {
        $this->changeMonth(-1);
    }

    public function nextMonth(): void
    {
        $this->changeMonth(1);
    }

    public function goToToday(): void
    {
        $this->mount();
    }

    public function selectDate(string $date): void
    {
        $parsed = $this->parseDate($date);
        if ($parsed && $parsed->format('Y-m') === $this->visibleMonth()->format('Y-m')) {
            $this->selectedDate = $parsed->toDateString();
        }
    }

    private function changeMonth(int $offset): void
    {
        $target = $this->visibleMonth()->addMonthsNoOverflow($offset);
        $this->month = $target->format('Y-m');
        $this->selectedDate = $target->startOfMonth()->toDateString();
    }

    private function visibleMonth(): CarbonImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) === 1) {
            $date = CarbonImmutable::createFromFormat('!Y-m', $this->month, 'Asia/Jakarta');
            if ($date !== false && $date->format('Y-m') === $this->month) {
                return $date;
            }
        }

        return CarbonImmutable::now('Asia/Jakarta')->startOfMonth();
    }

    private function parseDate(string $date): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Jakarta');
        } catch (\Throwable) {
            return null;
        }

        return $parsed !== false && $parsed->toDateString() === $date ? $parsed : null;
    }

    public function render(): View
    {
        $month = $this->visibleMonth();
        $startOfMonth = $month->startOfMonth();
        $endOfMonth = $month->endOfMonth();
        $date = $this->parseDate($this->selectedDate);
        if (! $date || $date->format('Y-m') !== $month->format('Y-m')) {
            $date = $startOfMonth;
        }

        $countsByDate = Schedule::query()
            ->selectRaw('DATE(date) as schedule_date, count(*) as session_count')
            ->whereDate('date', '>=', $startOfMonth->toDateString())
            ->whereDate('date', '<=', $endOfMonth->toDateString())
            ->groupByRaw('DATE(date)')
            ->pluck('session_count', 'schedule_date')
            ->all();

        $calendarDays = [];
        $calendarStart = $startOfMonth->startOfWeek(CarbonInterface::MONDAY);
        $calendarEnd = $endOfMonth->endOfWeek(CarbonInterface::SUNDAY);
        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        for ($day = $calendarStart; $day->lessThanOrEqualTo($calendarEnd); $day = $day->addDay()) {
            $iso = $day->toDateString();
            $calendarDays[] = [
                'date' => $iso,
                'day' => $day->day,
                'inMonth' => $day->format('Y-m') === $month->format('Y-m'),
                'isToday' => $iso === $today,
                'count' => (int) ($countsByDate[$iso] ?? 0),
            ];
        }

        $daySchedules = Schedule::query()
            ->with(['courseClass:id,name,subject', 'instructor:id,name', 'room:id,name'])
            ->whereDate('date', $date->toDateString())
            ->orderBy('start_time')->orderBy('id')->get();

        $leaveCounts = InstructorLeave::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        $substitutionCounts = ScheduleSubstitution::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        return view('livewire.dashboard-monitor', [
            'calendarDays' => $calendarDays,
            'monthLabel' => $month->locale('id')->translatedFormat('F Y'),
            'selectedDate' => $date->toDateString(),
            'selectedDateLabel' => $date->locale('id')->translatedFormat('d F Y'),
            'daySchedules' => $daySchedules,
            'monthlyScheduleCount' => array_sum(array_map('intval', $countsByDate)),
            'totalSchedules' => Schedule::query()->count(),
            'totalLeaves' => InstructorLeave::query()->count(),
            'leaveCounts' => $leaveCounts,
            'substitutionCounts' => $substitutionCounts,
        ]);
    }
}
