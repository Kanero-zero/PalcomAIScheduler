<x-layouts::app :title="__('Dashboard BAAK V2 — Preview')">
    {{-- F-08/P3: preview-only BAAK dashboard. No DB reads, AI calls, decisions, or persistence. --}}
    <style>
        #dashboard-v2 [x-cloak] { display: none !important; }
    </style>
    <main
        x-data="{
            calendarMonth: '2026-11',
            selectedCalendarDate: '2026-11-23',
            get calendarMonthLabel() {
                const parts = this.calendarMonth.split('-').map(Number);
                return new Intl.DateTimeFormat('id-ID', {month:'long',year:'numeric'}).format(new Date(parts[0],parts[1]-1,1,12));
            },
            get calendarDays() {
                const [year,month] = this.calendarMonth.split('-').map(Number);
                const first = new Date(year,month-1,1,12);
                const offset = (first.getDay() + 6) % 7;
                const start = new Date(year,month-1,1-offset,12);
                const output = [];
                for (let i=0; i<42; i++) {
                    const day = new Date(start.getFullYear(),start.getMonth(),start.getDate()+i,12);
                    const iso = [day.getFullYear(),String(day.getMonth()+1).padStart(2,'0'),String(day.getDate()).padStart(2,'0')].join('-');
                    output.push({iso,day:day.getDate(),inMonth:day.getFullYear()===year && day.getMonth()+1===month,sessions:this.agendaSamples.filter(s=>s.date===iso).length});
                }
                return output;
            },
            get selectedCalendarSessions() {
                return this.agendaSamples.filter(session => session.date === this.selectedCalendarDate);
            },
            changeCalendarMonth(delta) {
                const [year,month] = this.calendarMonth.split('-').map(Number);
                const next = new Date(year,month-1+delta,1,12);
                this.calendarMonth = [next.getFullYear(),String(next.getMonth()+1).padStart(2,'0')].join('-');
                this.selectedCalendarDate = [next.getFullYear(),String(next.getMonth()+1).padStart(2,'0'),'01'].join('-');
            },
            selectCalendarDate(iso) {
                if (typeof iso === 'string' && iso.slice(0,7) === this.calendarMonth) this.selectedCalendarDate=iso;
            },
            resetDemoCalendar() { this.calendarMonth='2026-11'; this.selectedCalendarDate='2026-11-23'; },
            kindFilter: 'all',
            selectedId: null,
            previewReferenceDate: '2026-11-20',
            priorityItems: [
                { id:'JAD-001', kind:'new', category:'Jadwal baru', title:'Pengajuan Web Programming Pagi', department:'Komputer', status:'pending', starts:'2026-11-23', priority:3, label:'Tinggi', issue:true, reason:'Kelas direncanakan mulai paling dekat dalam data contoh dan keputusan BAAK masih pending.' },
                { id:'IZN-002', kind:'leave', category:'Izin instruktur', title:'Pergantian Instruktur SpeakOut', department:'SpeakOut', status:'pending', starts:'2026-11-24', priority:3, label:'Tinggi', issue:false, reason:'Dua sesi percakapan contoh membutuhkan pemeriksaan instruktur pengganti.' },
                { id:'JAD-003', kind:'new', category:'Jadwal baru', title:'Pengajuan Microsoft Excel Sore', department:'Komputer', status:'pending', starts:'2026-11-25', priority:2, label:'Sedang', issue:false, reason:'Proposal masih pending, namun periode mulai lebih jauh daripada pengajuan pertama.' },
                { id:'IZN-004', kind:'leave', category:'Izin instruktur', title:'Pengganti Instruktur Excel', department:'Komputer', status:'pending', starts:'2026-11-27', priority:2, label:'Sedang', issue:false, reason:'Jadwal yang terdampak membutuhkan review BAAK sebelum sesi contoh berlangsung.' },
                { id:'JAD-005', kind:'new', category:'Jadwal baru', title:'Pengajuan Desain Grafis Dasar', department:'Komputer', status:'pending', starts:'2026-12-02', priority:1, label:'Normal', issue:false, reason:'Masih dalam antrean, dengan waktu persiapan lebih panjang dibanding item lain.' }
            ],
            agendaSamples: [
                { id:'AG-1', date:'2026-11-23', time:'08.00–10.00', title:'Microsoft Excel', instructor:'Instruktur Contoh A', room:'Lab Komputer 1', status:'Terjadwal (simulasi)' },
                { id:'AG-2', date:'2026-11-23', time:'10.00–11.30', title:'SpeakOut Conversation', instructor:'Instruktur Contoh B', room:'Ruang SpeakOut 1', status:'Terjadwal (simulasi)' },
                { id:'AG-3', date:'2026-11-23', time:'13.00–15.00', title:'Web Programming', instructor:'Instruktur Contoh C', room:'Lab Komputer 2', status:'Terjadwal (simulasi)' }
            ],
            get visiblePriorityItems() {
                return this.priorityItems
                    .filter(item => this.kindFilter === 'all' || item.kind === this.kindFilter)
                    .slice()
                    .sort((a,b) => b.priority - a.priority || a.starts.localeCompare(b.starts) || a.id.localeCompare(b.id));
            },
            get selectedItem() {
                return this.priorityItems.find(item => item.id === this.selectedId) ?? null;
            },
            get counts() {
                return {
                    pending: this.priorityItems.filter(item => item.kind === 'new' && item.status === 'pending').length,
                    leaves: this.priorityItems.filter(item => item.kind === 'leave' && item.status === 'pending').length,
                    classes: this.agendaSamples.length,
                    issues: this.priorityItems.filter(item => item.issue).length
                };
            },
            chooseItem(itemId) { this.selectedId = this.selectedId === itemId ? null : itemId; },
            clearSelection() { this.selectedId = null; },
            formatDemoDate(iso) {
                const parts = iso.split('-').map(Number);
                if (parts.length !== 3) return 'Tanggal tidak tersedia';
                return new Intl.DateTimeFormat('id-ID', {day:'numeric',month:'short',year:'numeric'}).format(new Date(parts[0],parts[1]-1,parts[2],12));
            }
        }"
        id="dashboard-v2" class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 p-4 sm:gap-6 sm:p-6"
    >
        <header class="overflow-hidden rounded-2xl bg-[#0A3D91] p-5 text-white sm:p-7">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl space-y-2">
                    <p class="text-xs font-bold uppercase tracking-widest text-[#FFC928]">SCHEDORA AI / BAAK</p>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Dashboard Operasional</h1>
                    <p class="text-sm leading-6 text-blue-100">Pantau pengajuan, kebutuhan keputusan BAAK, dan agenda kelas dari satu halaman.</p>
                </div>
                <span class="rounded-full bg-[#FFC928] px-3 py-1.5 text-xs font-bold text-[#0A3D91]">UI PREVIEW</span>
            </div>
        </header>

        <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
            <strong>DATA SIMULASI — BUKAN DATA OPERASIONAL.</strong>
            Ini adalah rancangan khusus peran BAAK. Data nyata, pengurutan AI, dan hak akses BAAK akan dihubungkan setelah backend tersedia.
        </div>

        <section aria-labelledby="dashboard-v2-overview-heading" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="dashboard-v2-overview-heading" class="text-lg font-bold text-zinc-900 dark:text-white">Ringkasan operasional</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Kartu statistik contoh — data belum terhubung</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['label' => 'Menunggu Approval', 'icon' => 'clipboard-document-check', 'key' => 'pending'],
                    ['label' => 'Izin Instruktur', 'icon' => 'clock', 'key' => 'leaves'],
                    ['label' => 'Kelas Segera Mulai', 'icon' => 'calendar-days', 'key' => 'classes'],
                    ['label' => 'Isu Perlu Review', 'icon' => 'exclamation-triangle', 'key' => 'issues'],
                ] as $stat)
                    <article class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs text-zinc-600 dark:text-zinc-300">{{ $stat['label'] }}</p>
                            <flux:icon :name="$stat['icon']" class="size-5 text-[#1565D8] dark:text-blue-300" />
                        </div>
                        <p class="mt-3 text-3xl font-bold text-zinc-900 dark:text-white" x-text="counts.{{ $stat['key'] }}">—</p>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Angka simulasi, bukan data database</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="dashboard-v2-inbox" aria-labelledby="dashboard-v2-inbox-heading" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
            <div class="mb-4 space-y-1">
                <h2 id="dashboard-v2-inbox-heading" class="text-lg font-bold text-zinc-900 dark:text-white">AI Priority Inbox</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Urutan tugas dan alasan prioritas. Tidak ada persetujuan otomatis dari dashboard.</p>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 pb-4 dark:border-zinc-800">
                <p class="max-w-2xl text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                    <strong>Simulasi berbasis aturan, bukan keluaran AI.</strong>
                    Urutan contoh: tingkat urgensi, tanggal mulai, lalu kode pengajuan. Acuan simulasi: 20 November 2026.
                </p>
                <label class="flex items-center gap-2 text-xs font-medium text-zinc-700 dark:text-zinc-200">
                    Jenis
                    <select id="dashboard-v2-inbox-filter" x-model="kindFilter" @change="clearSelection()" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                        <option value="all">Semua contoh</option>
                        <option value="new">Jadwal baru</option>
                        <option value="leave">Izin instruktur</option>
                    </select>
                </label>
            </div>
            <p class="py-3 text-xs text-zinc-500 dark:text-zinc-400" aria-live="polite">
                <span x-text="visiblePriorityItems.length">5</span> contoh pekerjaan ditampilkan.
            </p>
            <ol class="space-y-3" aria-label="Daftar prioritas pekerjaan simulasi">
                <template x-for="(item, index) in visiblePriorityItems" :key="item.id">
                    <li class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <div class="grid grid-cols-[auto_minmax(0,1fr)] items-start gap-3 sm:flex sm:flex-nowrap">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-xs font-bold text-[#1565D8] dark:bg-blue-950 dark:text-blue-200" x-text="String(index + 1).padStart(2,'0')"></span>
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white" x-text="item.title"></h3>
                                    <span class="rounded-md bg-white px-2 py-0.5 text-[11px] font-semibold text-zinc-700 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-zinc-700" x-text="item.label"></span>
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400"><span x-text="item.category"></span> · <span x-text="item.department"></span> · <span x-text="item.id"></span></p>
                                <p class="text-xs leading-5 text-zinc-700 dark:text-zinc-300" x-text="item.reason"></p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Rencana mulai: <span x-text="formatDemoDate(item.starts)"></span> · Status <span x-text="item.status"></span></p>
                            </div>
                            <button type="button" @click="chooseItem(item.id)" :aria-expanded="selectedId === item.id" aria-controls="dashboard-v2-priority-detail" class="col-span-2 justify-self-start shrink-0 rounded-lg border border-[#1565D8] px-3 py-2 text-xs font-semibold text-[#1565D8] hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1565D8] dark:text-blue-300 dark:hover:bg-blue-950/30">
                                <span x-text="selectedId === item.id ? 'Tutup detail' : 'Lihat alasan'"></span>
                            </button>
                        </div>
                    </li>
                </template>
            </ol>
            <div x-show="visiblePriorityItems.length === 0" class="mt-4 rounded-lg border border-dashed border-zinc-300 p-5 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">Tidak ada item pada filter ini.</div>
            <div id="dashboard-v2-priority-detail" x-show="selectedItem !== null" x-cloak class="mt-4 rounded-xl border border-blue-200 bg-blue-50/70 p-4 dark:border-blue-900 dark:bg-blue-950/30" aria-live="polite">
                <p class="text-xs font-bold uppercase tracking-wide text-[#0A3D91] dark:text-blue-200">Detail alasan — hanya simulasi</p>
                <p class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white" x-text="selectedItem?.title ?? ''"></p>
                <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300" x-text="selectedItem?.reason ?? ''"></p>
                <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">Prioritas bukan keputusan akhir. Pemeriksaan konflik dan persetujuan hanya dilakukan di modul Approval / Reject yang sah.</p>
                <a href="{{ route('approval-review') }}" wire:navigate class="mt-3 inline-flex rounded-lg bg-[#0A3D91] px-3 py-2 text-xs font-semibold text-white hover:bg-[#1565D8]">Buka Approval / Reject operasional →</a>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            <section id="dashboard-v2-agenda" aria-labelledby="dashboard-v2-agenda-heading" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                <h2 id="dashboard-v2-agenda-heading" class="text-base font-bold text-zinc-900 dark:text-white">Agenda Hari Ini (Contoh)</h2>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Agenda ilustratif tanggal 23 November 2026. Tidak membaca jadwal resmi atau agenda tanggal perangkat.</p>
                <ol class="mt-4 space-y-3">
                    <template x-for="session in agendaSamples" :key="session.id">
                        <li class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                            <p class="text-xs font-bold text-[#1565D8] dark:text-blue-300" x-text="session.time + ' WIB'"></p>
                            <h3 class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white" x-text="session.title"></h3>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"><span x-text="session.instructor"></span> · <span x-text="session.room"></span></p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" x-text="session.status"></p>
                        </li>
                    </template>
                </ol>
                <a href="{{ route('schedules.index') }}" wire:navigate class="mt-4 inline-block text-sm font-semibold text-[#1565D8] hover:underline dark:text-blue-300">Buka jadwal operasional →</a>
            </section>
            <section aria-labelledby="dashboard-v2-shortcuts-heading" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                <h2 id="dashboard-v2-shortcuts-heading" class="text-base font-bold text-zinc-900 dark:text-white">Akses Cepat</h2>
                <div class="mt-4 grid gap-2">
                    <a href="{{ route('approval-review') }}" wire:navigate class="rounded-xl border border-zinc-200 px-4 py-3 text-sm font-semibold text-[#1565D8] hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-[#1565D8] dark:border-zinc-700 dark:hover:bg-zinc-800">Buka Approval / Reject (operasional) →</a>
                    <a href="{{ route('ai-scheduler') }}" wire:navigate class="rounded-xl border border-zinc-200 px-4 py-3 text-sm font-semibold text-[#1565D8] hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-[#1565D8] dark:border-zinc-700 dark:hover:bg-zinc-800">Buka AI Auto-Scheduler →</a>
                    <a href="{{ route('course-classes.index') }}" wire:navigate class="rounded-xl border border-zinc-200 px-4 py-3 text-sm font-semibold text-[#1565D8] hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-[#1565D8] dark:border-zinc-700 dark:hover:bg-zinc-800">Lihat Kelas Kursus →</a>
                </div>
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">Tautan Approval saat ini menggunakan halaman operasional lama. Preview Approval V2 akan diperiksa secara terpisah.</p>
            </section>
        </div>

        <section id="dashboard-v2-calendar" aria-labelledby="dashboard-v2-calendar-heading" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
            <h2 id="dashboard-v2-calendar-heading" class="text-base font-bold text-zinc-900 dark:text-white">Kalender Mengajar Bulanan</h2>
            <p class="mt-2 text-xs leading-5 text-zinc-500 dark:text-zinc-400">Kalender simulasi November 2026; titik hanya menandakan sesi contoh. Bukan ketersediaan ruangan dan tidak mengubah jadwal resmi.</p>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" @click="changeCalendarMonth(-1)" aria-label="Bulan sebelumnya" class="rounded-lg border border-zinc-200 p-2 text-zinc-700 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-[#1565D8] dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"><flux:icon name="chevron-left" class="size-4" /></button>
                    <h3 class="min-w-36 text-center text-sm font-semibold capitalize text-zinc-900 dark:text-white" x-text="calendarMonthLabel"></h3>
                    <button type="button" @click="changeCalendarMonth(1)" aria-label="Bulan berikutnya" class="rounded-lg border border-zinc-200 p-2 text-zinc-700 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-[#1565D8] dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"><flux:icon name="chevron-right" class="size-4" /></button>
                </div>
                <button type="button" @click="resetDemoCalendar()" class="rounded-lg px-3 py-2 text-xs font-semibold text-[#1565D8] hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-[#1565D8] dark:text-blue-300 dark:hover:bg-zinc-800">Kembali ke November demo</button>
            </div>
            <div class="mt-4 grid grid-cols-7 gap-1 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400" aria-hidden="true">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday)
                    <span class="py-2">{{ $weekday }}</span>
                @endforeach
            </div>
            <div class="grid grid-cols-7 gap-1" role="group" aria-label="Kalender sesi simulasi">
                <template x-for="day in calendarDays" :key="day.iso">
                    <button
                        type="button"
                        @click="selectCalendarDate(day.iso)"
                        :disabled="!day.inMonth"
                        :aria-label="day.iso + ': ' + day.sessions + ' sesi contoh'"
                        :aria-pressed="selectedCalendarDate === day.iso"
                        :class="!day.inMonth ? 'cursor-default border-transparent text-zinc-300 dark:text-zinc-700' : (selectedCalendarDate === day.iso ? 'border-[#1565D8] bg-blue-50 font-bold text-[#0A3D91] dark:bg-blue-950 dark:text-blue-200' : 'border-transparent text-zinc-900 hover:border-blue-200 hover:bg-blue-50 dark:text-zinc-100 dark:hover:bg-zinc-800')"
                        class="flex min-h-14 min-w-0 flex-col items-center justify-center gap-1 rounded-xl border p-1 text-sm focus-visible:outline-2 focus-visible:outline-[#1565D8] sm:min-h-16"
                    >
                        <span x-text="day.day"></span>
                        <span x-show="day.inMonth && day.sessions > 0" class="rounded-full bg-[#1565D8] px-1.5 py-0.5 text-[9px] font-bold text-white" x-text="day.sessions + ' sesi'"></span>
                    </button>
                </template>
            </div>
            <section class="mt-5 border-t border-zinc-100 pt-4 dark:border-zinc-800" aria-label="Detail sesi tanggal terpilih">
                <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Sesi contoh: <span x-text="formatDemoDate(selectedCalendarDate)"></span></h3>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" x-text="selectedCalendarSessions.length + ' sesi ilustratif pada tanggal ini'"></p>
                <div x-show="selectedCalendarSessions.length === 0" class="mt-3 rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">Tidak ada sesi contoh pada tanggal ini. Ini tidak berarti ruangan kosong.</div>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    <template x-for="session in selectedCalendarSessions" :key="'calendar-' + session.id">
                        <li class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                            <p class="text-xs font-bold text-[#1565D8] dark:text-blue-300" x-text="session.time + ' WIB'"></p>
                            <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white" x-text="session.title"></p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" x-text="session.instructor + ' · ' + session.room"></p>
                        </li>
                    </template>
                </ul>
            </section>
        </section>
    </main>
</x-layouts::app>
