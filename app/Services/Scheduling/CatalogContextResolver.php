<?php

namespace App\Services\Scheduling;

use App\Models\Competency;
use App\Models\Instructor;
use App\Models\Room;
use Illuminate\Support\Collection;

/** Read-only resolver for factual scheduling context from active master data. */
class CatalogContextResolver
{
    /**
     * @return array{status:string,competency:array<string, mixed>|null,matches:list<array<string, mixed>>}
     */
    public function resolveMaterial(string $text): array
    {
        $needle = $this->normalize($text);
        if (mb_strlen($needle) < 3) {
            return ['status' => 'not_found', 'competency' => null, 'matches' => []];
        }

        $scored = $this->activeCompetencies()->map(function (Competency $competency) use ($needle): array {
            $name = $this->normalize($competency->name);
            $nameTokens = array_values(array_filter(explode(' ', $name)));
            $matchedTokens = array_filter($nameTokens, fn (string $token): bool => mb_strlen($token) >= 3
                && preg_match('/(?:^|\s)'.preg_quote($token, '/').'(?:\s|$)/u', $needle) === 1);
            $coverage = $nameTokens === [] ? 0 : count($matchedTokens) / count($nameTokens);
            $containsFullName = $name !== '' && preg_match('/(?:^|\s)'.preg_quote($name, '/').'(?:\s|$)/u', $needle) === 1;
            $hasDistinctiveSingleToken = count($matchedTokens) === 1
                && mb_strlen((string) reset($matchedTokens)) >= 4;

            return [
                'score' => $containsFullName ? 1000 + count($nameTokens) : (int) round($coverage * 100),
                'eligible' => $containsFullName || ($coverage >= 0.5
                    && (count($matchedTokens) >= min(2, count($nameTokens)) || $hasDistinctiveSingleToken)),
                'competency' => $this->competencyData($competency),
            ];
        })->filter(fn (array $candidate): bool => $candidate['eligible'])
            ->sortByDesc('score')
            ->values();

        if ($scored->isEmpty()) {
            return ['status' => 'not_found', 'competency' => null, 'matches' => []];
        }

        $topScore = $scored->first()['score'];
        $matches = $scored->where('score', $topScore)->pluck('competency')->values()->all();

        return count($matches) === 1
            ? ['status' => 'unique', 'competency' => $matches[0], 'matches' => $matches]
            : ['status' => 'ambiguous', 'competency' => null, 'matches' => $matches];
    }

    /** @return list<array{id:int,name:string,department:string}> */
    public function activeCatalog(): array
    {
        return $this->activeCompetencies()
            ->map(fn (Competency $competency): array => $this->competencyData($competency))
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,department:string,skill_level:string,availability:list<array{day:string,start_time:string,end_time:string}>}>
     */
    public function instructorsForCompetency(int $competencyId): array
    {
        return Instructor::query()
            ->where('status', 'active')
            ->whereHas('skills', fn ($query) => $query->where('competency_id', $competencyId))
            ->with([
                'skills' => fn ($query) => $query->where('competency_id', $competencyId),
                'availabilities' => fn ($query) => $query->orderBy('day')->orderBy('start_time'),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Instructor $instructor): array => [
                'id' => (int) $instructor->id,
                'name' => $instructor->name,
                'department' => (string) $instructor->department,
                'skill_level' => (string) ($instructor->skills->first()?->level ?? ''),
                'availability' => $instructor->availabilities->map(fn ($availability): array => [
                    'day' => $availability->day,
                    'start_time' => $availability->start_time,
                    'end_time' => $availability->end_time,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,department:string,capacity:int,facilities:list<array{code:string,name:string}>}>
     */
    public function availableRooms(string $department, int $studentCount): array
    {
        return Room::query()
            ->where('department', $department)
            ->where('status', 'available')
            ->where('capacity', '>=', max(1, $studentCount))
            ->with(['facilities' => fn ($query) => $query
                ->where('facilities.is_active', true)
                ->where('room_facilities.quantity', '>', 0)
                ->where('room_facilities.condition', '!=', 'maintenance')])
            ->orderBy('id')
            ->get()
            ->map(fn (Room $room): array => [
                'id' => (int) $room->id,
                'name' => $room->name,
                'department' => (string) $room->department,
                'capacity' => (int) $room->capacity,
                'facilities' => $room->facilities->map(fn ($facility): array => [
                    'code' => $facility->code,
                    'name' => $facility->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, Competency> */
    private function activeCompetencies(): Collection
    {
        return Competency::query()->active()->orderBy('name')->orderBy('id')->get(['id', 'name', 'department']);
    }

    /** @return array{id:int,name:string,department:string} */
    private function competencyData(Competency $competency): array
    {
        return [
            'id' => (int) $competency->id,
            'name' => $competency->name,
            'department' => $competency->department,
        ];
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($value)));
    }
}
