    <div
        class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 py-2 sm:p-4"
        x-data="{
            selectedCandidateId: null,
            dialog: null,
            rejectReason: '',
            submitting: false,
            requestError: '',
            get caseData() {
                const result = $wire.result;
                const schedule = result?.affected_schedules.find(item => item.schedule_id === $wire.selectedScheduleId);
                return {
                    class: schedule?.class_name || '-',
                    date: schedule?.date || '-',
                    time: schedule ? schedule.start_time + ' - ' + schedule.end_time : '-',
                    room: schedule?.room?.room_name || 'Ruangan belum tersedia',
                    roomCanApprove: !!schedule?.room?.is_valid && !schedule?.room?.requires_room_change,
                    instructor: result?.instructor_name || '-',
                    leave_reason: result?.leave_reason || '-',
                    candidates: [...(schedule?.valid_candidates || []), ...(schedule?.disqualified_candidates || [])].map(item => ({
                        id: item.instructor_id,
                        name: item.instructor_name,
                        eligible: item.is_valid,
                        detail: [item.disqualification_reason, ...item.reasons].filter(Boolean).join(' ')
                    })),
                    approval: schedule?.approval_status,
                };
            },
            get decision() {
                return this.caseData.approval?.is_decided ? this.caseData.approval : null;
            },
            get validCount() {
                return this.caseData.candidates.filter(item => item.eligible).length;
            },
            get selectedCandidate() {
                return this.caseData.candidates.find(item => item.id === this.selectedCandidateId && item.eligible) || null;
            },
            resetSelection() {
                this.selectedCandidateId = null;
                this.rejectReason = '';
                this.dialog = null;
                this.requestError = '';
            },
            openApproval() {
                if (!this.selectedCandidate || !this.caseData.roomCanApprove || this.decision || this.submitting) return;
                this.dialog = 'approve';
            },
            openRejection() {
                if (this.decision || this.submitting || !$wire.selectedScheduleId) return;
                this.rejectReason = '';
                this.dialog = 'reject';
            },
            closeDialog() {
                if (this.submitting) return;
                this.dialog = null;
                this.rejectReason = '';
            },
            async confirmApproval() {
                if (!this.selectedCandidate || !this.caseData.roomCanApprove || this.decision || this.dialog !== 'approve' || this.submitting) return;
                this.submitting = true;
                this.requestError = '';
                try {
                    const response = await $wire.approveSubstitution($wire.selectedLeaveId, $wire.selectedScheduleId, this.selectedCandidate.id);
                    if (response?.success) this.resetSelection();
                } catch (error) {
                    this.requestError = 'Keputusan belum dapat dikonfirmasi. Muat ulang data untuk memeriksa status sebelum mencoba kembali.';
                } finally {
                    this.submitting = false;
                }
            },
            async confirmRejection() {
                if (this.decision || this.dialog !== 'reject' || this.rejectReason.trim().length < 10 || this.rejectReason.trim().length > 500 || this.submitting) return;
                this.submitting = true;
                this.requestError = '';
                try {
                    const response = await $wire.rejectSubstitution($wire.selectedLeaveId, $wire.selectedScheduleId, this.rejectReason);
                    if (response?.success) this.resetSelection();
                } catch (error) {
                    this.requestError = 'Keputusan belum dapat dikonfirmasi. Muat ulang data untuk memeriksa status sebelum mencoba kembali.';
                } finally {
                    this.submitting = false;
                }
            }
        }"
    >
        @if ($feedbackMessage)
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ $feedbackMessage }}</div>
        @endif
            @if ($errors->any())
                <div role="alert" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
                    @foreach ($errors->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif
            <p x-show="requestError" x-text="requestError" role="alert" class="mt-3 text-sm text-rose-700 dark:text-rose-300"></p>
        <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-[#1565D8] dark:text-blue-300">PALCOM AI SCHEDULER</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl dark:text-white">Approval / Reject</h1>
                <p class="mt-1 max-w-2xl text-sm text-zinc-600 dark:text-zinc-400">Tinjau dan putuskan instruktur pengganti untuk setiap jadwal kelas terdampak.</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                <flux:icon name="information-circle" class="size-4" />
                Data pengajuan izin
            </span>
        </header>

        <div class="rounded-2xl border border-blue-200 bg-blue-50/70 p-4 dark:border-blue-900 dark:bg-blue-950/20">
            <div class="flex items-start gap-3">
                <flux:icon name="shield-check" class="mt-0.5 size-5 shrink-0 text-[#1565D8] dark:text-blue-300" />
                <div>
                    <p class="text-sm font-semibold text-blue-950 dark:text-blue-200">Keputusan berlaku per jadwal kelas</p>
                    <p class="mt-1 text-xs leading-5 text-blue-900 dark:text-blue-300">Pilih pengajuan izin, lalu kelas terdampak. Persetujuan memperbarui instruktur pada jadwal terpilih; penolakan tidak mengubah jadwal atau status izin induk.</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(280px,360px)]">
            <div class="flex min-w-0 flex-col gap-6">
                <section aria-label="Pilih pengajuan izin" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">1. Pilih Pengajuan Izin</h2>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Pilih pengajuan izin untuk meninjau kandidat pengganti.</p>
                        </div>
                        <label class="block w-full text-xs font-medium text-zinc-700 sm:w-64 dark:text-zinc-300">
                            <span class="mb-1 block">Pengajuan izin</span>
                            <select :value="$wire.selectedLeaveId" @change="resetSelection(); $wire.selectLeave(Number($event.target.value))" wire:loading.attr="disabled" :disabled="submitting" aria-label="Pilih pengajuan izin" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                @forelse ($leaves as $leave)
                                    <option wire:key="leave-{{ $leave->id }}" value="{{ $leave->id }}" @selected($selectedLeaveId === $leave->id)>#{{ $leave->id }} - {{ $leave->instructor->name }} · {{ $leave->date->format('d/m/Y') }}</option>
                                @empty
                                    <option value="">Belum ada pengajuan izin</option>
                                @endforelse
                            </select>
                        </label>
                    </div>
                    <label class="mt-4 block text-xs font-medium text-zinc-700 dark:text-zinc-300">
                        <span class="mb-1 block">Kelas terdampak — pilih jadwal untuk diputuskan</span>
                        <select :value="$wire.selectedScheduleId" @change="resetSelection(); $wire.selectSchedule(Number($event.target.value))" wire:loading.attr="disabled" :disabled="submitting || !$wire.selectedScheduleId" aria-label="Pilih kelas terdampak" class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            @forelse ($result['affected_schedules'] ?? [] as $schedule)
                                <option wire:key="schedule-{{ $schedule['schedule_id'] }}" value="{{ $schedule['schedule_id'] }}" @selected($selectedScheduleId === $schedule['schedule_id'])>{{ $schedule['class_name'] }} · {{ $schedule['start_time'] }}–{{ $schedule['end_time'] }} · {{ ['pending' => 'Menunggu keputusan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$schedule['approval_status']['status']] }}</option>
                            @empty
                                <option value="">Tidak ada kelas terdampak</option>
                            @endforelse
                        </select>
                    </label>
                    @if ($result && empty($result['affected_schedules']))
                        <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">Tidak ada kelas terdampak pada pengajuan izin ini.</p>
                    @elseif (! $result)
                        <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">Belum ada pengajuan izin yang dapat ditinjau.</p>
                    @endif
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
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Hanya kandidat yang memenuhi syarat Scheduling Engine yang dapat dipilih.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-[#1565D8] dark:bg-blue-950 dark:text-blue-300"><span x-text="validCount"></span> kandidat valid</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        <template x-for="candidate in caseData.candidates" :key="candidate.id">
                            <button type="button"
                                :disabled="!candidate.eligible || !!decision || submitting" wire:loading.attr="disabled"
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
                    <p x-show="$wire.selectedScheduleId &amp;&amp; validCount === 0" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">Tidak ada kandidat valid untuk jadwal ini. Persetujuan dinonaktifkan; admin dapat meninjau atau menolak rekomendasi.</p>
                </section>
            </div>

            <aside aria-label="Panel keputusan admin" class="min-w-0 self-start rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900 xl:sticky xl:top-6">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-white">3. Keputusan Admin</h2>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Periksa kandidat sebelum memilih tindakan.</p>

                <div aria-live="polite" class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Status keputusan</p>
                    <template x-if="!decision &amp;&amp; $wire.selectedScheduleId">
                        <p class="mt-1 text-sm font-semibold text-amber-700 dark:text-amber-300">Menunggu keputusan</p>
                    </template>
                    <template x-if="decision && decision.status === 'approved'">
                        <div class="mt-2 space-y-2">
                            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Disetujui</p>
                            <p class="text-xs text-zinc-700 dark:text-zinc-300">Instruktur: <strong x-text="decision.replacement_instructor_name"></strong></p>
                        </div>
                    </template>
                    <template x-if="decision && decision.status === 'rejected'">
                        <div class="mt-2 space-y-2">
                            <p class="text-sm font-semibold text-rose-700 dark:text-rose-300">Ditolak</p>
                            <p class="text-xs text-zinc-700 dark:text-zinc-300" x-text="decision.rejection_reason"></p>
                        </div>
                    </template>
                </div>

                <template x-if="decision">
                    <div class="mt-3 space-y-1 text-xs text-zinc-600 dark:text-zinc-300">
                        <p>Admin: <span x-text="decision.decision_by_name || '-' "></span></p>
                        <p>Waktu keputusan: <span x-text="decision.decision_at ? new Date(decision.decision_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' }) + ' WIB' : '-' "></span></p>
                    </div>
                </template>
                @php
                    $selectedSchedule = collect($result['affected_schedules'] ?? [])->firstWhere('schedule_id', $selectedScheduleId);
                @endphp
                @if ($selectedSchedule && (! ($selectedSchedule['room']['is_valid'] ?? false) || ($selectedSchedule['room']['requires_room_change'] ?? false)))
                    <p class="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-200">Persetujuan ditunda: {{ $selectedSchedule['room']['notes'] ?? 'Jadwal belum memiliki ruangan yang valid.' }} Selesaikan kendala ruangan sebelum menyetujui.</p>
                @endif
                @can('manage-schedule-approval')
                <div class="mt-4 space-y-2">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Kandidat terpilih</p>
                    <p class="rounded-xl border border-zinc-200 p-3 text-sm font-semibold text-zinc-900 dark:border-zinc-700 dark:text-white" x-text="selectedCandidate ? selectedCandidate.name : 'Belum memilih kandidat'"></p>
                    <button type="button" @click="openApproval()" :disabled="!selectedCandidate || !caseData.roomCanApprove || !!decision || submitting || !$wire.selectedScheduleId" wire:loading.attr="disabled" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#1565D8] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#0A3D91] disabled:cursor-not-allowed disabled:bg-zinc-300 disabled:text-zinc-600 dark:disabled:bg-zinc-700 dark:disabled:text-zinc-400">
                        <flux:icon name="check-circle" class="size-4" /> Setujui Rekomendasi
                    </button>
                    <button type="button" @click="openRejection()" :disabled="!!decision || submitting || !$wire.selectedScheduleId" wire:loading.attr="disabled" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-rose-300 bg-white px-4 py-3 text-sm font-semibold text-rose-700 transition-colors hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-900 dark:bg-zinc-900 dark:text-rose-300">
                        <flux:icon name="x-circle" class="size-4" /> Tolak Rekomendasi
                    </button>
                </div>
                @else
                    <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">Hanya admin berwenang yang dapat menyetujui atau menolak rekomendasi.</p>
                @endcan
                <button type="button" wire:click="loadEvaluation" @click="resetSelection()" wire:loading.attr="disabled" :disabled="submitting" class="mt-4 text-xs font-semibold text-[#1565D8] dark:text-blue-300">Muat ulang data</button>
                <p class="mt-4 border-t border-zinc-200 pt-4 text-xs leading-5 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">Keputusan disimpan untuk jadwal terpilih dan tidak dapat diputuskan ulang. Periksa detail sebelum mengonfirmasi.</p>
            </aside>
        </div>

        <div x-cloak x-show="dialog !== null" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeDialog()">
            <div class="absolute inset-0 bg-zinc-950/70" @click="closeDialog()"></div>
            <div role="dialog" aria-modal="true" :aria-label="dialog === 'approve' ? 'Konfirmasi persetujuan' : 'Konfirmasi penolakan'" class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl sm:p-6 dark:bg-zinc-900">
            @if ($errors->any())
                <div role="alert" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
                    @foreach ($errors->all() as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif
            <p x-show="requestError" x-text="requestError" role="alert" class="mt-3 text-sm text-rose-700 dark:text-rose-300"></p>
                <p x-show="submitting" role="status" class="mb-3 text-sm text-zinc-500">Menyimpan keputusan…</p>
                <div x-show="dialog === 'approve'">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Konfirmasi Persetujuan</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">Anda akan menyetujui <strong x-text="selectedCandidate ? selectedCandidate.name : '-' "></strong> sebagai instruktur pengganti untuk <strong x-text="caseData.class"></strong>. Jadwal terpilih akan diperbarui.</p>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300"><span x-text="caseData.date"></span> · <span x-text="caseData.time"></span> · <span x-text="caseData.room"></span></p>
                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeDialog()" :disabled="submitting" class="rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">Batal</button>
                        <button type="button" @click="confirmApproval()" :disabled="submitting" wire:loading.attr="disabled" class="rounded-xl bg-[#1565D8] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0A3D91]">Konfirmasi Persetujuan</button>
                    </div>
                </div>
                <div x-show="dialog === 'reject'">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Tolak Rekomendasi</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Berikan alasan penolakan usulan instruktur pengganti. Ini bukan penolakan pengajuan izin instruktur.</p>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300"><strong x-text="caseData.class"></strong> · <span x-text="caseData.date"></span> · <span x-text="caseData.time"></span></p>
                    <label for="reject-reason" class="mt-4 block text-sm font-semibold text-zinc-800 dark:text-zinc-200">Alasan penolakan <span class="text-rose-600">*</span></label>
                    <textarea id="reject-reason" x-model="rejectReason" :disabled="submitting" rows="4" maxlength="500" placeholder="Contoh: perlu pengecekan ulang ketersediaan kandidat pengganti..." class="mt-2 block w-full rounded-xl border border-zinc-300 bg-white p-3 text-sm text-zinc-900 focus:border-[#1565D8] focus:ring-2 focus:ring-blue-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"></textarea>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Minimal 10 dan maksimal 500 karakter. <span x-text="rejectReason.trim().length"></span>/500</p>
                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeDialog()" :disabled="submitting" class="rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">Batal</button>
                        <button type="button" @click="confirmRejection()" :disabled="submitting || rejectReason.trim().length < 10 || rejectReason.trim().length > 500" wire:loading.attr="disabled" class="rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-800 disabled:cursor-not-allowed disabled:opacity-40">Konfirmasi Penolakan</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
