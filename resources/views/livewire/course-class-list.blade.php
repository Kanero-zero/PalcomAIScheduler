<div class="flex w-full flex-col gap-5">
    <section aria-label="Ringkasan kelas" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total kelas tersimpan</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($totalClasses, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total peserta (akumulasi per kelas)</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($totalEnrollments, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Kelas sesuai filter</p>
            <p class="mt-1 text-2xl font-bold text-[#1565D8] dark:text-blue-300">{{ number_format($classes->total(), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Kelas aktif (V2)</p>
            <p class="mt-1 text-2xl font-bold text-zinc-400">—</p>
            <p class="mt-1 text-[11px] text-amber-700">Status belum tersimpan dalam data lama</p>
        </div>
    </section>

    <section aria-label="Filter kelas" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Cari dan Filter Kelas</h2>
            <button type="button" wire:click="resetFilters" class="rounded-lg px-3 py-2 text-sm font-medium text-[#1565D8] hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950/30">Reset filter</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                Nama kelas atau materi
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari kelas atau materi" maxlength="100" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
            </label>
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-700 dark:text-zinc-300">Program
                <select disabled class="cursor-not-allowed rounded-xl border border-zinc-300 bg-zinc-100 px-3 py-2.5 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800"><option>Semua program (menunggu backend)</option></select>
            </label>
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-700 dark:text-zinc-300">Bidang pembelajaran
                <select disabled class="cursor-not-allowed rounded-xl border border-zinc-300 bg-zinc-100 px-3 py-2.5 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800"><option>Belum tersedia</option></select>
                <span class="text-[11px] font-normal text-amber-700">Filter tersedia setelah bidang tersimpan di backend.</span>
            </label>
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-700 dark:text-zinc-300">Status kelas
                <select disabled class="cursor-not-allowed rounded-xl border border-zinc-300 bg-zinc-100 px-3 py-2.5 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800"><option>Belum tercatat</option></select>
                <span class="text-[11px] font-normal text-amber-700">Status tidak ditebak dari jumlah sesi.</span>
            </label>
        </div>
        <details class="mt-3 rounded-lg border border-zinc-200 px-3 py-2 text-xs dark:border-zinc-800">
            <summary class="cursor-pointer font-semibold text-[#1565D8]">Filter materi lama (data existing)</summary>
            <label class="mt-3 flex max-w-sm flex-col gap-1 text-xs font-medium text-zinc-700 dark:text-zinc-300">Materi pelajaran
                <select wire:model.live="subject" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                    <option value="">Semua materi</option>
                    @foreach ($subjects as $availableSubject)
                        <option value="{{ $availableSubject }}">{{ $availableSubject }}</option>
                    @endforeach
                </select>
            </label>
        </details>
    </section>

    <section aria-label="Daftar kelas kursus" class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-4 sm:px-6 dark:border-zinc-800">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Daftar Kelas</h2>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Jumlah peserta berasal dari data kelas. Jumlah sesi mencakup semua jadwal yang tercatat, termasuk yang dibatalkan.</p>
        </div>
        @if ($classes->isEmpty())
            <div class="flex flex-col items-center gap-2 px-6 py-12 text-center">
                <flux:icon name="academic-cap" class="size-8 text-[#1565D8]" />
                <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $totalClasses === 0 ? 'Belum ada data kelas kursus.' : 'Tidak ada kelas yang sesuai dengan filter.' }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $totalClasses === 0 ? 'Data kelas akan muncul setelah tersedia.' : 'Coba ubah pencarian atau reset filter.' }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[930px] text-left text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold sm:px-6">Nama kelas</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Program</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Pola jadwal</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Periode</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($classes as $courseClass)
                            <tr wire:key="course-class-{{ $courseClass->id }}" class="text-zinc-700 dark:text-zinc-200">
                                <td class="px-4 py-4 font-semibold text-zinc-900 sm:px-6 dark:text-white">
                                    {{ $courseClass->name }}
                                    <span class="mt-1 block text-xs font-normal text-zinc-500">Materi: {{ $courseClass->subject }} · {{ number_format($courseClass->student_count, 0, ',', '.') }} peserta</span>
                                </td>
                                <td class="px-4 py-4 text-xs text-zinc-500">Belum tercatat</td>
                                <td class="px-4 py-4 text-xs text-zinc-500">{{ number_format($courseClass->schedules_count, 0, ',', '.') }} sesi tercatat <span class="block text-amber-700">Pola belum tercatat</span></td>
                                <td class="px-4 py-4 text-xs text-zinc-500">Belum tercatat</td>
                                <td class="px-4 py-4 text-xs text-zinc-500">Belum tercatat</td>
                                <td class="px-4 py-4">
                                    <div class="flex gap-1.5">
                                        <button type="button" @click="$dispatch('palcom:class-detail', @js(['id' => $courseClass->id, 'name' => $courseClass->name, 'subject' => $courseClass->subject, 'student_count' => $courseClass->student_count, 'schedules_count' => $courseClass->schedules_count]))" class="whitespace-nowrap rounded-lg border border-zinc-300 px-2.5 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200">Lihat Detail</button>
                                        <button type="button" disabled title="Perubahan jadwal resmi dilakukan melalui AI Scheduler" class="cursor-not-allowed whitespace-nowrap rounded-lg border border-blue-200 px-2.5 py-1.5 text-xs text-[#1565D8] opacity-45">Atur Jadwal</button>
                                        <button type="button" @click="$dispatch('palcom:class-edit', @js(['id' => $courseClass->id, 'name' => $courseClass->name, 'subject' => $courseClass->subject, 'student_count' => $courseClass->student_count, 'schedules_count' => $courseClass->schedules_count]))" class="rounded-lg border border-blue-200 px-2.5 py-1.5 text-xs font-semibold text-[#1565D8] hover:bg-blue-50 dark:border-blue-900">Edit</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 px-4 py-4 sm:px-6 dark:border-zinc-800">{{ $classes->links() }}</div>
        @endif
    </section>
</div>
