<x-layouts::app :title="__('Kelas Kursus')">
    {{-- Frontend preview only: no class/curriculum persistence, publication or approval. --}}
    <div x-data="palcomCourseClassV2()"
        @palcom:class-detail.window="showClassDetail($event.detail)"
        @palcom:class-edit.window="openClassEdit($event.detail)"
        @keydown.escape.window="detailOpen = false; competencyOpen = false"
        class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 sm:p-6">

        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-widest text-[#1565D8] dark:text-blue-300">PALCOM AI SCHEDULER / KELAS KURSUS</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-3xl"
                    x-text="screen === 'class-form' ? (mode === 'edit' ? 'Edit Kelas' : 'Tambah Kelas') : screen === 'curriculum-form' ? (mode === 'edit' ? 'Edit Kurikulum' : 'Tambah Kurikulum') : 'Kelas Kursus'">Kelas Kursus</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400" x-show="screen === 'index'">Kelola data kelas, angkatan, dan kurikulum pembelajaran.</p>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400" x-show="screen === 'class-form'" style="display:none">Buat data kelas baru dan tentukan kurikulum yang akan dipakai.</p>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400" x-show="screen === 'curriculum-form'" style="display:none">Susun template kurikulum untuk angkatan berikutnya.</p>
            </div>
            <div x-show="screen === 'index'" class="flex flex-wrap gap-2">
                <button type="button" x-show="tab === 'classes'" @click="newClass()" class="rounded-xl bg-[#1565D8] px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-[#0A3D91]">+ Tambah Kelas</button>
                <button type="button" x-show="tab === 'curricula'" @click="newCurriculum()" class="rounded-xl bg-[#1565D8] px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-[#0A3D91]" style="display:none">+ Buat Kurikulum</button>
            </div>
            <button type="button" x-show="screen !== 'index'" @click="closeForm()" class="rounded-xl bg-[#1565D8] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0A3D91]" style="display:none">&larr; Kembali</button>
        </header>

        {{-- Main tabs. No additional sidebar module. --}}
        <nav x-show="screen === 'index'" aria-label="Bagian Kelas Kursus" class="flex flex-wrap items-center gap-5 border-b border-zinc-200 dark:border-zinc-800">
            <button type="button" @click="chooseTab('classes')" :class="tab === 'classes' ? 'border-[#1565D8] text-[#1565D8] dark:text-blue-300' : 'border-transparent text-zinc-600 dark:text-zinc-400'" class="border-b-2 px-1 pb-3 pt-1 text-sm font-semibold">Daftar Kelas</button>
            <button type="button" @click="chooseTab('curricula')" :class="tab === 'curricula' ? 'border-[#1565D8] text-[#1565D8] dark:text-blue-300' : 'border-transparent text-zinc-600 dark:text-zinc-400'" class="border-b-2 px-1 pb-3 pt-1 text-sm font-semibold">Kurikulum Program</button>
        </nav>

        <div x-show="screen === 'index' && tab === 'classes'">
            <livewire:course-class-list />
        </div>

        {{-- Curriculum program tab: explicit illustrative placeholders, NOT live database curricula. --}}
        <div x-show="screen === 'index' && tab === 'curricula'" style="display:none" class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">Kurikulum Program</h2>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Pilih program untuk meninjau contoh struktur kurikulum. Data kurikulum asli menunggu backend.</p>
                </div>
                <button type="button" @click="competencyOpen = true" class="rounded-xl border border-zinc-300 px-3 py-2 text-sm font-semibold text-[#1565D8] hover:bg-blue-50 dark:border-zinc-700 dark:hover:bg-blue-950/30">Kelola Kompetensi</button>
            </div>
            <div class="flex flex-wrap gap-2" aria-label="Pilih program">
                <template x-for="program in programs" :key="program.id">
                    <button type="button" @click="chooseProgram(program.id)" :aria-pressed="programId === program.id"
                        :class="programId === program.id ? 'bg-[#1565D8] text-white border-[#1565D8]' : 'bg-white text-zinc-700 border-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:border-zinc-700'"
                        class="rounded-xl border px-3 py-2 text-xs font-semibold transition hover:opacity-90" x-text="program.name"></button>
                </template>
            </div>
            <section class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold text-zinc-900 dark:text-white" x-text="`Kurikulum Program: ${selectedProgram?.name || ''}`"></h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Struktur, mata pelajaran, dan alokasi per minggu.</p>
                    </div>
                    <span class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">ILUSTRASI — Belum dari database</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button type="button" @click="selectedVersion = 'example-published'" :class="selectedVersion === 'example-published' ? 'border-[#1565D8] bg-blue-50/50 dark:bg-blue-950/30' : 'border-zinc-200 dark:border-zinc-800'" class="rounded-xl border p-3 text-left dark:text-zinc-200">
                        <div class="flex flex-wrap items-center gap-2"><span class="font-semibold text-sm" x-text="`${selectedProgram?.name || ''} v2026.1`"></span><span class="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Terbit (contoh)</span></div>
                        <p class="mt-1 text-xs text-zinc-500">Dapat dipilih oleh angkatan baru setelah backend aktif.</p>
                    </button>
                    <button type="button" @click="selectedVersion = 'example-draft'" :class="selectedVersion === 'example-draft' ? 'border-[#1565D8] bg-blue-50/50 dark:bg-blue-950/30' : 'border-zinc-200 dark:border-zinc-800'" class="rounded-xl border p-3 text-left dark:text-zinc-200">
                        <div class="flex flex-wrap items-center gap-2"><span class="font-semibold text-sm" x-text="`${selectedProgram?.name || ''} v2026.2`"></span><span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">Draft (contoh)</span></div>
                        <p class="mt-1 text-xs text-zinc-500">Draft tidak boleh dipilih ketika membuat kelas.</p>
                    </button>
                </div>
                <div class="grid gap-3 lg:grid-cols-2">
                    <template x-for="(period, pi) in selectedPeriods" :key="`${programId}-${pi}`">
                        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <div class="border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100" x-text="`${period.name} (${period.duration})`"></p>
                            </div>
                            <div class="grid grid-cols-[1fr_auto] gap-3 border-b border-zinc-100 px-4 py-2 text-[11px] font-semibold uppercase text-zinc-500 dark:border-zinc-800"><span>Mata Pelajaran</span><span>Frekuensi</span></div>
                            <template x-for="(track, ti) in period.tracks" :key="ti">
                                <div class="grid grid-cols-[1fr_auto] gap-3 border-b border-zinc-100 px-4 py-3 text-sm text-zinc-700 last:border-b-0 dark:border-zinc-800 dark:text-zinc-200">
                                    <span x-text="track.name"></span><span class="text-xs text-zinc-500" x-text="`${track.weekly}× per minggu`"></span>
                                </div>
                            </template>
                            <p x-show="period.tracks.length === 0" class="px-4 py-5 text-xs text-zinc-500">Materi periode ini belum ditentukan.</p>
                        </div>
                    </template>
                    <div x-show="selectedPeriods.length === 0" class="rounded-xl border border-dashed border-zinc-300 p-5 text-sm text-zinc-500 lg:col-span-2 dark:border-zinc-700">Struktur periode program ini belum dirinci. Tidak ada data kurikulum yang direka.</div>
                </div>
                <div class="grid gap-2 sm:grid-cols-3">
                    <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3 text-xs text-zinc-700 dark:border-blue-950 dark:bg-blue-950/20 dark:text-zinc-300">Basis penyelesaian<br><strong>Jumlah pertemuan completed</strong></div>
                    <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3 text-xs text-zinc-700 dark:border-blue-950 dark:bg-blue-950/20 dark:text-zinc-300">Target materi<br><strong>Belum tersedia — wajib dari backend</strong></div>
                    <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3 text-xs text-zinc-700 dark:border-blue-950 dark:bg-blue-950/20 dark:text-zinc-300">Versi &amp; revisi<br><strong>Snapshot per angkatan</strong></div>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" @click="editExampleCurriculum()" class="rounded-xl border border-blue-200 px-3 py-2 text-sm font-semibold text-[#1565D8] hover:bg-blue-50 dark:border-blue-900">Edit Template (Preview)</button>
                    <button type="button" disabled title="Menunggu backend" class="cursor-not-allowed rounded-xl bg-[#1565D8] px-3 py-2 text-sm font-semibold text-white opacity-45">Terbitkan untuk Angkatan Baru</button>
                </div>
            </section>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200">
                <strong>Snapshot kurikulum per angkatan.</strong> Angkatan yang telah memilih suatu kurikulum tetap menggunakan isi/revisi saat kelas dibuat, walaupun template terbit kemudian diedit.
            </div>
        </div>

        {{-- ADD / EDIT CLASS: no form submit and no fabricated legacy attributes. --}}
        <div x-show="screen === 'class-form'" style="display:none" class="space-y-4">
            <div role="status" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"><strong>Ini adalah tampilan preview frontend.</strong> Simpan Kelas belum terhubung dengan backend; isian tidak disimpan.</div>
            <section class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-base font-bold text-zinc-900 dark:text-white">Data Kelas</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Nama kelas / angkatan <span class="text-rose-600">*</span>
                        <input type="text" x-model="classForm.name" maxlength="150" placeholder="Contoh: PTIK 10 Bulan — Batch A" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </label>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Program <span class="text-rose-600">*</span>
                        <select :disabled="mode === 'edit'" :value="classForm.program" @change="changeClassProgram($event.target.value)" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <option value="">Belum tercatat / pilih program</option>
                            <template x-for="program in programOptions" :key="program.id"><option :value="program.id" x-text="program.name"></option></template>
                        </select>
                        <span x-show="mode === 'edit'" class="mt-1 block text-xs text-amber-700">Data program legacy belum ada; tidak boleh ditebak atau diubah via preview.</span>
                    </label>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <fieldset><legend class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">Bidang pembelajaran</legend>
                        <div class="flex gap-2">
                            <template x-for="option in [{id:'computer',label:'Komputer'},{id:'speakout',label:'SpeakOut'}]" :key="option.id">
                                <label :class="classForm.department === option.id ? 'border-[#1565D8] bg-blue-50 dark:bg-blue-950/30' : 'border-zinc-200 dark:border-zinc-700'" class="flex flex-1 items-center gap-2 rounded-xl border px-3 py-2.5 text-sm dark:text-zinc-200"><input type="radio" name="class-department" :value="option.id" :checked="classForm.department === option.id" :disabled="mode === 'edit'" @change="changeDepartment(option.id)" /><span x-text="option.label"></span></label>
                            </template>
                        </div>
                    </fieldset>
                    <fieldset><legend class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">Jenis kelas</legend>
                        <div class="flex gap-2"><template x-for="option in [{id:'regular',label:'Reguler'},{id:'private',label:'Privat'}]" :key="option.id"><label :class="classForm.kind === option.id ? 'border-[#1565D8] bg-blue-50 dark:bg-blue-950/30' : 'border-zinc-200 dark:border-zinc-700'" class="flex flex-1 items-center gap-2 rounded-xl border px-3 py-2.5 text-sm dark:text-zinc-200"><input type="radio" name="class-kind" :value="option.id" x-model="classForm.kind" /><span x-text="option.label"></span></label></template></div>
                    </fieldset>
                </div>
                <label class="block max-w-xs text-sm font-medium text-zinc-700 dark:text-zinc-200">Jumlah siswa <span class="text-rose-600">*</span>
                    <input type="number" x-model="classForm.count" min="1" placeholder="Contoh: 6" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                </label>
                <fieldset><legend class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">Status kelas <span class="text-xs font-normal text-amber-700">(preview; bukan status jadwal resmi)</span></legend>
                    <div class="flex flex-wrap gap-2"><template x-for="option in [{id:'upcoming',label:'Akan mulai'},{id:'active',label:'Aktif'},{id:'completed',label:'Selesai'},{id:'postponed',label:'Ditunda'}]" :key="option.id"><label :class="classForm.status === option.id ? 'border-[#1565D8] bg-blue-50 dark:bg-blue-950/30' : 'border-zinc-200 dark:border-zinc-700'" class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs dark:text-zinc-200"><input type="radio" name="class-status" :value="option.id" x-model="classForm.status" /><span x-text="option.label"></span></label></template></div>
                    <p x-show="mode === 'edit'" class="mt-1 text-xs text-amber-700">Kelas lama belum menyimpan status V2; jangan disimpulkan dari jumlah jadwal.</p>
                </fieldset>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Tanggal mulai belajar <input type="date" x-model="classForm.start" :disabled="mode === 'edit'" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm disabled:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /></label>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Tanggal selesai belajar <input type="date" x-model="classForm.end" :disabled="mode === 'edit'" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm disabled:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /></label>
                </div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">Catatan (opsional)<textarea x-model="classForm.notes" maxlength="500" rows="3" placeholder="Tulis catatan tambahan tentang kelas ini..." class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"></textarea><span class="block text-right text-xs text-zinc-500"><span x-text="classForm.notes.length"></span>/500</span></label>
            </section>
            <section class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-base font-bold text-zinc-900 dark:text-white">Kurikulum &amp; Ringkasan</h2>
                <label class="flex flex-col gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">Kurikulum dipakai
                    <select x-model="classForm.version" :disabled="mode === 'edit' || !formProgram?.periods.length" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-700 disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                        <option value="">Belum tersedia — backend kurikulum diperlukan</option>
                        <option value="example-published">Contoh: v2026.1 — Diterbitkan (ilustrasi)</option>
                    </select>
                    <span class="text-xs font-normal text-amber-700 dark:text-amber-300">Pilihan kurikulum nyata dan penyimpanan snapshot akan diaktifkan oleh backend; contoh tidak bisa disimpan.</span>
                </label>
                <div class="grid gap-2 sm:grid-cols-4">
                    <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3 text-xs dark:border-blue-950 dark:bg-blue-950/20"><span class="text-zinc-500">Struktur</span><br/><strong x-text="formProgram?.periods.length ? `${formProgram.periods.length} periode` : 'Belum dirinci'"></strong></div>
                    <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3 text-xs dark:border-blue-950 dark:bg-blue-950/20"><span class="text-zinc-500">Basis penyelesaian</span><br/><strong>Jumlah pertemuan</strong></div>
                    <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3 text-xs dark:border-blue-950 dark:bg-blue-950/20"><span class="text-zinc-500">Target</span><br/><strong>Belum ditentukan</strong></div>
                    <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3 text-xs dark:border-blue-950 dark:bg-blue-950/20"><span class="text-zinc-500">Pola program</span><br/><strong x-text="`${formProgram?.frequency || 'Belum tercatat'} · ${formProgram?.session || ''}`"></strong></div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <template x-for="(period, i) in (formProgram?.periods || [])" :key="i">
                        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800"><div class="border-b border-zinc-200 bg-zinc-50 px-3 py-2 font-semibold text-sm dark:border-zinc-800 dark:bg-zinc-800/50" x-text="`${period.name} (${period.duration})`"></div><template x-for="(track, j) in period.tracks" :key="j"><div class="flex justify-between gap-2 border-b border-zinc-100 px-3 py-2 text-xs last:border-b-0 dark:border-zinc-800"><span x-text="track.name"></span><span class="text-zinc-500" x-text="`${track.weekly}×/minggu`"></span></div></template><p x-show="period.tracks.length === 0" class="p-3 text-xs text-zinc-500">Belum ada materi resmi.</p></div>
                    </template>
                    <p x-show="!(formProgram?.periods.length)" class="rounded-xl border border-dashed border-zinc-300 p-4 text-xs text-zinc-500 md:col-span-2">Struktur kurikulum belum tersedia dalam pratinjau.</p>
                </div>
            </section>
            <div class="flex flex-wrap items-center justify-between gap-3"><div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200">Hari, jam, instruktur, ruangan, dan persetujuan jadwal diatur terpisah melalui AI Scheduler.</div><div class="flex gap-2"><button type="button" @click="closeForm()" class="rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-medium dark:border-zinc-700">Batal</button><button type="button" disabled title="Belum ada backend untuk menyimpan kelas" class="cursor-not-allowed rounded-xl bg-[#1565D8] px-5 py-2.5 text-sm font-semibold text-white opacity-40">Simpan Kelas</button></div></div>
        </div>

        {{-- ADD / EDIT CURRICULUM: functional local editor for presentation, no DB writes. --}}
        <div x-show="screen === 'curriculum-form'" style="display:none" class="space-y-4">
            <div role="status" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"><strong>Ini adalah tampilan preview frontend.</strong> Struktur bisa diedit untuk menilai UX, tetapi tidak tersimpan atau diterbitkan.</div>
            <section class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-base font-bold text-zinc-900 dark:text-white">Informasi Kurikulum</h2>
                <fieldset><legend class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">Program</legend><div class="flex flex-wrap gap-2"><template x-for="program in programs" :key="program.id"><button type="button" @click="chooseProgram(program.id);draftPeriods=JSON.parse(JSON.stringify(selectedPeriods))" :class="programId === program.id ? 'bg-[#1565D8] text-white border-[#1565D8]' : 'bg-white text-zinc-700 border-zinc-300 dark:bg-zinc-900 dark:text-zinc-200 dark:border-zinc-700'" class="rounded-xl border px-3 py-2 text-xs font-semibold" x-text="program.name"></button></template></div></fieldset>
                <div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm font-medium dark:text-zinc-200">Nama versi kurikulum<input x-model="curriculumVersion" type="text" maxlength="100" placeholder="Contoh: PTIK v2026.2" class="mt-1 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /></label><fieldset><legend class="mb-2 text-sm font-medium dark:text-zinc-200">Status</legend><div class="flex gap-2"><label :class="curriculumStatus==='draft'?'border-[#1565D8] bg-blue-50 dark:bg-blue-950/20':'border-zinc-300 dark:border-zinc-700'" class="flex flex-1 items-center gap-2 rounded-xl border px-3 py-2.5 text-sm dark:text-zinc-200"><input type="radio" name="curriculum-status" value="draft" x-model="curriculumStatus"/>Draft</label><label :class="curriculumStatus==='published'?'border-[#1565D8] bg-blue-50 dark:bg-blue-950/20':'border-zinc-300 dark:border-zinc-700'" class="flex flex-1 items-center gap-2 rounded-xl border px-3 py-2.5 text-sm dark:text-zinc-200"><input type="radio" name="curriculum-status" value="published" x-model="curriculumStatus"/>Terbit</label></div></fieldset></div>
                <div class="grid gap-3 sm:grid-cols-2"><div class="rounded-xl border border-blue-100 bg-blue-50/50 px-4 py-3 text-xs dark:border-blue-900 dark:bg-blue-950/20"><strong>Berlaku untuk</strong><p class="mt-1 text-zinc-600 dark:text-zinc-300">Angkatan baru yang memilih versi/revisi ini</p></div><div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-xs dark:border-zinc-800 dark:bg-zinc-800/40"><strong>Aturan penyelesaian</strong><p class="mt-1 text-zinc-600 dark:text-zinc-300">Jumlah pertemuan berstatus completed</p></div></div>
            </section>
            <section class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-base font-bold text-zinc-900 dark:text-white">Susunan Kurikulum</h2><div class="flex flex-wrap gap-2"><button type="button" @click="addPeriod()" class="rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-[#1565D8] hover:bg-blue-50">+ Tambah Semester / Periode</button></div></div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <template x-for="(period, pi) in draftPeriods" :key="pi">
                        <div class="space-y-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                            <div class="flex items-center gap-2"><input type="text" x-model="period.name" aria-label="Nama semester atau periode" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 py-2 text-sm font-semibold dark:border-zinc-700 dark:bg-zinc-800" /><button type="button" @click="removePeriod(pi)" aria-label="Hapus periode" class="rounded-lg border border-rose-200 px-2 py-2 text-xs text-rose-700">Hapus</button></div>
                            <label class="flex items-center gap-2 text-xs text-zinc-500">Durasi periode <input type="text" x-model="period.duration" placeholder="Contoh: 5 bulan" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-800" /></label>
                            <div class="grid grid-cols-[1fr_80px_auto] gap-2 px-1 text-[10px] font-bold uppercase text-zinc-500"><span>Mata pelajaran / jalur</span><span>Per minggu</span><span></span></div>
                            <template x-for="(track, ti) in period.tracks" :key="ti">
                                <div class="space-y-2 rounded-lg border border-zinc-100 p-2 dark:border-zinc-800"><div class="grid grid-cols-[1fr_80px_auto] gap-2"><input x-model="track.name" type="text" aria-label="Nama mata pelajaran" placeholder="Nama mata pelajaran" class="min-w-0 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-800"/><input x-model.number="track.weekly" type="number" min="1" max="7" aria-label="Pertemuan per minggu" class="w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-800"/><button type="button" @click="removeTrack(pi,ti)" aria-label="Hapus mata pelajaran" class="px-1 text-xs text-rose-600">✕</button></div>
                                    <div class="space-y-1 pl-2"><template x-for="(module,mi) in track.modules" :key="mi"><div class="flex items-center gap-2"><input x-model="module.name" type="text" placeholder="Materi berurutan" aria-label="Nama materi" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-2 py-1 text-[11px] dark:border-zinc-700 dark:bg-zinc-800"/><input x-model="module.target" type="number" min="1" placeholder="Target" aria-label="Target pertemuan completed" class="w-16 rounded-lg border border-zinc-300 px-2 py-1 text-[11px] dark:border-zinc-700 dark:bg-zinc-800"/><button type="button" @click="removeModule(pi,ti,mi)" aria-label="Hapus materi" class="text-rose-600">✕</button></div></template><button type="button" @click="addModule(pi,ti)" class="text-[11px] font-medium text-[#1565D8]">+ Tambah materi &amp; target</button></div>
                                </div>
                            </template>
                            <button type="button" @click="addTrack(pi)" class="w-full rounded-lg border border-dashed border-blue-300 py-2 text-xs font-semibold text-[#1565D8] hover:bg-blue-50">+ Tambah Mata Pelajaran</button>
                        </div>
                    </template>
                    <div x-show="draftPeriods.length===0" class="rounded-xl border border-dashed border-zinc-300 p-5 text-sm text-zinc-500 lg:col-span-2">Belum ada semester/periode. Klik Tambah Semester / Periode untuk mulai menyusun.</div>
                </div>
            </section>
            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200"><strong>Perlindungan snapshot:</strong> edit template terbit diperbolehkan, tetapi revisi baru hanya berlaku untuk angkatan baru; snapshot angkatan berjalan tidak berubah.</div>
            <div class="flex flex-wrap justify-end gap-2"><button type="button" @click="closeForm()" class="rounded-xl border border-zinc-300 px-4 py-2.5 text-sm dark:border-zinc-700">Batal</button><button type="button" disabled class="cursor-not-allowed rounded-xl border border-blue-200 px-4 py-2.5 text-sm font-semibold text-[#1565D8] opacity-40">Simpan Draft</button><button type="button" disabled class="cursor-not-allowed rounded-xl bg-[#1565D8] px-4 py-2.5 text-sm font-semibold text-white opacity-40">Terbitkan Kurikulum</button></div>
        </div>

        {{-- Detail modal contains only data backed by the existing class schema. --}}
        <div x-show="detailOpen" x-transition.opacity style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 p-4" @click.self="detailOpen=false">
            <section role="dialog" aria-modal="true" aria-labelledby="class-detail-preview-title" class="w-full max-w-xl rounded-2xl bg-white p-5 shadow-xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-2"><div><h2 id="class-detail-preview-title" class="text-lg font-bold dark:text-white">Detail Kelas</h2><p class="text-xs text-zinc-500">Data nyata dari sistem lama</p></div><button type="button" @click="detailOpen=false" aria-label="Tutup detail kelas" class="rounded-lg px-3 py-1 text-xl text-zinc-500">×</button></div>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2"><div><dt class="text-xs text-zinc-500">Nama kelas</dt><dd class="mt-1 font-semibold dark:text-white" x-text="chosenClass?.name || 'Belum tercatat'"></dd></div><div><dt class="text-xs text-zinc-500">Materi legasi</dt><dd class="mt-1 font-semibold dark:text-white" x-text="chosenClass?.subject || 'Belum tercatat'"></dd></div><div><dt class="text-xs text-zinc-500">Jumlah peserta</dt><dd class="mt-1 font-semibold dark:text-white" x-text="chosenClass?.student_count ?? 'Belum tercatat'"></dd></div><div><dt class="text-xs text-zinc-500">Jumlah sesi tercatat</dt><dd class="mt-1 font-semibold dark:text-white" x-text="chosenClass?.schedules_count ?? 'Belum tercatat'"></dd></div><div><dt class="text-xs text-zinc-500">Program / kurikulum / periode</dt><dd class="mt-1 text-sm text-amber-700">Belum tercatat pada skema kelas lama</dd></div><div><dt class="text-xs text-zinc-500">Status persetujuan jadwal</dt><dd class="mt-1 text-sm text-amber-700">Tidak disimpulkan dari jumlah sesi</dd></div></dl>
                <div class="mt-5 flex justify-end gap-2"><button type="button" @click="detailOpen=false" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm dark:border-zinc-700">Tutup</button><button type="button" @click="editDetail()" class="rounded-xl border border-blue-200 px-4 py-2 text-sm font-semibold text-[#1565D8]">Edit (Preview)</button></div>
            </section>
        </div>
        <div x-show="competencyOpen" x-transition.opacity style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 p-4" @click.self="competencyOpen=false">
            <section role="dialog" aria-modal="true" aria-labelledby="competency-preview-title" class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl dark:bg-zinc-900"><div class="flex items-center justify-between gap-2"><h2 id="competency-preview-title" class="text-lg font-bold dark:text-white">Kelola Kompetensi</h2><button type="button" @click="competencyOpen=false" aria-label="Tutup modal kompetensi" class="text-xl text-zinc-500">×</button></div><p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">Modal ini adalah pintu masuk katalog kompetensi bersama modul Instruktur. Katalog riil dan fitur pengelolaannya menunggu kontrak backend agar tidak membuat data kompetensi duplikat.</p><button type="button" @click="competencyOpen=false" class="mt-5 rounded-xl bg-[#1565D8] px-4 py-2 text-sm font-semibold text-white">Tutup</button></section>
        </div>
    </div>
</x-layouts::app>
