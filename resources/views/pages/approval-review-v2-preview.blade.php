<x-layouts::app :title="__('Approval / Reject — Preview')">
    {{-- F-07/P1-P4: isolated preview. All new-class records/decisions are simulated; legacy Livewire is preserved. --}}
    <main
        x-data="{
            activeTab: 'new',
            filterStatus: 'all',
            filterDepartment: 'all',
            searchQuery: '',
            selectedProposalId: null,
            reviewMode: null,
            reviewReason: '',
            proposals: [
                { id:'SIM-001', title:'Microsoft Excel — Reguler Sore', department:'computer', classKind:'regular', status:'pending', requester:'AO Demo A', submittedAt:'16 Okt 2026', students:10, meetings:8, period:'2–27 Nov 2026', option:'A', timeStart:'16:00', timeEnd:'18:00', demoInstructor:'Instruktur Demo A', demoRoom:'Lab Contoh 1', exampleDates:['2026-11-02','2026-11-04','2026-11-09','2026-11-11','2026-11-16','2026-11-18','2026-11-23','2026-11-25'] },
                { id:'SIM-002', title:'SpeakOut Conversation — Small Group', department:'speakout', classKind:'regular', status:'pending', requester:'AO Demo B', submittedAt:'15 Okt 2026', students:6, meetings:12, period:'3 Nov – 12 Des 2026', option:'B', timeStart:'17:00', timeEnd:'18:30', demoInstructor:'Instruktur Demo B', demoRoom:'Ruang Contoh S1', exampleDates:['2026-11-03','2026-11-05','2026-11-10','2026-11-12','2026-11-17','2026-11-19','2026-11-24','2026-11-26','2026-12-01','2026-12-03','2026-12-08','2026-12-10'] },
                { id:'SIM-003', title:'Web Programming — Intensif', department:'computer', classKind:'regular', status:'needs_revision', requester:'AO Demo A', submittedAt:'14 Okt 2026', students:12, meetings:8, period:'9 Nov – 5 Des 2026', option:'C', timeStart:'09:00', timeEnd:'11:00', demoInstructor:'Instruktur Demo C', demoRoom:'Lab Contoh 2', exampleDates:['2026-11-09','2026-11-12','2026-11-16','2026-11-19','2026-11-23','2026-11-26','2026-11-30','2026-12-03'] },
                { id:'SIM-004', title:'Microsoft Office — Privat', department:'computer', classKind:'private', status:'approved', requester:'AO Demo C', submittedAt:'13 Okt 2026', students:1, meetings:6, period:'2–21 Nov 2026', option:'A', timeStart:'10:00', timeEnd:'12:00', demoInstructor:'Instruktur Demo D', demoRoom:'Ruang Contoh P1', exampleDates:['2026-11-02','2026-11-04','2026-11-09','2026-11-11','2026-11-16','2026-11-18'] },
                { id:'SIM-005', title:'SpeakOut Basic — Sore', department:'speakout', classKind:'regular', status:'rejected', requester:'AO Demo B', submittedAt:'12 Okt 2026', students:4, meetings:8, period:'10 Nov – 11 Des 2026', option:'B', timeStart:'15:00', timeEnd:'16:30', demoInstructor:'Instruktur Demo E', demoRoom:'Ruang Contoh S2', exampleDates:['2026-11-10','2026-11-13','2026-11-17','2026-11-20','2026-11-24','2026-11-27','2026-12-01','2026-12-04'] }
            ],
            get stats() {
                return {
                    pending: this.proposals.filter(item => item.status === 'pending').length,
                    needs_revision: this.proposals.filter(item => item.status === 'needs_revision').length,
                    reviewed: this.proposals.filter(item => ['approved','rejected'].includes(item.status)).length
                };
            },
            get filteredProposals() {
                const needle = this.searchQuery.trim().toLocaleLowerCase('id-ID');
                return this.proposals.filter(item =>
                    (this.filterStatus === 'all' || item.status === this.filterStatus) &&
                    (this.filterDepartment === 'all' || item.department === this.filterDepartment) &&
                    (!needle || [item.id, item.title, item.requester].some(value => value.toLocaleLowerCase('id-ID').includes(needle)))
                );
            },
            selectedProposal() {
                return this.proposals.find(item => item.id === this.selectedProposalId) ?? null;
            },
            openProposal(id) {
                this.selectedProposalId = id;
                this.reviewMode = null;
                this.reviewReason = '';
            },
            closeProposal() {
                this.selectedProposalId = null;
                this.reviewMode = null;
                this.reviewReason = '';
            },
            previewReview(mode) {
                if (this.selectedProposal()?.status !== 'pending') return;
                if (!['approve','reject','revision'].includes(mode)) return;
                this.reviewMode = mode;
                this.reviewReason = '';
            },
            reviewLabel(mode) {
                return {approve:'Setujui',reject:'Tolak',revision:'Minta Revisi'}[mode] ?? 'Tidak ada keputusan';
            },
            formatExampleDate(iso) {
                const values = iso.split('-').map(Number);
                return new Intl.DateTimeFormat('id-ID', {weekday:'short',day:'numeric',month:'short',year:'numeric'}).format(new Date(values[0],values[1]-1,values[2],12));
            },
            exampleMeetings(proposal) {
                return (proposal?.exampleDates ?? []).map((iso,index) => ({
                    number:index+1,
                    iso,
                    date:this.formatExampleDate(iso),
                    time:proposal.timeStart + '–' + proposal.timeEnd,
                    instructor:proposal.demoInstructor,
                    room:proposal.demoRoom
                }));
            },
            exampleHistory(proposal) {
                if (!proposal) return [];
                const history = [{label:'Pengajuan contoh dibuat', actor:proposal.requester, date:proposal.submittedAt, note:'Catatan simulasi; tidak ada record proposal tersimpan.'}];
                if (proposal.status === 'needs_revision') history.push({label:'Contoh permintaan revisi', actor:'BAAK Demo', date:'15 Okt 2026', note:'Perlu penyesuaian jadwal (contoh alasan, bukan pemeriksaan resource).'});
                if (proposal.status === 'approved') history.push({label:'Contoh persetujuan', actor:'BAAK Demo', date:'14 Okt 2026', note:'Status ilustrasi, tidak membuat jadwal resmi.'});
                if (proposal.status === 'rejected') history.push({label:'Contoh penolakan', actor:'BAAK Demo', date:'13 Okt 2026', note:'Alasan penolakan contoh; tidak ada keputusan nyata.'});
                return history;
            },
            statusLabel(status) {
                return {pending:'Menunggu',needs_revision:'Perlu Revisi',approved:'Disetujui',rejected:'Ditolak'}[status] ?? 'Tidak diketahui';
            },
            statusClass(status) {
                return {
                    pending:'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
                    needs_revision:'bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-200',
                    approved:'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
                    rejected:'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-200'
                }[status] ?? 'bg-zinc-100 text-zinc-700';
            },
            departmentLabel(department) {
                return department === 'computer' ? 'Komputer' : 'SpeakOut';
            }
        }"
        class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 p-3 sm:gap-6 sm:p-6"
    >
        <header class="overflow-hidden rounded-2xl bg-[#0A3D91] text-white shadow-xs">
            <div class="flex flex-wrap items-start justify-between gap-4 p-5 sm:p-6">
                <div class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-200">SCHEDORA AI</p>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Approval / Reject</h1>
                    <p class="max-w-3xl text-sm leading-relaxed text-blue-100">
                        Tinjau pengajuan jadwal kelas baru serta keputusan pergantian instruktur yang mengajukan izin.
                    </p>
                </div>
                <span class="inline-flex shrink-0 items-center rounded-full bg-[#FFC928] px-3 py-1.5 text-xs font-bold text-[#0A3D91]">
                    UI PREVIEW
                </span>
            </div>
        </header>

        <div class="border-b border-zinc-200 dark:border-zinc-800">
            <nav role="tablist" aria-label="Tab Approval / Reject" class="flex flex-wrap gap-x-4 gap-y-1 sm:gap-x-6">
                <button
                    id="approval-v2-tab-new"
                    x-ref="tabNew"
                    type="button"
                    role="tab"
                    aria-controls="approval-v2-panel-new"
                    :aria-selected="activeTab === 'new'"
                    :tabindex="activeTab === 'new' ? 0 : -1"
                    @click="activeTab = 'new'"
                    @keydown.right.prevent="activeTab = 'leave'; $nextTick(() => $refs.tabLeave.focus())"
                    @keydown.end.prevent="activeTab = 'leave'; $nextTick(() => $refs.tabLeave.focus())"
                    :class="activeTab === 'new' ? 'border-[#1565D8] text-[#1565D8] dark:text-blue-300' : 'border-transparent text-zinc-600 hover:text-[#1565D8] dark:text-zinc-400'"
                    class="border-b-2 px-1 py-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1565D8]"
                >Pengajuan Jadwal Baru</button>
                <button
                    id="approval-v2-tab-leave"
                    x-ref="tabLeave"
                    type="button"
                    role="tab"
                    aria-controls="approval-v2-panel-leave"
                    :aria-selected="activeTab === 'leave'"
                    :tabindex="activeTab === 'leave' ? 0 : -1"
                    @click="activeTab = 'leave'"
                    @keydown.left.prevent="activeTab = 'new'; $nextTick(() => $refs.tabNew.focus())"
                    @keydown.home.prevent="activeTab = 'new'; $nextTick(() => $refs.tabNew.focus())"
                    :class="activeTab === 'leave' ? 'border-[#1565D8] text-[#1565D8] dark:text-blue-300' : 'border-transparent text-zinc-600 hover:text-[#1565D8] dark:text-zinc-400'"
                    class="border-b-2 px-1 py-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1565D8]"
                >Pengajuan Izin Instruktur</button>
            </nav>
        </div>

        <section
            id="approval-v2-panel-new"
            role="tabpanel"
            aria-labelledby="approval-v2-tab-new"
            x-show="activeTab === 'new'"
            class="min-w-0 space-y-5"
        >
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Antrean Pengajuan Jadwal Baru</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                            Contoh tampilan antrean BAAK: pengajuan AO untuk kelas Komputer dan SpeakOut, termasuk status serta opsi jadwal terpilih.
                        </p>
                    </div>
                    <span class="inline-flex items-center rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                        Belum terhubung ke backend V2
                    </span>
                </div>
                <p role="note" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm font-semibold leading-relaxed text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                    DATA SIMULASI — BUKAN PENGAJUAN ASLI. Semua nama, tanggal, status, dan jumlah berikut hanya untuk meninjau desain.
                </p>
                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3" aria-label="Statistik contoh pengajuan (bukan database)">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Menunggu Peninjauan (contoh)</p>
                        <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white" x-text="stats.pending"></p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Perlu Revisi (contoh)</p>
                        <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white" x-text="stats.needs_revision"></p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Selesai Ditinjau (contoh)</p>
                        <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white" x-text="stats.reviewed"></p>
                    </div>
                </div>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Angka di atas dihitung hanya dari data simulasi di browser; tidak mencerminkan antrean resmi.</p>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white">Daftar Pengajuan</h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Filter dan pencarian hanya menyaring lima kartu simulasi. Belum memakai database.</p>
                    </div>
                    <div class="grid w-full min-w-0 gap-3 sm:grid-cols-3 xl:w-auto">
                        <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-300">
                            Status
                            <select x-model="filterStatus" @change="closeProposal()" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                <option value="all">Semua status</option>
                                <option value="pending">Menunggu</option>
                                <option value="needs_revision">Perlu Revisi</option>
                                <option value="approved">Disetujui</option>
                                <option value="rejected">Ditolak</option>
                            </select>
                        </label>
                        <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-300">
                            Bidang
                            <select x-model="filterDepartment" @change="closeProposal()" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                <option value="all">Semua bidang</option>
                                <option value="computer">Komputer</option>
                                <option value="speakout">SpeakOut</option>
                            </select>
                        </label>
                        <label class="block text-xs font-medium text-zinc-600 dark:text-zinc-300">
                            Cari pengajuan
                            <input x-model.debounce.200ms="searchQuery" @input.debounce.200ms="closeProposal()" type="search" placeholder="ID, kelas, atau AO demo" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 placeholder:text-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </label>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400" aria-live="polite"><span class="font-bold" x-text="filteredProposals.length"></span> contoh ditampilkan</p>
                    <button type="button" @click="filterStatus = 'all'; filterDepartment = 'all'; searchQuery = ''; closeProposal()" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">Reset Filter</button>
                </div>
                <div class="mt-4 grid min-w-0 grid-cols-1 gap-3 lg:grid-cols-2" aria-label="Daftar contoh pengajuan jadwal baru">
                    <template x-for="item in filteredProposals" :key="item.id">
                        <article :class="selectedProposalId === item.id ? 'border-[#1565D8] ring-2 ring-blue-100 dark:ring-blue-900' : 'border-zinc-200 dark:border-zinc-700'" class="flex min-w-0 flex-col gap-3 rounded-xl border bg-white p-4 transition-shadow dark:bg-zinc-900">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs font-bold tracking-wide text-[#1565D8]" x-text="item.id"></span>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(item.status)" x-text="statusLabel(item.status)"></span>
                            </div>
                            <h4 class="text-base font-bold text-zinc-900 dark:text-white" x-text="item.title"></h4>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400"><span x-text="departmentLabel(item.department)"></span> · <span x-text="item.classKind === 'private' ? 'Privat' : 'Reguler'"></span> · <span x-text="item.requester"></span></p>
                            <dl class="grid grid-cols-2 gap-3 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800/50">
                                <div><dt class="text-xs text-zinc-500">Siswa</dt><dd class="mt-1 font-semibold dark:text-white" x-text="item.students + ' orang'"></dd></div>
                                <div><dt class="text-xs text-zinc-500">Pertemuan</dt><dd class="mt-1 font-semibold dark:text-white" x-text="item.meetings + ' kali'"></dd></div>
                                <div><dt class="text-xs text-zinc-500">Periode</dt><dd class="mt-1 font-semibold dark:text-white" x-text="item.period"></dd></div>
                                <div><dt class="text-xs text-zinc-500">Opsi AO</dt><dd class="mt-1 font-semibold dark:text-white" x-text="'Opsi ' + item.option"></dd></div>
                            </dl>
                            <div class="mt-auto flex items-center justify-between gap-3 pt-2">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400" x-text="'Diajukan (contoh): ' + item.submittedAt"></p>
                                <button type="button" @click="openProposal(item.id)" :aria-pressed="selectedProposalId === item.id" aria-controls="approval-v2-detail" class="shrink-0 rounded-lg border border-[#1565D8] px-3 py-2 text-xs font-semibold text-[#1565D8] hover:bg-blue-50 dark:hover:bg-blue-950/40">Lihat Contoh Detail</button>
                            </div>
                        </article>
                    </template>
                </div>
                <div x-show="filteredProposals.length === 0" class="mt-4 rounded-xl border border-dashed border-zinc-300 p-7 text-center text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300" aria-live="polite">Tidak ada contoh yang sesuai filter. Ubah pencarian atau reset filter.</div>
                <template x-if="selectedProposal()">
                    <section id="approval-v2-detail" class="mt-5 min-w-0 space-y-5 rounded-2xl border border-[#1565D8] bg-white p-4 dark:border-blue-800 dark:bg-zinc-900 sm:p-6" role="region" aria-label="Detail pengajuan jadwal contoh">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-[#1565D8]">Detail pengajuan — simulasi</p>
                                <h4 class="mt-1 text-xl font-bold text-zinc-900 dark:text-white" x-text="selectedProposal().title"></h4>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300"><span x-text="selectedProposal().id"></span> · <span x-text="selectedProposal().requester"></span> · <span x-text="selectedProposal().submittedAt"></span></p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(selectedProposal().status)" x-text="statusLabel(selectedProposal().status)"></span>
                                <button type="button" @click="closeProposal()" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-800">Tutup Detail</button>
                            </div>
                        </div>
                        <p role="note" class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm font-semibold text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                            CONTOH DATA — BUKAN HASIL VALIDASI ENGINE. Nama instruktur, ruangan, tanggal, histori, dan status di bawah ini adalah ilustrasi UI.
                        </p>
                        <dl class="grid grid-cols-2 gap-3 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800/40 sm:grid-cols-4">
                            <div><dt class="text-xs text-zinc-500">Bidang</dt><dd class="mt-1 font-semibold dark:text-white" x-text="departmentLabel(selectedProposal().department)"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Jenis Kelas</dt><dd class="mt-1 font-semibold dark:text-white" x-text="selectedProposal().classKind === 'private' ? 'Privat' : 'Reguler'"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Jumlah Siswa</dt><dd class="mt-1 font-semibold dark:text-white" x-text="selectedProposal().students + ' orang'"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Pertemuan</dt><dd class="mt-1 font-semibold dark:text-white" x-text="selectedProposal().meetings + ' kali'"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Periode</dt><dd class="mt-1 font-semibold dark:text-white" x-text="selectedProposal().period"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Alternatif AO</dt><dd class="mt-1 font-semibold dark:text-white" x-text="'Opsi ' + selectedProposal().option"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Jam Contoh</dt><dd class="mt-1 font-semibold dark:text-white" x-text="selectedProposal().timeStart + '–' + selectedProposal().timeEnd + ' WIB'"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Pemohon</dt><dd class="mt-1 font-semibold dark:text-white" x-text="selectedProposal().requester"></dd></div>
                        </dl>
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" aria-label="Alasan opsi contoh">
                                <h5 class="text-sm font-bold text-zinc-900 dark:text-white">Rasional Opsi Terpilih</h5>
                                <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">Opsi <span x-text="selectedProposal().option"></span> dipakai untuk mengilustrasikan keputusan AO. Alasan pemilihan nyata hanya boleh berasal dari engine setelah memeriksa seluruh resource.</p>
                            </section>
                            <section class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/20" aria-label="Validasi resource contoh">
                                <h5 class="text-sm font-bold text-amber-950 dark:text-amber-200">Validasi Jadwal Resmi</h5>
                                <p class="mt-2 text-sm leading-relaxed text-amber-900 dark:text-amber-200">Belum diperiksa oleh backend. Tidak ada konfirmasi ketersediaan instruktur, ruangan, kapasitas, kompetensi atau status bentrok.</p>
                                <p class="mt-2 text-xs font-semibold text-amber-900 dark:text-amber-200">BAAK wajib melakukan revalidasi atomik pada saat persetujuan nanti.</p>
                            </section>
                        </div>
                        <section aria-label="Daftar seluruh pertemuan contoh" class="space-y-3">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <h5 class="text-base font-bold text-zinc-900 dark:text-white">Daftar Seluruh Pertemuan</h5>
                                <p class="text-xs text-zinc-500" x-text="exampleMeetings(selectedProposal()).length + ' tanggal contoh · bukan reservasi'"></p>
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="Kalender ringkas pertemuan contoh">
                                <template x-for="meeting in exampleMeetings(selectedProposal())" :key="'tile-' + meeting.iso">
                                    <div class="rounded-lg border border-zinc-200 p-3 text-center dark:border-zinc-700">
                                        <p class="text-xs font-semibold text-[#1565D8]" x-text="'Pertemuan ' + meeting.number"></p>
                                        <p class="mt-1 text-xs text-zinc-700 dark:text-zinc-200" x-text="meeting.date"></p>
                                    </div>
                                </template>
                            </div>
                            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                                <table class="w-full min-w-[640px] text-left text-sm">
                                    <thead class="bg-zinc-50 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"><tr><th scope="col" class="px-3 py-3">Ke-</th><th scope="col" class="px-3 py-3">Tanggal</th><th scope="col" class="px-3 py-3">Jam</th><th scope="col" class="px-3 py-3">Instruktur (contoh)</th><th scope="col" class="px-3 py-3">Ruangan (contoh)</th></tr></thead>
                                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                        <template x-for="meeting in exampleMeetings(selectedProposal())" :key="'row-' + meeting.iso">
                                            <tr><td class="px-3 py-3" x-text="meeting.number"></td><td class="px-3 py-3" x-text="meeting.date"></td><td class="px-3 py-3" x-text="meeting.time + ' WIB'"></td><td class="px-3 py-3" x-text="meeting.instructor"></td><td class="px-3 py-3" x-text="meeting.room"></td></tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                        <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" aria-label="Histori review contoh">
                            <h5 class="text-sm font-bold text-zinc-900 dark:text-white">Riwayat Keputusan (Simulasi)</h5>
                            <ol class="mt-3 space-y-3">
                                <template x-for="(event,index) in exampleHistory(selectedProposal())" :key="index">
                                    <li class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800/50"><p class="font-semibold dark:text-white" x-text="event.label"></p><p class="mt-1 text-xs text-zinc-500" x-text="event.actor + ' · ' + event.date"></p><p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300" x-text="event.note"></p></li>
                                </template>
                            </ol>
                        </section>
                        <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" aria-label="Simulasi keputusan BAAK">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div><h5 class="text-base font-bold text-zinc-900 dark:text-white">Tindakan BAAK</h5><p class="mt-1 text-xs leading-relaxed text-zinc-500">Pilih tindakan untuk melihat formulir pratinjau. Tidak ada keputusan resmi yang dapat dikirim. Hak akses BAAK akan diperiksa backend saat integrasi.</p></div>
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-900 dark:bg-amber-950 dark:text-amber-200">AKSI RESMI NONAKTIF</span>
                            </div>
                            <p x-show="selectedProposal().status !== 'pending'" class="mt-3 text-sm text-zinc-500 dark:text-zinc-300">Contoh proposal ini bukan berstatus menunggu; tindakan keputusan tidak tersedia.</p>
                            <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <button type="button" @click="previewReview('approve')" :disabled="selectedProposal().status !== 'pending'" class="rounded-lg border border-emerald-300 px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-800 dark:text-emerald-300">Pratinjau Setujui</button>
                                <button type="button" @click="previewReview('reject')" :disabled="selectedProposal().status !== 'pending'" class="rounded-lg border border-rose-300 px-4 py-2.5 text-sm font-semibold text-rose-800 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-800 dark:text-rose-300">Pratinjau Tolak</button>
                                <button type="button" @click="previewReview('revision')" :disabled="selectedProposal().status !== 'pending'" class="rounded-lg border border-blue-300 px-4 py-2.5 text-sm font-semibold text-[#1565D8] hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-blue-800 dark:text-blue-300">Pratinjau Minta Revisi</button>
                            </div>
                            <div x-show="reviewMode" style="display: none" class="mt-4 space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/20" aria-label="Form simulasi keputusan">
                                <div class="flex flex-wrap items-center justify-between gap-2"><h6 class="text-sm font-bold text-amber-950 dark:text-amber-200" x-text="'Simulasi: ' + reviewLabel(reviewMode)"></h6><button type="button" @click="reviewMode = null; reviewReason = ''" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-900 dark:border-amber-800 dark:text-amber-200">Batal Pratinjau</button></div>
                                <p class="text-xs leading-relaxed text-amber-900 dark:text-amber-200" x-text="reviewMode === 'approve' ? 'Saat backend siap, BAAK wajib recheck seluruh resource dan membuat jadwal secara atomik.' : 'Saat backend siap, alasan wajib dikirim bersama keputusan dan dicatat dalam riwayat.'"></p>
                                <label x-show="reviewMode !== 'approve'" class="block text-xs font-semibold text-amber-950 dark:text-amber-200">Alasan keputusan (minimum 10 karakter pada implementasi nanti)
                                    <textarea x-model="reviewReason" rows="3" maxlength="500" placeholder="Contoh alasan penolakan / revisi..." class="mt-2 block w-full rounded-lg border border-amber-300 bg-white p-3 text-sm text-zinc-900 dark:border-amber-800 dark:bg-zinc-900 dark:text-white"></textarea>
                                    <span class="mt-1 block text-xs font-normal" x-text="reviewReason.trim().length + ' karakter · minimal 10 karakter untuk keputusan nyata'"></span>
                                </label>
                                <button type="button" disabled aria-disabled="true" class="w-full cursor-not-allowed rounded-lg bg-[#1565D8] px-4 py-3 text-sm font-semibold text-white opacity-50">Konfirmasi Keputusan — Belum Aktif</button>
                                <p class="text-xs text-amber-900 dark:text-amber-200">Tidak ada pengiriman request, perubahan status proposal, approval, ataupun reservasi jadwal.</p>
                            </div>
                        </section>
                    </section>
                </template>
                <div class="mt-4 rounded-lg border border-blue-100 bg-blue-50/60 p-3 text-xs leading-5 text-blue-950 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200">
                    <strong>Alur setelah backend siap:</strong> AO mengajukan → proposal pending (tidak memesan slot) → BAAK memvalidasi ulang → setujui, tolak, atau minta revisi. Semua keputusan resmi memerlukan otorisasi dan transaksi server.
                </div>
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">Tidak ada persetujuan, penolakan, perubahan status, atau reservasi jadwal yang dilakukan oleh tab ini.</p>
            </div>
        </section>

        <section
            id="approval-v2-panel-leave"
            role="tabpanel"
            aria-labelledby="approval-v2-tab-leave"
            x-show="activeTab === 'leave'"
            x-cloak
            style="display: none"
            class="min-w-0 space-y-4"
        >
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Modul persetujuan izin instruktur lama tetap digunakan. Tindakan pada tab ini mengikuti otorisasi dan validasi sistem yang sudah berjalan.
            </p>
            {{-- Preserve all existing leave approval / rejection actions and validations. --}}
            <livewire:approval-review />
        </section>
    </main>
</x-layouts::app>
