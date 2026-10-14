{{-- Frontend only: shared Create/Edit modal; never submits or persists data. --}}
<dialog x-ref="roomFormDialog" aria-labelledby="room-form-heading" class="max-h-[90vh] w-[min(94vw,34rem)] overflow-y-auto rounded-2xl border border-zinc-200 bg-white p-0 text-left text-zinc-900 shadow-2xl backdrop:bg-zinc-900/60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
    <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <div>
            <h2 id="room-form-heading" class="text-lg font-bold" x-text="mode === 'edit' ? 'Edit Ruangan — ' + (selected?.name ?? '') : 'Tambah Ruangan'"></h2>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Isi informasi ruangan untuk digunakan dalam kegiatan belajar mengajar.</p>
        </div>
        <button type="button" @click="closeForm()" aria-label="Tutup formulir ruangan" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800">✕</button>
    </div>
    <div class="space-y-4 px-5 py-5">
        <p role="status" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">Pratinjau frontend: penyimpanan belum terhubung ke backend. Data yang diubah tidak disimpan.</p>
        <label class="flex flex-col gap-1.5 text-sm font-medium">
            <span>Nama ruangan <span class="text-rose-600">*</span></span>
            <input type="text" x-model="form.name" required maxlength="150" placeholder="Contoh: Lab Komputer 1" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
        </label>
        <fieldset>
            <legend class="mb-2 text-sm font-medium">Bidang pembelajaran <span class="text-rose-600">*</span></legend>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="flex cursor-pointer items-center gap-2"><input type="radio" name="room-preview-department" value="computer" x-model="form.department" class="accent-[#1565D8]" /> Komputer</label>
                <label class="flex cursor-pointer items-center gap-2"><input type="radio" name="room-preview-department" value="speakout" x-model="form.department" class="accent-[#1565D8]" /> SpeakOut</label>
            </div>
            <p x-show="mode === 'edit'" class="mt-2 text-xs text-amber-700 dark:text-amber-300">Bidang ruangan belum tercatat pada database lama. Pilih bidang sesuai kondisi sebenarnya.</p>
        </fieldset>
        <label class="flex flex-col gap-1.5 text-sm font-medium">
            <span>Kapasitas maksimal <span class="text-rose-600">*</span></span>
            <input type="number" x-model="form.capacity" min="1" step="1" required placeholder="Contoh: 20" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
            <span class="text-xs font-normal text-zinc-500">Jumlah maksimal siswa yang dapat ditampung secara efektif.</span>
        </label>
        <fieldset>
            <legend class="mb-2 text-sm font-medium">Status operasional <span class="text-rose-600">*</span></legend>
            <div class="space-y-2 text-sm">
                <label class="flex cursor-pointer items-center gap-2"><input type="radio" name="room-preview-status" value="available" x-model="form.status" class="accent-[#1565D8]" /> Operasional</label>
                <label class="flex cursor-pointer items-center gap-2"><input type="radio" name="room-preview-status" value="maintenance" x-model="form.status" class="accent-[#1565D8]" /> Maintenance / Perawatan</label>
                <label class="flex cursor-pointer items-center gap-2"><input type="radio" name="room-preview-status" value="unavailable" x-model="form.status" class="accent-[#1565D8]" /> Tidak Operasional</label>
            </div>
        </fieldset>
        <label class="flex flex-col gap-1.5 text-sm font-medium">
            <span>Catatan (opsional)</span>
            <textarea x-model="form.notes" rows="3" maxlength="500" placeholder="Contoh: Tersedia komputer, internet, AC, dan proyektor." class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"></textarea>
            <span class="text-xs font-normal text-zinc-500">Informasi tambahan mengenai fasilitas atau kondisi ruangan, bukan persyaratan otomatis penjadwalan.</span>
            <span x-show="mode === 'edit'" class="text-xs font-normal text-amber-700 dark:text-amber-300">Catatan data lama belum tersedia dalam database.</span>
        </label>
    </div>
    <footer class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <button type="button" @click="closeForm()" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200">Batal</button>
        <button type="button" disabled aria-disabled="true" title="Menunggu backend penyimpanan dari Anggota 2" class="cursor-not-allowed rounded-xl bg-[#1565D8] px-4 py-2 text-sm font-semibold text-white opacity-50" x-text="mode === 'edit' ? 'Simpan Perubahan' : 'Simpan Ruangan'"></button>
    </footer>
</dialog>

{{-- Frontend only: data already stored in the current rooms table. --}}
<dialog x-ref="roomDetailDialog" aria-labelledby="room-detail-heading" class="max-h-[85vh] w-[min(94vw,34rem)] overflow-y-auto rounded-2xl border border-zinc-200 bg-white p-0 text-left text-zinc-900 shadow-2xl backdrop:bg-zinc-900/60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
    <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <div>
            <h2 id="room-detail-heading" class="text-lg font-bold" x-text="'Detail Ruangan — ' + (selected?.name ?? '')"></h2>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Data yang benar-benar tercatat di sistem.</p>
        </div>
        <button type="button" @click="closeDetail()" aria-label="Tutup detail ruangan" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800">✕</button>
    </div>
    <dl class="grid gap-5 px-5 py-5 text-sm sm:grid-cols-2">
        <div><dt class="text-xs font-semibold text-zinc-500">Nama ruangan</dt><dd class="mt-1 font-semibold break-words" x-text="selected?.name ?? '—'"></dd></div>
        <div><dt class="text-xs font-semibold text-zinc-500">Status operasional</dt><dd class="mt-1" x-text="statusLabel(selected?.status)"></dd></div>
        <div><dt class="text-xs font-semibold text-zinc-500">Bidang pembelajaran</dt><dd class="mt-1 text-amber-700 dark:text-amber-300">Belum tercatat</dd></div>
        <div><dt class="text-xs font-semibold text-zinc-500">Kapasitas maksimal</dt><dd class="mt-1"><span x-text="selected?.capacity ?? '—'"></span> siswa</dd></div>
        <div class="sm:col-span-2"><dt class="text-xs font-semibold text-zinc-500">Catatan</dt><dd class="mt-1 text-amber-700 dark:text-amber-300">Belum tercatat</dd></div>
    </dl>
    <footer class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <button type="button" @click="closeDetail()" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200">Tutup</button>
        <button type="button" @click="const room = selected; closeDetail(); openEdit(room)" class="rounded-xl bg-[#1565D8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0A3D91]">Edit Ruangan</button>
    </footer>
</dialog>
