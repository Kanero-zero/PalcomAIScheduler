<x-layouts::app :title="__('Pengajuan Izin Instruktur')">
    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 py-2 sm:p-4">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="text-xs font-semibold tracking-widest text-[#1565D8] uppercase dark:text-blue-300">{{ __('Manajemen instruktur') }}</p>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl dark:text-white">{{ __('Pengajuan Izin Instruktur') }}</h1>
                <p class="max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Siapkan informasi ketidakhadiran agar penyesuaian jadwal kelas dapat direncanakan dengan baik.') }}</p>
            </div>
            <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-800">
                <span aria-hidden="true" class="size-1.5 rounded-full bg-[#FFC928]"></span>
                {{ __('Pratinjau formulir') }}
            </span>
        </header>

        <div role="status" class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900 dark:bg-amber-950/20">
            <flux:icon name="information-circle" class="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
            <div class="min-w-0 space-y-1">
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ __('Formulir belum terhubung') }}</p>
                <p id="leave-connection-help" class="text-sm leading-6 text-amber-900/80 dark:text-amber-100/80">{{ __('Anda dapat mencoba mengisi tanggal, waktu, dan alasan izin. Pilihan instruktur dan pengiriman belum tersedia. Isian tidak disimpan dan tidak menjalankan penjadwalan.') }}</p>
            </div>
        </div>

        <div class="grid min-w-0 grid-cols-1 items-start gap-6 xl:grid-cols-3">
            <section aria-labelledby="leave-form-heading" class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs xl:col-span-2 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start gap-3 border-b border-zinc-100 p-5 sm:p-6 dark:border-zinc-800">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#1565D8] dark:bg-blue-950 dark:text-blue-300">
                        <flux:icon name="calendar-days" class="size-5" />
                    </span>
                    <div class="space-y-1">
                        <h2 id="leave-form-heading" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Detail pengajuan izin') }}</h2>
                        <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Field bertanda * wajib diisi saat pengiriman tersedia.') }}</p>
                    </div>
                </div>

                {{--
                    Integration handoff for App\Livewire\InstructorLeaveForm:
                    replace x-on:submit.prevent with wire:submit="submitLeave";
                    bind each control with wire:model="form.<name>" (keep the HTML names);
                    populate instructor_id from $this->instructors (active only, ordered by name);
                    enable the selector and submit button only when that backend is connected.
                    Error keys below already follow API_CONTRACT_SCHEDULER.md.
                --}}
                <form id="instructor-leave-form" x-data x-on:submit.prevent aria-labelledby="leave-form-heading" aria-describedby="leave-connection-help" class="[--color-accent:#1565D8] [--color-accent-foreground:#ffffff]">
                    <div class="space-y-6 p-5 sm:p-6">
                        <flux:field>
                            <flux:label for="leave-instructor">{{ __('Instruktur') }} <span aria-hidden="true" class="ms-1 text-red-600 dark:text-red-400">*</span></flux:label>
                            <flux:select
                                id="leave-instructor"
                                name="instructor_id"
                                required
                                disabled
                                aria-describedby="leave-instructor-help leave-instructor-error"
                                :invalid="$errors->has('form.instructor_id')"
                            >
                                <option value="">{{ __('Belum tersedia') }}</option>
                            </flux:select>
                            <flux:description id="leave-instructor-help">{{ __('Hanya instruktur aktif yang dapat dipilih setelah daftar tersedia.') }}</flux:description>
                            <flux:error id="leave-instructor-error" name="form.instructor_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label for="leave-date">{{ __('Tanggal izin') }} <span aria-hidden="true" class="ms-1 text-red-600 dark:text-red-400">*</span></flux:label>
                            <flux:input
                                id="leave-date"
                                name="date"
                                type="date"
                                required
                                class="min-w-0 w-full"
                                aria-describedby="leave-date-help leave-date-error"
                                :invalid="$errors->has('form.date')"
                            />
                            <flux:description id="leave-date-help">{{ __('Pilih tanggal izin. Nilai tanggal menggunakan format YYYY-MM-DD; tampilan mengikuti pengaturan perangkat.') }}</flux:description>
                            <flux:error id="leave-date-error" name="form.date" />
                        </flux:field>

                        <fieldset class="min-w-0 space-y-4">
                            <legend class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Rentang waktu izin') }}</legend>
                            <div class="grid min-w-0 grid-cols-1 gap-5 sm:grid-cols-2">
                                <flux:field class="min-w-0">
                                    <flux:label for="leave-start-time">{{ __('Jam mulai') }} <span aria-hidden="true" class="ms-1 text-red-600 dark:text-red-400">*</span></flux:label>
                                    <flux:input
                                        id="leave-start-time"
                                        name="start_time"
                                        type="time"
                                        step="60"
                                        required
                                        class="min-w-0 w-full"
                                        aria-describedby="leave-start-help leave-start-error"
                                        :invalid="$errors->has('form.start_time')"
                                    />
                                    <flux:description id="leave-start-help">{{ __('Format 24 jam (HH:mm).') }}</flux:description>
                                    <flux:error id="leave-start-error" name="form.start_time" />
                                </flux:field>
                                <flux:field class="min-w-0">
                                    <flux:label for="leave-end-time">{{ __('Jam selesai') }} <span aria-hidden="true" class="ms-1 text-red-600 dark:text-red-400">*</span></flux:label>
                                    <flux:input
                                        id="leave-end-time"
                                        name="end_time"
                                        type="time"
                                        step="60"
                                        required
                                        class="min-w-0 w-full"
                                        aria-describedby="leave-end-help leave-end-error"
                                        :invalid="$errors->has('form.end_time')"
                                    />
                                    <flux:description id="leave-end-help">{{ __('Format HH:mm, harus setelah jam mulai pada tanggal yang sama.') }}</flux:description>
                                    <flux:error id="leave-end-error" name="form.end_time" />
                                </flux:field>
                            </div>
                        </fieldset>

                        <flux:field>
                            <flux:label for="leave-reason" badge="Opsional">{{ __('Alasan izin') }}</flux:label>
                            <flux:textarea
                                id="leave-reason"
                                name="reason"
                                rows="4"
                                maxlength="500"
                                resize="vertical"
                                placeholder="Tuliskan alasan atau keterangan singkat."
                                aria-describedby="leave-reason-help leave-reason-error"
                                :invalid="$errors->has('form.reason')"
                            />
                            <flux:description id="leave-reason-help">{{ __('Maksimal 500 karakter. Dapat dikosongkan jika tidak ada keterangan tambahan.') }}</flux:description>
                            <flux:error id="leave-reason-error" name="form.reason" />
                        </flux:field>
                    </div>

                    <div class="flex flex-col gap-4 border-t border-zinc-100 bg-zinc-50/70 p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-950/30">
                        <p id="leave-submit-help" class="text-xs leading-5 text-zinc-600 dark:text-zinc-400">{{ __('Pengiriman akan tersedia setelah formulir terhubung. Isian pratinjau akan hilang saat halaman dimuat ulang.') }}</p>
                        <flux:button type="submit" variant="primary" icon="lock-closed" disabled aria-describedby="leave-submit-help" class="w-full sm:w-fit">
                            {{ __('Pengiriman belum tersedia') }}
                        </flux:button>
                    </div>
                </form>
            </section>

            <aside aria-labelledby="leave-checks-heading" class="flex min-w-0 flex-col gap-5">
                <section class="overflow-hidden rounded-2xl border border-blue-100 bg-white dark:border-blue-900 dark:bg-zinc-900">
                    <div class="space-y-3 bg-[#0A3D91] p-5 text-white sm:p-6">
                        <p class="text-xs font-semibold tracking-widest text-[#FFC928] uppercase">{{ __('Setelah formulir terhubung') }}</p>
                        <h2 id="leave-checks-heading" class="text-lg font-semibold">{{ __('Dari izin ke rencana pengganti') }}</h2>
                        <p class="text-sm leading-6 text-blue-100">{{ __('Data pengajuan nantinya digunakan untuk pemeriksaan berikut oleh mesin penjadwalan.') }}</p>
                    </div>
                    <ol class="space-y-5 p-5 sm:p-6">
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-xs font-semibold text-[#1565D8] dark:bg-blue-950 dark:text-blue-300">01</span>
                            <div><h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Jadwal terdampak') }}</h3><p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Kelas yang beririsan dengan tanggal dan rentang waktu izin.') }}</p></div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-xs font-semibold text-[#1565D8] dark:bg-blue-950 dark:text-blue-300">02</span>
                            <div><h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Kesesuaian kompetensi') }}</h3><p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Keahlian calon instruktur pengganti sesuai materi kelas.') }}</p></div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-xs font-semibold text-[#1565D8] dark:bg-blue-950 dark:text-blue-300">03</span>
                            <div><h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Bentrok instruktur') }}</h3><p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Jadwal mengajar dan izin lain dari calon pengganti.') }}</p></div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-xs font-semibold text-[#1565D8] dark:bg-blue-950 dark:text-blue-300">04</span>
                            <div><h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Ketersediaan ruangan') }}</h3><p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Kapasitas, status, dan bentrok penggunaan ruangan.') }}</p></div>
                        </li>
                    </ol>
                </section>
                <section aria-labelledby="leave-history-heading" class="rounded-2xl border border-dashed border-zinc-300 p-5 dark:border-zinc-700">
                    <h2 id="leave-history-heading" class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Riwayat pengajuan') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Riwayat izin belum ditampilkan pada halaman ini. Formulir pratinjau tidak menambahkan pengajuan baru.') }}</p>
                </section>
            </aside>
        </div>
    </div>
</x-layouts::app>
