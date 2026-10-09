<div class="flex flex-col gap-6">
    {{-- Notifikasi Feedback / Status --}}
    @if ($feedbackMessage)
        <div class="rounded-xl border p-4 text-sm font-medium transition-all {{ ($schedulingResult['all_schedules_resolved'] ?? false) || ($schedulingResult['total_affected_schedules'] ?? 0) === 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300' : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300' }}">
            <div class="flex items-start gap-3">
                <flux:icon name="{{ ($schedulingResult['all_schedules_resolved'] ?? false) || ($schedulingResult['total_affected_schedules'] ?? 0) === 0 ? 'check-circle' : 'exclamation-circle' }}" class="size-5 shrink-0 mt-0.5" />
                <div class="flex-1">
                    <p>{{ $feedbackMessage }}</p>
                    @if ($submittedLeaveId)
                        <p class="mt-1 text-xs opacity-80">{{ __('ID Pengajuan: #') }}{{ $submittedLeaveId }} &bull; {{ __('Status: pending (menunggu persetujuan admin)') }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Formulir Pengajuan Izin Instruktur --}}
    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mb-5 flex flex-col gap-1 border-b border-zinc-100 pb-4 dark:border-zinc-800">
            <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                {{ __('Form Pengajuan Izin & Penjadwalan Otomatis') }}
            </h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Input data izin instruktur untuk mendeteksi kelas terdampak dan menghasilkan rekomendasi pengganti secara deterministik.') }}
            </p>
        </div>

        <form wire:submit="submitLeave" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {{-- Dropdown Pilihan Instruktur --}}
                <div class="sm:col-span-2">
                    <label for="instructor_id" class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('Instruktur') }} <span class="text-rose-500">*</span>
                    </label>
                    <select
                        id="instructor_id"
                        wire:model="form.instructor_id"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm font-medium text-zinc-800 shadow-xs focus:border-[#1565D8] focus:outline-none focus:ring-2 focus:ring-[#1565D8]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    >
                        <option value="">{{ __('-- Pilih Instruktur yang Mengajukan Izin --') }}</option>
                        @foreach ($instructors as $inst)
                            <option value="{{ $inst->id }}" wire:key="inst-opt-{{ $inst->id }}">
                                {{ $inst->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('form.instructor_id')
                        <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tanggal Izin --}}
                <div class="sm:col-span-2">
                    <label for="leave_date" class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('Tanggal Izin') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="leave_date"
                        type="date"
                        wire:model="form.date"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm text-zinc-800 shadow-xs focus:border-[#1565D8] focus:outline-none focus:ring-2 focus:ring-[#1565D8]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    />
                    @error('form.date')
                        <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Jam Mulai --}}
                <div>
                    <label for="start_time" class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('Jam Mulai') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="start_time"
                        type="time"
                        wire:model="form.start_time"
                        placeholder="13:00"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm text-zinc-800 shadow-xs focus:border-[#1565D8] focus:outline-none focus:ring-2 focus:ring-[#1565D8]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    />
                    @error('form.start_time')
                        <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Jam Selesai --}}
                <div>
                    <label for="end_time" class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('Jam Selesai') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="end_time"
                        type="time"
                        wire:model="form.end_time"
                        placeholder="18:00"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm text-zinc-800 shadow-xs focus:border-[#1565D8] focus:outline-none focus:ring-2 focus:ring-[#1565D8]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    />
                    @error('form.end_time')
                        <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Alasan Izin (Opsional) --}}
                <div class="sm:col-span-2">
                    <label for="reason" class="mb-1 block text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        {{ __('Alasan Izin (Opsional)') }}
                    </label>
                    <textarea
                        id="reason"
                        wire:model="form.reason"
                        rows="2"
                        placeholder="Contoh: Izin urusan keluarga mendadak"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2 text-sm text-zinc-800 shadow-xs focus:border-[#1565D8] focus:outline-none focus:ring-2 focus:ring-[#1565D8]/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                    ></textarea>
                    @error('form.reason')
                        <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Tombol Aksi --}}
            <div class="flex items-center justify-between border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <button
                    type="button"
                    wire:click="resetForm"
                    class="rounded-xl border border-zinc-300 px-4 py-2 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                >
                    {{ __('Reset Form') }}
                </button>

                <flux:button
                    type="submit"
                    variant="primary"
                    icon="bolt"
                    class="bg-[#1565D8] hover:bg-[#0A3D91] text-white font-semibold"
                >
                    <span wire:loading.remove wire:target="submitLeave">{{ __('Simpan & Evaluasi Jadwal') }}</span>
                    <span wire:loading wire:target="submitLeave">{{ __('Memproses...') }}</span>
                </flux:button>
            </div>
        </form>
    </div>

    {{-- Hasil Evaluasi Penjadwalan (Jika Sudah Disubmit) --}}
    @if ($schedulingResult)
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-5 flex flex-col gap-2 border-b border-zinc-100 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                        {{ __('Hasil Evaluasi Scheduling Engine') }}
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $schedulingResult['summary'] }}
                    </p>
                </div>
                <div>
                    @if ($schedulingResult['all_schedules_resolved'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            {{ __('Semua Kelas Terselesaikan') }}
                        </span>
                    @elseif ($schedulingResult['total_affected_schedules'] === 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                            <span class="size-1.5 rounded-full bg-blue-500"></span>
                            {{ __('Bebas Jadwal') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                            <span class="size-1.5 rounded-full bg-amber-500"></span>
                            {{ __('Perlu Perhatian Admin') }}
                        </span>
                    @endif
                </div>
            </div>

            @if (empty($schedulingResult['affected_schedules']))
                <div class="rounded-xl border border-dashed border-zinc-200 p-8 text-center dark:border-zinc-800">
                    <flux:icon name="calendar" class="mx-auto size-8 text-zinc-400" />
                    <p class="mt-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                        {{ __('Tidak ada kelas mengajar yang terdampak pada rentang waktu izin ini.') }}
                    </p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($schedulingResult['affected_schedules'] as $index => $sched)
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30" wire:key="sched-res-{{ $sched['schedule_id'] }}">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white">
                                        #{{ $index + 1 }}. {{ $sched['class_name'] }} ({{ $sched['subject'] }})
                                    </h4>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Waktu:') }} {{ substr($sched['start_time'], 0, 5) }} - {{ substr($sched['end_time'], 0, 5) }} &bull; {{ $sched['date'] }}
                                    </p>
                                </div>
                                <div>
                                    @if ($sched['is_resolved'])
                                        <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                            {{ __('RESOLVED') }}
                                        </span>
                                    @elseif ($sched['status'] === 'room_issue')
                                        <span class="rounded-md bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-900 dark:text-rose-200">
                                            {{ __('MASALAH RUANGAN') }}
                                        </span>
                                    @else
                                        <span class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                            {{ __('BELUM ADA PENGGANTI') }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Peringatan Cross-Schedule --}}
                            @if (! empty($sched['warnings']))
                                <div class="mt-2 space-y-1">
                                    @foreach ($sched['warnings'] as $warning)
                                        <p class="text-xs font-medium text-amber-600 dark:text-amber-400">
                                            ⚠️ {{ $warning }}
                                        </p>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Rekomendasi Utama --}}
                            @if ($sched['best_candidate'])
                                <div class="mt-3 rounded-lg border border-blue-100 bg-blue-50/60 p-3 dark:border-blue-900/40 dark:bg-blue-950/20">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <flux:icon name="user" class="size-4 text-[#1565D8] dark:text-blue-400" />
                                            <span class="text-xs font-bold text-zinc-900 dark:text-white">
                                                {{ __('Rekomendasi Utama: ') }} {{ $sched['best_candidate']['instructor_name'] }}
                                            </span>
                                            <span class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                {{ ucfirst($sched['best_candidate']['skill_level'] ?? '') }}
                                            </span>
                                        </div>
                                        <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">
                                            {{ __('Skor: ') }}{{ $sched['best_candidate']['score'] }}
                                        </span>
                                    </div>
                                    <ul class="mt-2 list-inside list-disc text-[11px] text-zinc-600 dark:text-zinc-400">
                                        @foreach ($sched['best_candidate']['reasons'] as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- Status Ruangan --}}
                            @if (! empty($sched['room']))
                                <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                    <span class="font-semibold">{{ __('Ruangan:') }}</span> {{ $sched['room']['notes'] }}
                                    @if (! empty($sched['room']['suggested_alternative_room']))
                                        <span class="ml-1 font-semibold text-blue-600 dark:text-blue-400">
                                            &rarr; {{ __('Disarankan: ') }}{{ $sched['room']['suggested_alternative_room']['room_name'] }} ({{ __('Kapasitas: ') }}{{ $sched['room']['suggested_alternative_room']['capacity'] }})
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
