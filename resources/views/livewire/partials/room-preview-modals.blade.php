{{-- Shared Create/Edit and Detail dialogs connected to Livewire RoomList backend --}}
<dialog x-ref="roomFormDialog" @cancel="$wire.closeForm()" aria-labelledby="room-form-heading" class="max-h-[90vh] w-[min(94vw,34rem)] overflow-y-auto rounded-2xl border border-zinc-200 bg-white p-0 text-left text-zinc-900 shadow-2xl backdrop:bg-zinc-900/60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
    <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <div>
            <h2 id="room-form-heading" class="text-lg font-bold" x-text="mode === 'edit' ? 'Edit Ruangan — ' + (selected?.name ?? '') : 'Tambah Ruangan'">{{ $mode === 'edit' ? 'Edit Ruangan' : 'Tambah Ruangan' }}</h2>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Isi informasi ruangan untuk digunakan dalam kegiatan belajar mengajar.</p>
        </div>
        <button type="button" @click="closeForm()" wire:click="closeForm" aria-label="Tutup formulir ruangan" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800">✕</button>
    </div>

    <form wire:submit.prevent="save">
        <div class="space-y-4 px-5 py-5">
            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200">
                    <p class="font-semibold">Penyimpanan ruangan tidak dapat diproses:</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                <span>Nama ruangan <span class="text-rose-600">*</span></span>
                <input type="text" wire:model="name" x-model="form.name" required maxlength="150" placeholder="Contoh: Lab Komputer 1" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                @error('name')
                    <span class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</span>
                @enderror
            </label>

            <fieldset>
                <legend class="mb-2 text-sm font-medium">Bidang pembelajaran <span class="text-rose-600">*</span></legend>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="room-form-department" value="computer" wire:model="formDepartment" x-model="form.department" class="accent-[#1565D8]" />
                        <span>Komputer</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="room-form-department" value="speakout" wire:model="formDepartment" x-model="form.department" class="accent-[#1565D8]" />
                        <span>SpeakOut</span>
                    </label>
                </div>
                <p x-show="mode === 'edit' && !form.department" class="mt-2 text-xs text-amber-700 dark:text-amber-300">Bidang ruangan belum tercatat pada database lama. Pilih bidang sesuai kondisi sebenarnya.</p>
                @error('formDepartment')
                    <span class="mt-1 block text-xs text-rose-600 dark:text-rose-400">{{ $message }}</span>
                @enderror
                @error('department')
                    <span class="mt-1 block text-xs text-rose-600 dark:text-rose-400">{{ $message }}</span>
                @enderror
            </fieldset>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                <span>Kapasitas maksimal <span class="text-rose-600">*</span></span>
                <input type="number" wire:model="capacity" x-model="form.capacity" min="1" step="1" required placeholder="Contoh: 20" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                <span class="text-xs font-normal text-zinc-500">Jumlah maksimal siswa yang dapat ditampung secara efektif.</span>
                @error('capacity')
                    <span class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</span>
                @enderror
            </label>

            <fieldset>
                <legend class="mb-2 text-sm font-medium">Status operasional <span class="text-rose-600">*</span></legend>
                <div class="space-y-2 text-sm">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="room-form-status" value="available" wire:model="formStatus" x-model="form.status" class="accent-[#1565D8]" />
                        <span>Operasional</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="room-form-status" value="maintenance" wire:model="formStatus" x-model="form.status" class="accent-[#1565D8]" />
                        <span>Maintenance / Perawatan</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" name="room-form-status" value="unavailable" wire:model="formStatus" x-model="form.status" class="accent-[#1565D8]" />
                        <span>Tidak Operasional</span>
                    </label>
                </div>
                @error('formStatus')
                    <span class="mt-1 block text-xs text-rose-600 dark:text-rose-400">{{ $message }}</span>
                @enderror
                @error('status')
                    <span class="mt-1 block text-xs text-rose-600 dark:text-rose-400">{{ $message }}</span>
                @enderror
            </fieldset>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                <span>Catatan (opsional)</span>
                <textarea wire:model="notes" x-model="form.notes" rows="3" maxlength="500" placeholder="Contoh: Tersedia komputer, internet, AC, dan proyektor." class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm focus:border-[#1565D8] focus:ring-2 focus:ring-blue-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"></textarea>
                <span class="text-xs font-normal text-zinc-500">Informasi tambahan mengenai fasilitas atau kondisi ruangan, bukan persyaratan otomatis penjadwalan.</span>
                <span x-show="mode === 'edit' && !form.notes" class="text-xs font-normal text-amber-700 dark:text-amber-300">Catatan data lama belum tersedia dalam database.</span>
                @error('notes')
                    <span class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</span>
                @enderror
            </label>
        </div>

        <footer class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
            <button type="button" @click="closeForm()" wire:click="closeForm" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200">Batal</button>
            <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-[#1565D8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0A3D91] disabled:opacity-50">
                <span wire:loading.remove x-text="mode === 'edit' ? 'Simpan Perubahan' : 'Simpan Ruangan'">{{ $mode === 'edit' ? 'Simpan Perubahan' : 'Simpan Ruangan' }}</span>
                <span wire:loading>Menyimpan...</span>
            </button>
        </footer>
    </form>
</dialog>

{{-- Detail dialog connected to Livewire/DB data --}}
<dialog x-ref="roomDetailDialog" @cancel="$wire.closeDetail()" aria-labelledby="room-detail-heading" class="max-h-[85vh] w-[min(94vw,34rem)] overflow-y-auto rounded-2xl border border-zinc-200 bg-white p-0 text-left text-zinc-900 shadow-2xl backdrop:bg-zinc-900/60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
    <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <div>
            <h2 id="room-detail-heading" class="text-lg font-bold" x-text="'Detail Ruangan — ' + (selected?.name ?? '')">Detail Ruangan — {{ $selectedRoom['name'] ?? '' }}</h2>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Data yang benar-benar tercatat di sistem.</p>
        </div>
        <button type="button" @click="closeDetail()" wire:click="closeDetail" aria-label="Tutup detail ruangan" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800">✕</button>
    </div>
    <dl class="grid gap-5 px-5 py-5 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-xs font-semibold text-zinc-500">Nama ruangan</dt>
            <dd class="mt-1 font-semibold break-words" x-text="selected?.name ?? '—'">{{ $selectedRoom['name'] ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-zinc-500">Status operasional</dt>
            <dd class="mt-1" x-text="statusLabel(selected?.status)">
                {{ match ($selectedRoom['status'] ?? '') { 'available' => 'Operasional', 'maintenance' => 'Perawatan', 'unavailable' => 'Tidak operasional', default => 'Status tidak dikenal' } }}
            </dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-zinc-500">Bidang pembelajaran</dt>
            <dd class="mt-1" :class="selected?.department ? 'font-medium text-zinc-900 dark:text-zinc-100' : 'text-amber-700 dark:text-amber-300'" x-text="selected?.department ? (selected.department === 'computer' ? 'Komputer' : 'SpeakOut') : 'Belum tercatat'">
                {{ !empty($selectedRoom['department']) ? ($selectedRoom['department'] === 'computer' ? 'Komputer' : 'SpeakOut') : 'Belum tercatat' }}
            </dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-zinc-500">Kapasitas maksimal</dt>
            <dd class="mt-1"><span x-text="selected?.capacity ?? '—'">{{ $selectedRoom['capacity'] ?? '—' }}</span> siswa</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-xs font-semibold text-zinc-500">Catatan</dt>
            <dd class="mt-1" :class="selected?.notes && selected.notes.trim() !== '' ? 'text-zinc-800 dark:text-zinc-200' : 'text-amber-700 dark:text-amber-300'" x-text="selected?.notes && selected.notes.trim() !== '' ? selected.notes : 'Belum ada catatan'">
                {{ !empty(trim($selectedRoom['notes'] ?? '')) ? $selectedRoom['notes'] : 'Belum ada catatan' }}
            </dd>
        </div>
    </dl>
    <footer class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <button type="button" @click="closeDetail()" wire:click="closeDetail" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200">Tutup</button>
        <button type="button" @click="const room = selected; closeDetail(); openEdit(room)" wire:click="openEdit({{ $selectedRoom['id'] ?? 0 }})" class="rounded-xl bg-[#1565D8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0A3D91]">Edit Ruangan</button>
    </footer>
</dialog>
