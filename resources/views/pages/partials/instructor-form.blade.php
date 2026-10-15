    <div x-data="palcomInstructorCreate(@js($formSeed))" class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 sm:p-6" x-cloak>
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-[#1565D8] dark:text-blue-300">PALCOM AI SCHEDULER / INSTRUKTUR</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl">{{ $isEditing ? 'Edit Instruktur' : 'Tambah Instruktur' }}</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $isEditing ? 'Tinjau data tersimpan serta rancangan perubahan kompetensi dan ketersediaan.' : 'Tambahkan data pengajar, kompetensi, dan ketersediaan mengajar.' }}</p>
            </div>
            <a href="{{ route('instructors.index') }}" wire:navigate class="inline-flex items-center rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">&larr; Kembali</a>
        </header>

        <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
            @if ($isEditing)
                <strong>Pratinjau Edit — {{ $instructor->name }}.</strong> Nama, status, dan daftar kompetensi diambil dari database. Bidang pembelajaran, catatan, serta ketersediaan mengajar BELUM tersimpan di skema saat ini: kosongnya field bukan berarti instruktur tidak tersedia. Pilih bidang untuk meninjau kompetensi yang cocok dengan contoh katalog. Tombol simpan belum diaktifkan; tidak ada perubahan yang disimpan.
            @else
                <strong>Pratinjau frontend.</strong> Kompetensi yang ditampilkan hanya contoh untuk meninjau interaksi. Data belum terhubung ke master kompetensi dan tombol simpan belum diaktifkan. Tidak ada data yang disimpan.
            @endif
        </div>

        <section aria-labelledby="instructor-identity-title" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 id="instructor-identity-title" class="mb-5 text-base font-bold text-zinc-900 dark:text-white">Data Instruktur</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div class="space-y-5">
                    <label class="flex flex-col gap-1.5 text-sm font-medium text-zinc-800 dark:text-zinc-200">
                        <span>Nama lengkap <span class="text-rose-600">*</span></span>
                        <input x-model="name" maxlength="150" required type="text" placeholder="Masukkan nama lengkap instruktur" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-[#1565D8] focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </label>
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">Bidang pembelajaran <span class="text-rose-600">*</span></legend>
                        <div class="flex flex-wrap gap-2">
                            <label class="cursor-pointer rounded-xl border px-3 py-2 text-sm transition" :class="department === 'computer' ? 'border-[#1565D8] bg-blue-50 font-semibold text-[#1565D8] dark:bg-blue-950/40' : 'border-zinc-300 text-zinc-700 dark:border-zinc-700 dark:text-zinc-300'">
                                <input type="radio" value="computer" x-model="department" @change="departmentChanged" class="mr-1 accent-[#1565D8]" /> Komputer
                            </label>
                            <label class="cursor-pointer rounded-xl border px-3 py-2 text-sm transition" :class="department === 'speakout' ? 'border-[#1565D8] bg-blue-50 font-semibold text-[#1565D8] dark:bg-blue-950/40' : 'border-zinc-300 text-zinc-700 dark:border-zinc-700 dark:text-zinc-300'">
                                <input type="radio" value="speakout" x-model="department" @change="departmentChanged" class="mr-1 accent-[#1565D8]" /> SpeakOut
                            </label>
                        </div>
                        @if ($isEditing)
                            <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Bidang belum dicatat di database lama. Pilih bidang untuk memulai pratinjau perubahan kompetensi.</p>
                        @endif
                    </fieldset>
                </div>
                <div class="space-y-5">
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">Status instruktur <span class="text-rose-600">*</span></legend>
                        <div class="flex flex-wrap gap-2">
                            <label class="cursor-pointer rounded-xl border px-3 py-2 text-sm" :class="status === 'active' ? 'border-emerald-300 bg-emerald-50 font-semibold text-emerald-800' : 'border-zinc-300 text-zinc-700 dark:border-zinc-700 dark:text-zinc-300'"><input type="radio" value="active" x-model="status" class="mr-1 accent-emerald-600" /> Aktif</label>
                            <label class="cursor-pointer rounded-xl border px-3 py-2 text-sm" :class="status === 'inactive' ? 'border-zinc-500 bg-zinc-100 font-semibold text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100' : 'border-zinc-300 text-zinc-700 dark:border-zinc-700 dark:text-zinc-300'"><input type="radio" value="inactive" x-model="status" class="mr-1 accent-[#1565D8]" /> Tidak Aktif</label>
                        </div>
                    </fieldset>
                    @if ($isEditing)
                        <p class="text-xs text-amber-700 dark:text-amber-300">Catatan belum didukung penyimpanan. Nilai kosong adalah pratinjau, bukan data tersimpan.</p>
                    @endif
                    <label class="flex flex-col gap-1.5 text-sm font-medium text-zinc-800 dark:text-zinc-200">
                        <span>Catatan (opsional)</span>
                        <textarea x-model="notes" maxlength="300" rows="3" placeholder="Informasi tambahan mengenai instruktur" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-[#1565D8] focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"></textarea>
                        <span class="text-right text-xs font-normal text-zinc-500"><span x-text="notes.length"></span>/300</span>
                    </label>
                </div>
            </div>
        </section>

        <section aria-labelledby="instructor-skill-title" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="instructor-skill-title" class="text-base font-bold text-zinc-900 dark:text-white">Kompetensi Mengajar</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Pilih kompetensi yang dikuasai instruktur beserta tingkat penguasaannya.</p>
                </div>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-[#1565D8] dark:bg-blue-950/40 dark:text-blue-300"><span x-text="selections.length"></span> kompetensi</span>
            </div>
            @if ($isEditing)
                <div class="mb-4 rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/40">
                    <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Kompetensi yang benar-benar tersimpan saat ini</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse ($instructor->skills as $storedSkill)
                            <span class="rounded-lg border border-blue-100 bg-blue-50 px-2.5 py-1 text-xs text-zinc-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-zinc-200">{{ $storedSkill->skill }} · {{ match ($storedSkill->level) { 'beginner' => 'Dasar', 'intermediate' => 'Menengah', 'advanced' => 'Mahir', default => \Illuminate\Support\Str::headline($storedSkill->level) } }}</span>
                        @empty
                            <span class="text-xs text-zinc-500">Belum ada kompetensi yang tersimpan.</span>
                        @endforelse
                    </div>
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Pilihan di bawah hanya pratinjau. Materi yang tidak cocok dengan katalog contoh tetap tercantum di atas dan tidak akan dihapus.</p>
                </div>
            @endif
            <label class="mb-4 flex cursor-pointer flex-wrap items-center gap-3 rounded-lg bg-blue-50/80 px-4 py-3 text-sm text-zinc-800 dark:bg-blue-950/30 dark:text-zinc-100">
                <input type="checkbox" class="size-4 accent-[#1565D8]" :checked="allSelected" @change="toggleAll($event.target.checked)" />
                <span class="font-semibold text-[#1565D8] dark:text-blue-300">Pilih Semua Kompetensi</span>
                <span class="text-xs text-zinc-500 dark:text-zinc-400">Hanya bidang yang dipilih; tiap tingkat tetap dapat diubah.</span>
            </label>
            <div class="mb-4 flex flex-wrap items-end gap-2">
                <label class="flex flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                    Tingkat penguasaan massal
                    <select x-model="bulkLevel" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                        <option value="beginner">Dasar</option><option value="intermediate">Menengah</option><option value="advanced">Mahir</option>
                    </select>
                </label>
                <button type="button" :disabled="selections.length === 0" @click="applyBulkLevel" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium text-[#1565D8] hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-700">Terapkan ke semua terpilih</button>
            </div>
            <div class="space-y-2">
                <template x-for="(selection, index) in selections" :key="selection.key">
                    <div class="grid gap-2 rounded-xl border border-zinc-200 p-3 md:grid-cols-[1fr_230px_auto] md:items-end dark:border-zinc-800">
                        <label class="flex flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                            Kompetensi
                            <select :value="selection.id" x-init="$nextTick(() => { $el.value = selection.id })" @change="changeCompetency(selection.key, $event.target.value)" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                <template x-for="item in catalog" :key="item.id">
                                    <option :value="item.id" :selected="item.id === selection.id" :disabled="selections.some(other => other.id === item.id && other.key !== selection.key)" x-text="item.name"></option>
                                </template>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                            Tingkat Penguasaan
                            <select x-model="selection.level" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                <option value="beginner">Dasar</option><option value="intermediate">Menengah</option><option value="advanced">Mahir</option>
                            </select>
                        </label>
                        <button type="button" @click="removeCompetency(selection.key)" class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300" :aria-label="'Hapus kompetensi ' + (index + 1)">Hapus</button>
                    </div>
                </template>
                <p x-show="selections.length === 0" class="rounded-lg border border-dashed border-zinc-300 px-4 py-5 text-center text-sm text-zinc-500 dark:border-zinc-700">Belum ada kompetensi dipilih.</p>
            </div>
            <button type="button" :disabled="!department || selections.length >= catalog.length" @click="addCompetency" class="mt-3 w-full rounded-xl border border-dashed border-blue-300 px-4 py-2.5 text-sm font-semibold text-[#1565D8] hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-blue-800 dark:hover:bg-blue-950/30">+ Tambah Kompetensi</button>
        </section>

        <section aria-labelledby="instructor-availability-title" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 id="instructor-availability-title" class="text-base font-bold text-zinc-900 dark:text-white">Ketersediaan Mengajar</h2>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Aktifkan hari mengajar lalu masukkan satu atau beberapa rentang jam. Hari yang tidak diaktifkan dianggap tidak tersedia.</p>
            @if ($isEditing)
                <p class="mt-2 text-xs font-semibold text-amber-700 dark:text-amber-300">Ketersediaan lama belum tersedia di database. Seluruh hari tampil tanpa pilihan awal; ini bukan pernyataan bahwa instruktur tidak tersedia.</p>
            @endif
            <div class="mt-5 grid gap-3 lg:grid-cols-2">
                <template x-for="day in days" :key="day.label">
                    <div class="rounded-xl border border-zinc-200 px-3 py-3 dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-2">
                            <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-zinc-800 dark:text-zinc-100">
                                <input type="checkbox" x-model="day.enabled" @change="toggleDay(day)" class="size-4 accent-[#1565D8]" />
                                <span x-text="day.label"></span>
                            </label>
                            <button type="button" x-show="day.enabled" @click="addSlot(day)" class="rounded-lg border border-blue-300 px-2.5 py-1 text-xs font-semibold text-[#1565D8] hover:bg-blue-50 dark:border-blue-800">+ Tambah Rentang Jam</button>
                        </div>
                        <p x-show="!day.enabled" class="mt-2 text-xs text-zinc-400">Tidak tersedia</p>
                        <div x-show="day.enabled" class="mt-3 space-y-2">
                            <template x-for="slot in day.slots" :key="slot.key">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input type="time" x-model="slot.start" :aria-label="'Jam mulai ' + day.label" class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                    <span class="text-zinc-400">–</span>
                                    <input type="time" x-model="slot.end" :aria-label="'Jam selesai ' + day.label" class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                    <button type="button" @click="removeSlot(day, slot.key)" class="rounded-lg border border-rose-200 px-2 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900">Hapus</button>
                                    <p x-show="invalidSlot(day, slot)" class="w-full text-xs font-semibold text-rose-600">Jam tidak valid atau berbenturan dengan rentang lain pada hari ini.</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <footer class="flex flex-wrap items-center justify-end gap-3 border-t border-zinc-200 pb-4 pt-5 dark:border-zinc-800">
            <a href="{{ route('instructors.index') }}" wire:navigate class="rounded-xl border border-zinc-300 px-5 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200">Batal</a>
            <button type="button" disabled aria-disabled="true" title="Menunggu backend penyimpanan dari Anggota 2" class="cursor-not-allowed rounded-xl bg-[#1565D8] px-5 py-2.5 text-sm font-semibold text-white opacity-50">{{ $isEditing ? 'Simpan Perubahan' : 'Simpan Instruktur' }}</button>
        </footer>
    </div>
