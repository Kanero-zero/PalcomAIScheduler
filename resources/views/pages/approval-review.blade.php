<x-layouts::app :title="__('Approval / Reject')">
    @php
        // HANYA DATA SIMULASI. Tidak membaca/menulis keputusan asli atau jadwal produksi.
        $demoCases = [
            [
                'id' => 'DEMO-001',
                'instructor' => 'Wahyu',
                'class' => 'Microsoft Excel - Reguler Siang',
                'date' => '15 Oktober 2026',
                'time' => '13.00 - 15.00 WIB',
                'room' => 'Lab 2',
                'leave_reason' => 'Urusan keluarga',
                'candidates' => [
                    ['id' => 'ins-2', 'name' => 'Kanero', 'eligible' => true, 'detail' => 'Kompetensi Excel sesuai; jadwal simulasi tersedia.'],
                    ['id' => 'ins-3', 'name' => 'Rizky Pratama', 'eligible' => true, 'detail' => 'Kompetensi Office sesuai; tidak ada bentrok pada data simulasi.'],
                    ['id' => 'ins-4', 'name' => 'Dina Oktavia', 'eligible' => false, 'detail' => 'Tidak memenuhi kriteria kompetensi Excel pada simulasi.'],
                ],
            ],
            [
                'id' => 'DEMO-002',
                'instructor' => 'Dina Oktavia',
                'class' => 'Desain Grafis - Reguler Pagi',
                'date' => '16 Oktober 2026',
                'time' => '09.00 - 11.00 WIB',
                'room' => 'Lab 1',
                'leave_reason' => 'Keperluan pribadi',
                'candidates' => [
                    ['id' => 'ins-3', 'name' => 'Rizky Pratama', 'eligible' => false, 'detail' => 'Bentrok kelas lain pada data simulasi.'],
                    ['id' => 'ins-5', 'name' => 'Budi Santoso', 'eligible' => false, 'detail' => 'Belum memenuhi kriteria kompetensi pada simulasi.'],
                ],
            ],
        ];
    @endphp

    <div
        class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 py-2 sm:p-4"
        x-data="{
            cases: @js($demoCases),
            selectedCaseId: 'DEMO-001',
            selectedCandidateId: null,
            decisions: {},
            dialog: null,
            rejectReason: '',
            get caseData() {
                return this.cases.find(item => item.id === this.selectedCaseId) || this.cases[0];
            },
            get decision() {
                return this.decisions[this.selectedCaseId] || null;
            },
            get validCount() {
                return this.caseData.candidates.filter(item => item.eligible).length;
            },
            get selectedCandidate() {
                return this.caseData.candidates.find(item => item.id === this.selectedCandidateId && item.eligible) || null;
            },
            chooseCase(id) {
                this.selectedCaseId = id;
                this.selectedCandidateId = null;
                this.rejectReason = '';
                this.dialog = null;
            },
            openApproval() {
                if (!this.selectedCandidate || this.decision) return;
                this.dialog = 'approve';
            },
            openRejection() {
                if (this.decision) return;
                this.rejectReason = '';
                this.dialog = 'reject';
            },
            closeDialog() {
                this.dialog = null;
                this.rejectReason = '';
            },
            confirmApproval() {
                if (!this.selectedCandidate || this.decision || this.dialog !== 'approve') return;
                this.decisions = {
                    ...this.decisions,
                    [this.selectedCaseId]: { status: 'approved', candidateName: this.selectedCandidate.name, reason: '' }
                };
                this.closeDialog();
            },
            confirmRejection() {
                if (this.decision || this.dialog !== 'reject' || this.rejectReason.trim().length < 10) return;
                this.decisions = {
                    ...this.decisions,
                    [this.selectedCaseId]: { status: 'rejected', candidateName: '', reason: this.rejectReason.trim() }
                };
                this.closeDialog();
            },
            resetPreview() {
                const updated = { ...this.decisions };
                delete updated[this.selectedCaseId];
                this.decisions = updated;
                this.selectedCandidateId = null;
                this.closeDialog();
            }
        }"
    >
        <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-[#1565D8] dark:text-blue-300">PALCOM AI SCHEDULER</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl dark:text-white">Approval / Reject</h1>
                <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-400">Pratinjau alur persetujuan instruktur pengganti oleh admin.</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                <flux:icon name="information-circle" class="size-4" />
                Pratinjau - Data simulasi
            </span>
        </header>

        <div class="rounded-2xl border border-blue-200 bg-blue-50/70 p-4 dark:border-blue-900 dark:bg-blue-950/20">
            <div class="flex items-start gap-3">
                <flux:icon name="shield-check" class="mt-0.5 size-5 shrink-0 text-[#1565D8] dark:text-blue-300" />
                <div>
                    <p class="text-sm font-semibold text-blue-950 dark:text-blue-200">Frontend simulasi - belum terhubung ke backend Approval/Reject</p>
                    <p class="mt-1 text-xs leading-5 text-blue-900 dark:text-blue-300">Tombol dan dialog dapat dicoba, tetapi keputusan hanya disimpan sementara di browser hingga halaman dimuat ulang. Tidak mengubah jadwal, izin instruktur, database, maupun Activity Log asli.</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(280px,360px)]">
            <div class="flex min-w-0 flex-col gap-6">
                <section aria-label="Pilih pengajuan izin" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">1. Pilih Pengajuan Izin</h2>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Pilih salah satu skenario untuk meninjau kandidat pengganti.</p>
                        </div>
                        <label class="block w-full text-xs font-medium text-zinc-700 sm:w-64 dark:text-zinc-300">
                            <span class="mb-1 block">Pengajuan simulasi</span>
                            <select x-model="selectedCaseId" @change="chooseCase($event.target.value)" aria-label="Pilih pengajuan simulasi" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                <template x-for="item in cases" :key="item.id">
                                    <option :value="item.id" x-text="item.id + ' - ' + item.instructor"></option>
                                </template>
                            </select>
                        </label>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Kelas terdampak</p>
                            <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white" x-text="caseData.class"></p>
                            <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300"><span x-text="caseData.date"></span> · <span x-text="caseData.time"></span></p>
                        </div>
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Instruktur izin</p>
                            <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white" x-text="caseData.instructor"></p>
                            <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300"><span x-text="caseData.room"></span> · <span x-text="caseData.leave_reason"></span></p>
                        </div>
                    </div>
                </section>

                <section aria-label="Daftar kandidat pengganti" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">2. Pilih Instruktur Pengganti</h2>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Hanya kandidat yang memenuhi syarat simulasi yang dapat dipilih.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-[#1565D8] dark:bg-blue-950 dark:text-blue-300"><span x-text="validCount"></span> kandidat valid</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        <template x-for="candidate in caseData.candidates" :key="candidate.id">
                            <button type="button"
                                :disabled="!candidate.eligible || !!decision"
                                @click="selectedCandidateId = candidate.id"
                                :aria-pressed="selectedCandidateId === candidate.id"
                                :class="selectedCandidateId === candidate.id && candidate.eligible ? 'border-[#1565D8] bg-blue-50/80 ring-2 ring-blue-100 dark:border-blue-400 dark:bg-blue-950/30' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900'"
                                class="flex w-full min-w-0 items-start gap-3 rounded-xl border p-4 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-65 hover:border-blue-300 dark:hover:border-blue-700">
                                <span :class="selectedCandidateId === candidate.id && candidate.eligible ? 'border-[#1565D8] bg-[#1565D8] text-white' : 'border-zinc-300 text-transparent dark:border-zinc-600'" class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full border">
                                    <flux:icon name="check" class="size-3.5" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-zinc-900 dark:text-white" x-text="candidate.name"></span>
                                        <span x-show="candidate.eligible" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Valid</span>
                                        <span x-show="!candidate.eligible" class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-300">Tidak memenuhi syarat</span>
                                    </span>
                                    <span class="mt-1 block text-xs leading-5 text-zinc-600 dark:text-zinc-300" x-text="candidate.detail"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p x-show="validCount === 0" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">Tidak ada kandidat valid pada skenario ini. Persetujuan dinonaktifkan; admin dapat meninjau atau menolak usulan simulasi.</p>
                </section>
            </div>

            <aside aria-label="Panel keputusan admin" class="min-w-0 self-start rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900 xl:sticky xl:top-6">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-white">3. Keputusan Admin</h2>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Periksa kandidat sebelum memilih tindakan.</p>

                <div aria-live="polite" class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Status keputusan (simulasi)</p>
                    <template x-if="!decision">
                        <p class="mt-1 text-sm font-semibold text-amber-700 dark:text-amber-300">Menunggu keputusan</p>
                    </template>
                    <template x-if="decision && decision.status === 'approved'">
                        <div class="mt-2 space-y-2">
                            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Disetujui - hanya simulasi</p>
                            <p class="text-xs text-zinc-700 dark:text-zinc-300">Instruktur: <strong x-text="decision.candidateName"></strong></p>
                        </div>
                    </template>
                    <template x-if="decision && decision.status === 'rejected'">
                        <div class="mt-2 space-y-2">
                            <p class="text-sm font-semibold text-rose-700 dark:text-rose-300">Ditolak - hanya simulasi</p>
                            <p class="text-xs text-zinc-700 dark:text-zinc-300" x-text="decision.reason"></p>
                        </div>
                    </template>
                </div>

                <div class="mt-4 space-y-2">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Kandidat terpilih</p>
                    <p class="rounded-xl border border-zinc-200 p-3 text-sm font-semibold text-zinc-900 dark:border-zinc-700 dark:text-white" x-text="selectedCandidate ? selectedCandidate.name : 'Belum memilih kandidat'"></p>
                    <button type="button" @click="openApproval()" :disabled="!selectedCandidate || !!decision" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#1565D8] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#0A3D91] disabled:cursor-not-allowed disabled:bg-zinc-300 disabled:text-zinc-600 dark:disabled:bg-zinc-700 dark:disabled:text-zinc-400">
                        <flux:icon name="check-circle" class="size-4" /> Setujui Rekomendasi
                    </button>
                    <button type="button" @click="openRejection()" :disabled="!!decision" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-rose-300 bg-white px-4 py-3 text-sm font-semibold text-rose-700 transition-colors hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-900 dark:bg-zinc-900 dark:text-rose-300">
                        <flux:icon name="x-circle" class="size-4" /> Tolak Rekomendasi
                    </button>
                    <button type="button" x-show="decision" @click="resetPreview()" class="w-full rounded-xl px-4 py-2 text-xs font-semibold text-[#1565D8] hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950/30">Ulangi simulasi kasus ini</button>
                </div>
                <p class="mt-4 border-t border-zinc-200 pt-4 text-xs leading-5 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">Keputusan tidak dipublikasikan dan tidak tercatat di Activity Log. Penyimpanan nyata akan dihubungkan setelah backend dari Anggota 2 selesai.</p>
            </aside>
        </div>

        <div x-cloak x-show="dialog !== null" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeDialog()">
            <div class="absolute inset-0 bg-zinc-950/70" @click="closeDialog()"></div>
            <div role="dialog" aria-modal="true" :aria-label="dialog === 'approve' ? 'Konfirmasi persetujuan simulasi' : 'Konfirmasi penolakan simulasi'" class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl sm:p-6 dark:bg-zinc-900">
                <div x-show="dialog === 'approve'">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Konfirmasi Persetujuan (Simulasi)</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">Anda akan menyetujui <strong x-text="selectedCandidate ? selectedCandidate.name : '-' "></strong> sebagai instruktur pengganti untuk <strong x-text="caseData.class"></strong>. Tidak ada data nyata yang berubah.</p>
                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeDialog()" class="rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">Batal</button>
                        <button type="button" @click="confirmApproval()" class="rounded-xl bg-[#1565D8] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0A3D91]">Konfirmasi Simulasi</button>
                    </div>
                </div>
                <div x-show="dialog === 'reject'">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Tolak Rekomendasi (Simulasi)</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Berikan alasan penolakan usulan instruktur pengganti. Ini bukan penolakan pengajuan izin instruktur.</p>
                    <label for="simulated-reject-reason" class="mt-4 block text-sm font-semibold text-zinc-800 dark:text-zinc-200">Alasan penolakan <span class="text-rose-600">*</span></label>
                    <textarea id="simulated-reject-reason" x-model="rejectReason" rows="4" maxlength="500" placeholder="Contoh: perlu pengecekan ulang ketersediaan kandidat pengganti..." class="mt-2 block w-full rounded-xl border border-zinc-300 bg-white p-3 text-sm text-zinc-900 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"></textarea>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Minimal 10 karakter. <span x-text="rejectReason.trim().length"></span>/500</p>
                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeDialog()" class="rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">Batal</button>
                        <button type="button" @click="confirmRejection()" :disabled="rejectReason.trim().length < 10" class="rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800 disabled:cursor-not-allowed disabled:opacity-40">Konfirmasi Penolakan</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>