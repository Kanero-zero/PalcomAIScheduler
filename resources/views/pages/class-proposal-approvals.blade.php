<x-layouts::app :title="__('Persetujuan Kelas Baru')">
    <main class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 p-4 sm:p-6" x-data="{ rejectOpen: false, rejectReason: '' }">
        <header class="rounded-2xl bg-[#0A3D91] p-5 text-white shadow-sm sm:p-7">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-200">BAAK · SCHEDORA AI</p>
            <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Persetujuan Kelas Baru</h1>
            <p class="mt-2 max-w-3xl text-sm text-blue-100">Tinjau proposal AO, validasi ulang bentrok terkini, lalu tetapkan keputusan. Menu ini terpisah dari approval izin instruktur.</p>
        </header>

        @if (session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
                <p class="font-semibold">Keputusan belum dapat diproses.</p>
                @foreach ($errors->all() as $message)<p class="mt-1">{{ $message }}</p>@endforeach
            </div>
        @endif

        <nav class="flex flex-wrap gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-800" aria-label="Filter proposal">
            @foreach (['pending' => 'Menunggu Persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $filter => $label)
                <a href="{{ route('class-proposal-approvals.index', ['status' => $filter]) }}" @class([
                    'rounded-full px-4 py-2 text-sm font-semibold transition',
                    'bg-[#1565D8] text-white' => $status === $filter,
                    'border border-zinc-300 bg-white text-zinc-700 hover:border-blue-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' => $status !== $filter,
                ])>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(260px,0.7fr)_minmax(0,1.8fr)]">
            <section aria-label="Daftar proposal" class="min-w-0 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-bold text-zinc-900 dark:text-white">Daftar Proposal</h2>
                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $proposals->count() }}</span>
                </div>
                <div class="mt-4 space-y-2">
                    @forelse ($proposals as $proposal)
                        <a href="{{ route('class-proposal-approvals.index', ['status' => $status, 'proposal' => $proposal->id]) }}" @class([
                            'block rounded-xl border p-3 transition',
                            'border-blue-500 bg-blue-50 ring-2 ring-blue-100 dark:bg-blue-950/30 dark:ring-blue-950' => $selected?->id === $proposal->id,
                            'border-zinc-200 hover:border-blue-300 dark:border-zinc-700' => $selected?->id !== $proposal->id,
                        ])>
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">#{{ $proposal->id }} · {{ $proposal->title }}</p>
                                <span @class([
                                    'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                    'bg-amber-100 text-amber-800' => $proposal->status === 'pending',
                                    'bg-emerald-100 text-emerald-800' => $proposal->status === 'approved',
                                    'bg-rose-100 text-rose-800' => $proposal->status === 'rejected',
                                ])>{{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$proposal->status] ?? $proposal->status }}</span>
                            </div>
                            <p class="mt-1 text-xs text-zinc-500">{{ $proposal->requester?->name ?? 'AO tidak tersedia' }} · {{ $proposal->submitted_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '-' }} WIB</p>
                        </a>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">Tidak ada proposal pada filter ini.</div>
                    @endforelse
                </div>
            </section>

            <section aria-label="Detail proposal kelas baru" class="min-w-0 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                @if ($selected && $selectedDetails)
                    <div class="flex flex-col gap-3 border-b border-zinc-200 pb-5 sm:flex-row sm:items-start sm:justify-between dark:border-zinc-800">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-[#1565D8]">Proposal #{{ $selectedDetails['id'] }}</p>
                            <h2 class="mt-1 text-xl font-bold text-zinc-900 dark:text-white">{{ $selectedDetails['title'] }}</h2>
                            <p class="mt-1 text-sm text-zinc-500">Diajukan oleh {{ $selectedDetails['requester'] ?? '-' }} pada {{ $selected->submitted_at?->timezone('Asia/Jakarta')->format('d F Y, H:i') ?? '-' }} WIB</p>
                        </div>
                        <span @class([
                            'w-fit rounded-full px-3 py-1.5 text-xs font-bold',
                            'bg-amber-100 text-amber-800' => $selected->status === 'pending',
                            'bg-emerald-100 text-emerald-800' => $selected->status === 'approved',
                            'bg-rose-100 text-rose-800' => $selected->status === 'rejected',
                        ])>{{ ['pending' => 'Menunggu Persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$selected->status] ?? $selected->status }}</span>
                    </div>

                    <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            'Bidang' => $selectedDetails['department'] === 'computer' ? 'Komputer' : 'SpeakOut',
                            'Jenis kelas' => $selectedDetails['class_kind'] === 'regular' ? 'Reguler' : 'Privat',
                            'Jumlah siswa' => $selectedDetails['student_count'].' orang',
                            'Periode' => $selectedDetails['start_date'].' – '.$selectedDetails['end_date'],
                            'Jumlah pertemuan' => $selectedDetails['total_meetings'],
                            'Frekuensi' => $selectedDetails['frequency_per_week'].'× per minggu',
                            'Durasi' => $selectedDetails['session_duration_minutes'].' menit',
                            'Hari' => implode(', ', $selectedDetails['preferred_weekdays']),
                            'Jam' => ($selectedDetails['preferred_time_window']['start'] ?? '-').' – '.($selectedDetails['preferred_time_window']['end'] ?? '-'),
                            'Opsi terpilih' => $selectedDetails['option_label'] ? 'Opsi '.$selectedDetails['option_label'] : 'Belum dipilih',
                        ] as $label => $value)
                            <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                                <dt class="text-xs text-zinc-500">{{ $label }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ $value ?: '-' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <section class="mt-6" aria-labelledby="validation-heading">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 id="validation-heading" class="font-bold text-zinc-900 dark:text-white">{{ $selected->status === 'approved' ? 'Verifikasi Jadwal Resmi' : 'Validasi Konflik Terkini' }}</h3>
                                <p class="mt-1 text-xs text-zinc-500">{{ $selected->status === 'approved' ? 'Diverifikasi langsung dari record pada tabel schedules yang terhubung ke proposal ini.' : 'Dijalankan kembali terhadap database operasional di dalam transaksi saat tombol Setujui ditekan.' }}</p>
                            </div>
                            @if ($validation['has_conflicts'])
                                <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-800">Konflik ditemukan</span>
                            @else
                                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Tidak ada konflik</span>
                            @endif
                        </div>
                        @if ($validation['has_conflicts'])
                            <div class="mt-3 space-y-2">
                                @foreach ($validation['conflicts'] as $conflict)
                                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200">
                                        <p class="font-semibold">{{ $conflict['resource_name'] }} · {{ $conflict['date'] ?: 'Tanggal belum tersedia' }} {{ $conflict['start_time'] }}–{{ $conflict['end_time'] }}</p>
                                        <p class="mt-1 text-xs leading-5">{{ $conflict['reason'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="mt-6" aria-labelledby="meetings-heading">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 id="meetings-heading" class="font-bold text-zinc-900 dark:text-white">Seluruh Pertemuan</h3>
                            @if ($selectedDetails['meetings_source'] === 'official_schedules')
                                <span @class([
                                    'rounded-full px-3 py-1 text-xs font-bold',
                                    'bg-emerald-100 text-emerald-800' => $selectedDetails['official_schedule_verified'],
                                    'bg-rose-100 text-rose-800' => ! $selectedDetails['official_schedule_verified'],
                                ])>Sumber: jadwal resmi · {{ $selectedDetails['official_schedule_count'] }} record</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Sumber: opsi engine · belum resmi</span>
                            @endif
                        </div>
                        <div class="mt-3 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800"><tr><th class="px-3 py-2">#</th><th class="px-3 py-2">Tanggal</th><th class="px-3 py-2">Jam</th><th class="px-3 py-2">Instruktur</th><th class="px-3 py-2">Ruangan</th></tr></thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @forelse ($selectedDetails['meetings'] as $meeting)
                                        <tr>
                                            <td class="px-3 py-2 font-semibold">{{ $meeting['sequence'] }}@if(isset($meeting['schedule_id']))<span class="mt-0.5 block text-[10px] font-normal text-zinc-400">ID {{ $meeting['schedule_id'] }}</span>@endif</td>
                                            <td class="px-3 py-2">{{ $meeting['date'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $meeting['start_time'] ?? '-' }}–{{ $meeting['end_time'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $meeting['instructor_name'] }}</td>
                                            <td class="px-3 py-2">{{ $meeting['room_name'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-3 py-6 text-center text-zinc-500">Belum ada slot pertemuan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    @if ($selected->status === 'pending')
                        <div class="mt-6 flex flex-col gap-3 border-t border-zinc-200 pt-5 sm:flex-row dark:border-zinc-800">
                            <form method="POST" action="{{ route('class-proposal-approvals.approve', $selected) }}" class="sm:flex-1">
                                @csrf
                                <input type="hidden" name="idempotency_token" value="{{ $approvalToken }}">
                                <button type="submit" @disabled($validation['has_conflicts']) class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#1565D8] px-4 py-3 text-sm font-bold text-white hover:bg-[#0A3D91] disabled:cursor-not-allowed disabled:bg-zinc-300 disabled:text-zinc-600"><flux:icon name="check-circle" class="size-5" /> Setujui</button>
                            </form>
                            <button type="button" @click="rejectOpen = true; rejectReason = ''" class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-300 px-5 py-3 text-sm font-bold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300"><flux:icon name="x-circle" class="size-5" /> Tolak</button>
                        </div>
                    @else
                        <div class="mt-6 rounded-xl border p-4 {{ $selected->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900' }}">
                            <p class="font-bold">{{ $selected->status === 'approved' ? 'Disetujui — jadwal sudah resmi' : 'Ditolak' }}</p>
                            <p class="mt-1 text-sm">{{ data_get($selectedDetails, 'decision.reason') ?: ($selected->status === 'approved' ? 'Seluruh pertemuan telah ditulis ke jadwal resmi.' : 'Tidak ada alasan tersimpan.') }}</p>
                            <p class="mt-2 text-xs">Diputuskan oleh {{ data_get($selectedDetails, 'decision.decided_by', '-') }} · Putaran {{ data_get($selectedDetails, 'decision.round', '-') }}</p>
                        </div>
                    @endif
                @else
                    <div class="flex min-h-80 items-center justify-center text-center"><div><p class="font-semibold text-zinc-700 dark:text-zinc-200">Belum ada proposal untuk ditinjau.</p><p class="mt-1 text-sm text-zinc-500">Pilih filter lain atau tunggu pengajuan AO berikutnya.</p></div></div>
                @endif
            </section>
        </div>

        @if ($selected && $selected->status === 'pending')
            <div x-cloak x-show="rejectOpen" @keydown.escape.window="rejectOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
                <button type="button" aria-label="Tutup dialog" class="absolute inset-0 bg-zinc-950/70" @click="rejectOpen = false"></button>
                <form method="POST" action="{{ route('class-proposal-approvals.reject', $selected) }}" class="relative z-10 w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl dark:bg-zinc-900">
                    @csrf
                    <input type="hidden" name="idempotency_token" value="{{ $rejectionToken }}">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Tolak Proposal #{{ $selected->id }}</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Alasan penolakan wajib diisi dan akan tersimpan pada riwayat keputusan.</p>
                    <label for="proposal-rejection-reason" class="mt-4 block text-sm font-semibold text-zinc-800 dark:text-zinc-200">Alasan penolakan</label>
                    <textarea id="proposal-rejection-reason" name="reason" x-model="rejectReason" required minlength="10" maxlength="500" rows="4" class="mt-2 w-full rounded-xl border border-zinc-300 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"></textarea>
                    <p class="mt-1 text-xs text-zinc-500"><span x-text="rejectReason.trim().length"></span>/500 karakter</p>
                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" @click="rejectOpen = false" class="rounded-xl border border-zinc-300 px-4 py-2.5 text-sm font-semibold">Batal</button>
                        <button type="submit" :disabled="rejectReason.trim().length < 10" class="rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40">Konfirmasi Penolakan</button>
                    </div>
                </form>
            </div>
        @endif
    </main>
</x-layouts::app>
