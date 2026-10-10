<div class="flex flex-col gap-6">
    {{-- Header & Selector Section --}}
    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-1">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-[#1565D8] dark:bg-blue-950/60 dark:text-blue-300">
                    <span class="size-1.5 rounded-full bg-[#1565D8] dark:bg-blue-400"></span>
                    {{ __('Scheduling Engine Tahap 1 - Algoritma Deterministik') }}
                </span>
                <h2 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    {{ __('Pilih Pengajuan Izin Instruktur') }}
                </h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Pilih instruktur yang mengajukan izin untuk menjalankan analisis bentrok jadwal dan pencocokan kandidat pengganti.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-[240px]">
                    <select
                        wire:model="selectedLeaveId"
                        wire:change="runScheduler"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm font-medium text-zinc-800 shadow-xs focus:border-[#1565D8] focus:outline-none focus:ring-2 focus:ring-[#1565D8]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    >
                        @foreach ($leaves as $leave)
                            <option value="{{ $leave->id }}" wire:key="leave-opt-{{ $leave->id }}">
                                {{ $leave->instructor->name }} &bull; {{ $leave->date instanceof \DateTimeInterface ? $leave->date->format('d M Y') : $leave->date }} ({{ substr($leave->start_time, 0, 5) }} - {{ substr($leave->end_time, 0, 5) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <flux:button
                    wire:click="runScheduler"
                    variant="primary"
                    icon="arrow-path"
                    class="bg-[#1565D8] hover:bg-[#0A3D91] text-white"
                >
                    <span wire:loading.remove wire:target="runScheduler">{{ __('Jalankan Engine') }}</span>
                    <span wire:loading wire:target="runScheduler">{{ __('Menganalisis...') }}</span>
                </flux:button>

                <flux:button
                    type="button"
                    wire:click="analyzeWithAi"
                    variant="outline"
                    icon="sparkles"
                    class="border-blue-300 text-[#1565D8] hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-950/40"
                    wire:loading.attr="disabled"
                    wire:target="analyzeWithAi"
                >
                    <span wire:loading.remove wire:target="analyzeWithAi">{{ __('Analisis dengan Gemini AI') }}</span>
                    <span wire:loading wire:target="analyzeWithAi">{{ __('Menganalisis...') }}</span>
                </flux:button>
            </div>
        </div>

        {{-- Quick Demo Badges --}}
        @if ($leaves->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Skenario Cepat:') }}</span>
                @foreach ($leaves as $leave)
                    <button
                        type="button"
                        wire:click="selectLeave({{ $leave->id }})"
                        wire:key="quick-leave-{{ $leave->id }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium transition-all {{ $selectedLeaveId === $leave->id ? 'border-[#1565D8] bg-blue-50/70 text-[#1565D8] dark:border-blue-500 dark:bg-blue-950/40 dark:text-blue-300' : 'border-zinc-200 bg-zinc-50 text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}"
                    >
                        <flux:icon name="user" class="size-3.5" />
                        <span>{{ $leave->instructor->name }} ({{ substr($leave->start_time, 0, 5) }}-{{ substr($leave->end_time, 0, 5) }})</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Pesan Notifikasi / Feedback --}}
    @if ($feedbackMessage)
        <div class="rounded-xl border {{ ($result['ai_summary']['fallback_used'] ?? false) ? 'border-amber-200 bg-amber-50/70 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200' : 'border-blue-200 bg-blue-50/70 text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200' }} p-3.5 text-xs font-medium">
            <div class="flex items-center gap-2">
                <flux:icon name="{{ ($result['ai_summary']['fallback_used'] ?? false) ? 'exclamation-circle' : 'sparkles' }}" class="size-4 shrink-0" />
                <span>{{ $feedbackMessage }}</span>
            </div>
        </div>
    @endif

    {{-- Hasil Evaluasi Scheduler --}}
    @if ($result)
        {{-- Ringkasan Status Banner --}}
        <div class="rounded-2xl border p-6 shadow-xs {{ $result['all_schedules_resolved'] ? 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-900/40 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/70 dark:border-amber-900/40 dark:bg-amber-950/20' }}">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $result['all_schedules_resolved'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300' }}">
                        @if ($result['all_schedules_resolved'])
                            <flux:icon name="check-circle" class="size-6" />
                        @else
                            <flux:icon name="exclamation-triangle" class="size-6" />
                        @endif
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-bold text-zinc-900 dark:text-white">
                                {{ $result['instructor_name'] }} &bull; {{ __('Pengajuan Izin Teranalisis') }}
                            </h3>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $result['all_schedules_resolved'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' }}">
                                {{ $result['all_schedules_resolved'] ? __('Status: Semua Teratasi') : __('Status: Perlu Tindak Lanjut') }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $result['summary'] }}
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="inline-flex items-center gap-1">
                                <flux:icon name="calendar" class="size-3.5" />
                                {{ $result['leave_date'] }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <flux:icon name="clock" class="size-3.5" />
                                {{ substr($result['leave_start_time'], 0, 5) }} - {{ substr($result['leave_end_time'], 0, 5) }} WIB
                            </span>
                            @if (! empty($result['leave_reason']))
                                <span class="inline-flex items-center gap-1 italic">
                                    <flux:icon name="chat-bubble-bottom-center-text" class="size-3.5" />
                                    "{{ $result['leave_reason'] }}"
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Metrik Ringkas --}}
                <div class="flex items-center gap-6 border-t border-zinc-200 pt-4 sm:border-t-0 sm:pt-0 dark:border-zinc-800">
                    <div class="text-center">
                        <div class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                            {{ $result['total_affected_schedules'] }}
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Kelas Terdampak') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                            {{ $result['total_resolved_schedules'] }}
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Kelas Terselesaikan') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Daftar Kelas yang Terdampak --}}
        <div class="space-y-4">
            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">
                {{ __('Rincian Kelas Terdampak & Rekomendasi Instruktur Pengganti') }} ({{ count($result['affected_schedules']) }})
            </h3>

            @forelse ($result['affected_schedules'] as $index => $schedule)
                <div
                    wire:key="affected-sched-{{ $schedule['schedule_id'] }}"
                    class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900"
                >
                    {{-- Header Kartu Kelas --}}
                    <div class="flex flex-col gap-3 border-b border-zinc-100 bg-zinc-50/80 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800 dark:bg-zinc-800/40">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="flex size-6 items-center justify-center rounded-full bg-[#1565D8] text-xs font-bold text-white">
                                    {{ $index + 1 }}
                                </span>
                                <h4 class="text-base font-bold text-zinc-900 dark:text-white">
                                    {{ $schedule['class_name'] }}
                                </h4>
                                <span class="rounded-lg bg-blue-100 px-2 py-0.5 text-xs font-semibold text-[#1565D8] dark:bg-blue-950 dark:text-blue-300">
                                    {{ $schedule['subject'] }}
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                <span class="inline-flex items-center gap-1 font-medium">
                                    <flux:icon name="clock" class="size-3.5" />
                                    {{ substr($schedule['start_time'], 0, 5) }} - {{ substr($schedule['end_time'], 0, 5) }} WIB
                                </span>
                                @if (isset($schedule['room']))
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon name="building-office-2" class="size-3.5" />
                                        {{ $schedule['room']['room_name'] }} (Kapasitas: {{ $schedule['room']['capacity'] }} | Siswa: {{ $schedule['room']['student_count'] }})
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div>
                            @if ($schedule['status'] === 'resolved')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-400">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    {{ count($schedule['valid_candidates']) }} {{ __('Kandidat Valid & Ruangan Siap') }}
                                </span>
                            @elseif ($schedule['status'] === 'room_issue')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/50 dark:text-amber-400">
                                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                                    {{ __('Masalah Ruangan') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-950/50 dark:text-rose-400">
                                    <span class="size-1.5 rounded-full bg-rose-500"></span>
                                    {{ __('Belum Ada Pengganti') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="p-6 space-y-6">
                        {{-- Peringatan Cross-Schedule / Alokasi --}}
                        @if (! empty($schedule['warnings']))
                            <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3.5 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200 space-y-1">
                                @foreach ($schedule['warnings'] as $warning)
                                    <div class="flex items-center gap-2 font-medium">
                                        <flux:icon name="exclamation-triangle" class="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                        <span>{{ $warning }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Pemeriksaan Ruangan --}}
                        @if (isset($schedule['room']))
                            <div class="rounded-xl border p-3.5 text-xs {{ $schedule['room']['is_valid'] ? 'border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-800/30' : ($schedule['room']['has_usable_room'] ? 'border-blue-200 bg-blue-50/70 text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200' : 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200') }}">
                                <div class="flex items-center gap-2 font-medium">
                                    <flux:icon name="{{ $schedule['room']['is_valid'] ? 'check-circle' : ($schedule['room']['has_usable_room'] ? 'arrow-path' : 'exclamation-circle') }}" class="size-4 {{ $schedule['room']['is_valid'] ? 'text-emerald-600 dark:text-emerald-400' : ($schedule['room']['has_usable_room'] ? 'text-[#1565D8] dark:text-blue-400' : 'text-rose-600 dark:text-rose-400') }}" />
                                    <span>{{ __('Status Ruangan:') }} {{ $schedule['room']['notes'] }}</span>
                                </div>
                            </div>
                        @endif

                        {{-- Penjelasan Gemini 3.5 Flash-Lite (Jika Ada) --}}
                        @if (! empty($schedule['ai_recommendation']['summary_explanation']))
                            <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 text-xs dark:border-indigo-900/50 dark:bg-indigo-950/30">
                                <div class="flex items-center justify-between border-b border-indigo-100 pb-2 dark:border-indigo-900/50">
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="sparkles" class="size-4 text-indigo-600 dark:text-indigo-400" />
                                        <span class="font-bold text-indigo-950 dark:text-indigo-200">
                                            {{ __('Penjelasan Gemini 3.5 Flash-Lite') }}
                                        </span>
                                    </div>
                                    @if ($schedule['ai_recommendation']['fallback_used'] ?? false)
                                        <span class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                            {{ __('Mode Fallback') }}
                                        </span>
                                    @else
                                        <span class="rounded bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                            {{ __('AI Active') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-2 leading-relaxed text-indigo-950 dark:text-indigo-200">
                                    {{ $schedule['ai_recommendation']['summary_explanation'] }}
                                </p>
                            </div>
                        @endif

                        {{-- Rekomendasi Kandidat Valid --}}
                        @if (! empty($schedule['valid_candidates']))
                            <div class="space-y-3">
                                <h5 class="text-xs font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ __('Kandidat Pengganti yang Memenuhi Syarat:') }}
                                </h5>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    @foreach ($schedule['valid_candidates'] as $rank => $candidate)
                                        <div
                                            wire:key="valid-cand-{{ $schedule['schedule_id'] }}-{{ $candidate['instructor_id'] }}"
                                            class="relative flex flex-col justify-between rounded-xl border p-4 transition-all {{ $rank === 0 ? 'border-[#1565D8] bg-blue-50/30 ring-1 ring-[#1565D8]/20 dark:border-blue-500 dark:bg-blue-950/20' : 'border-zinc-200 bg-white hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900' }}"
                                        >
                                            <div class="space-y-2">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div>
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-sm font-bold text-zinc-900 dark:text-white">
                                                                {{ $candidate['instructor_name'] }}
                                                            </span>
                                                            @if ($rank === 0)
                                                                <span class="rounded-full bg-[#FFC928] px-2 py-0.5 text-[10px] font-bold text-[#0A3D91]">
                                                                    {{ __('Rekomendasi Utama') }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                            <span class="rounded bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                                                {{ $candidate['skill_name'] }} ({{ ucfirst($candidate['skill_level'] ?? '') }})
                                                            </span>
                                                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                                {{ __('Skor:') }} {{ $candidate['score'] }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="text-right">
                                                        <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                                            #{{ $rank + 1 }}
                                                        </span>
                                                    </div>
                                                </div>

                                                {{-- Alasan Penilaian --}}
                                                <ul class="space-y-1 border-t border-zinc-100 pt-2 text-xs text-zinc-600 dark:border-zinc-800 dark:text-zinc-300">
                                                    @foreach ($candidate['reasons'] as $reason)
                                                        <li class="flex items-start gap-1.5">
                                                            <flux:icon name="check" class="mt-0.5 size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                                            <span>{{ $reason }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            {{-- Jika tidak ada pengganti --}}
                            <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-4 text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-200">
                                <div class="flex items-center gap-3">
                                    <flux:icon name="x-circle" class="size-5 shrink-0 text-rose-600 dark:text-rose-400" />
                                    <div>
                                        <h5 class="text-sm font-semibold">{{ __('Belum Ada Instruktur Pengganti yang Memenuhi Syarat') }}</h5>
                                        <p class="text-xs mt-0.5">{{ $schedule['summary'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Kandidat yang Didiskualifikasi (Transparansi Alasan) --}}
                        @if (! empty($schedule['disqualified_candidates']))
                            <div x-data="{ expanded: false }" class="border-t border-zinc-100 pt-4 dark:border-zinc-800">
                                <button
                                    type="button"
                                    @click="expanded = ! expanded"
                                    class="flex items-center justify-between w-full text-xs font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                                >
                                    <span class="inline-flex items-center gap-1.5">
                                        <flux:icon name="information-circle" class="size-4" />
                                        <span>{{ __('Transparansi: Lihat ') }} {{ count($schedule['disqualified_candidates']) }} {{ __(' kandidat lain yang tidak memenuhi syarat beserta alasannya') }}</span>
                                    </span>
                                    <span x-text="expanded ? '{{ __('Sembunyikan') }}' : '{{ __('Tampilkan') }}'" class="text-[#1565D8] dark:text-blue-400 font-semibold"></span>
                                </button>

                                <div x-show="expanded" x-collapse class="mt-3 space-y-2">
                                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                                        <table class="min-w-full divide-y divide-zinc-200 text-left text-xs dark:divide-zinc-800">
                                            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                                                <tr>
                                                    <th class="px-3.5 py-2 font-medium text-zinc-600 dark:text-zinc-400">{{ __('Instruktur') }}</th>
                                                    <th class="px-3.5 py-2 font-medium text-zinc-600 dark:text-zinc-400">{{ __('Kesesuaian Kompetensi') }}</th>
                                                    <th class="px-3.5 py-2 font-medium text-zinc-600 dark:text-zinc-400">{{ __('Jadwal') }}</th>
                                                    <th class="px-3.5 py-2 font-medium text-zinc-600 dark:text-zinc-400">{{ __('Alasan Didiskualifikasi') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800/50 dark:bg-zinc-900">
                                                @foreach ($schedule['disqualified_candidates'] as $discCandidate)
                                                    <tr wire:key="disc-cand-{{ $schedule['schedule_id'] }}-{{ $discCandidate['instructor_id'] }}">
                                                        <td class="px-3.5 py-2 font-semibold text-zinc-800 dark:text-zinc-200">
                                                            {{ $discCandidate['instructor_name'] }}
                                                        </td>
                                                        <td class="px-3.5 py-2">
                                                            @if ($discCandidate['competency_matched'])
                                                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">{{ __('Cocok') }}</span>
                                                            @else
                                                                <span class="text-zinc-400 dark:text-zinc-500">{{ __('Tidak Cocok') }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3.5 py-2">
                                                            @if ($discCandidate['has_schedule_conflict'])
                                                                <span class="text-rose-600 dark:text-rose-400 font-medium">{{ __('Bentrok') }}</span>
                                                            @else
                                                                <span class="text-emerald-600 dark:text-emerald-400">{{ __('Bebas') }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3.5 py-2 text-zinc-500 dark:text-zinc-400">
                                                            {{ $discCandidate['disqualification_reason'] }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Tidak ada jadwal kelas yang terdampak pada rentang waktu izin ini.') }}
                    </p>
                </div>
            @endforelse
        </div>

        {{-- Gemini Recommendation Layer --}}
        <section aria-labelledby="gemini-recommendation-heading" class="overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-xs dark:border-blue-900 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 border-b border-blue-100 bg-blue-50/50 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-blue-900/40 dark:bg-blue-950/20">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#1565D8] text-white">
                        <flux:icon name="sparkles" class="size-5" />
                    </span>
                    <div class="min-w-0 space-y-1">
                        <h3 id="gemini-recommendation-heading" class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Analisis Gemini AI') }}</h3>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Lapisan analisis tambahan, terpisah dari Scheduling Engine deterministik.') }}</p>
                    </div>
                </div>
                @if (! empty($result['ai_summary']['fallback_used']))
                    <span class="inline-flex w-fit shrink-0 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300">{{ __('Mode Fallback Aktif') }}</span>
                @elseif (! empty($result['ai_summary']['is_ai_generated']))
                    <span class="inline-flex w-fit shrink-0 rounded-full border border-blue-300 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-300">{{ __('Gemini 3.5 Flash-Lite') }}</span>
                @else
                    <span class="inline-flex w-fit shrink-0 rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">{{ __('Menunggu Permintaan Admin') }}</span>
                @endif
            </div>

            <div class="space-y-4 p-5 sm:p-6">
                @if (! empty($result['ai_summary']))
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ ($result['ai_summary']['fallback_used'] ?? false) ? __('Ringkasan Sistem (Fallback):') : __('Ringkasan Eksekutif Gemini AI:') }}
                        </h4>
                        <p class="text-sm leading-relaxed text-zinc-800 dark:text-zinc-200">
                            {{ $result['ai_summary']['executive_summary'] }}
                        </p>
                    </div>

                    @php
                        $aiSchedules = collect($result['affected_schedules'] ?? [])
                            ->filter(fn ($s) => ! empty($s['ai_recommendation']['rankings']) || ! empty($s['ai_recommendation']['summary_explanation']));
                    @endphp

                    @if ($aiSchedules->isNotEmpty())
                        <div class="space-y-3 pt-2">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Penjelasan & Peringkat Rekomendasi per Kelas:') }}</h4>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($aiSchedules as $sched)
                                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 text-xs dark:border-zinc-800 dark:bg-zinc-800/40" wire:key="ai-summary-card-{{ $sched['schedule_id'] }}">
                                        <div class="flex items-center justify-between border-b border-zinc-200 pb-2 dark:border-zinc-700">
                                            <span class="font-bold text-zinc-900 dark:text-white">{{ $sched['class_name'] }} ({{ $sched['subject'] }})</span>
                                            <span class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ substr($sched['start_time'], 0, 5) }} - {{ substr($sched['end_time'], 0, 5) }}</span>
                                        </div>
                                        <p class="mt-2 text-zinc-600 dark:text-zinc-300 leading-relaxed">
                                            {{ $sched['ai_recommendation']['summary_explanation'] }}
                                        </p>
                                        @if (! empty($sched['ai_recommendation']['rankings']))
                                            <div class="mt-3 space-y-1.5 border-t border-zinc-200/60 pt-2 dark:border-zinc-700/60">
                                                @foreach ($sched['ai_recommendation']['rankings'] as $aiRank)
                                                    <div class="flex items-start justify-between gap-2 text-[11px]">
                                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">
                                                            #{{ $aiRank['rank'] }}. {{ $aiRank['instructor_name'] }}
                                                            <span class="block text-zinc-500 dark:text-zinc-400 font-normal">{{ $aiRank['ai_reasoning'] }}</span>
                                                        </span>
                                                        <span class="shrink-0 font-semibold text-[#1565D8] dark:text-blue-400">
                                                            {{ $aiRank['confidence_score'] }}%
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Belum ada analisis AI untuk pengajuan ini.') }}</p>
                    <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Klik tombol "Analisis dengan Gemini AI" di atas untuk meminta Gemini 3.5 Flash-Lite menilai kandidat yang telah lolos pemeriksaan kompetensi, bentrok jadwal, dan ruangan.') }}</p>
                @endif

                <div class="flex items-start gap-2 rounded-lg bg-zinc-50 p-3 text-xs leading-5 text-zinc-600 dark:bg-zinc-800/50 dark:text-zinc-300">
                    <flux:icon name="shield-check" class="mt-0.5 size-4 shrink-0 text-[#1565D8] dark:text-blue-300" />
                    <p>{{ __('Gemini hanya memberi rekomendasi, tidak mengubah jadwal otomatis. Jika API gagal, hasil Scheduling Engine tetap digunakan.') }}</p>
                </div>
            </div>
        </section>
    @endif
</div>
