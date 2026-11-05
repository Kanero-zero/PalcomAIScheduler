<x-layouts::app :title="__('AI Auto-Scheduler')">
    @vite('resources/js/schedora-ai-chatbot.js')
    {{-- Patch 4: scoped grid fallback keeps desktop cards side-by-side even before CSS is rebuilt. --}}
    <style>
        #scheduler-example-options { grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 680px) and (max-width: 1023px) {
            #scheduler-example-options { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (min-width: 1024px) {
            #scheduler-example-options { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
    </style>
    {{-- V2 operates against authenticated endpoints; leave scheduler remains unchanged. --}}
    <main
        x-data="schedoraAiChatbot()"
        data-schedora-chatbot
        data-interpret-url="{{ route('ai-scheduler.v2-interpret') }}"
        data-preview-url="{{ route('ai-scheduler.v2-recommendations') }}"
        data-submit-url="{{ route('ai-scheduler.v2-submit') }}"
        class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 p-4 sm:gap-6 sm:p-6"
    >
        <header class="overflow-hidden rounded-2xl bg-[#0A3D91] text-white shadow-xs">
            <div class="flex flex-wrap items-start justify-between gap-4 p-5 sm:p-6">
                <div class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-200">PALCOM AI SCHEDULER</p>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">AI Auto-Scheduler</h1>
                    <p class="max-w-3xl text-sm leading-relaxed text-blue-100">
                        Pembuatan jadwal kelas baru dan rekomendasi pergantian instruktur yang mengajukan izin.
                    </p>
                </div>
                <span class="inline-flex shrink-0 items-center rounded-full bg-[#FFC928] px-3 py-1.5 text-xs font-bold text-[#0A3D91]">
                    GEMINI + ENGINE G3
                </span>
            </div>
        </header>

        <div class="border-b border-zinc-200 dark:border-zinc-800">
            <nav aria-label="Tab AI Auto-Scheduler" role="tablist" class="flex flex-wrap gap-x-6 gap-y-1">
                <button
                    id="scheduler-tab-create"
                    type="button"
                    role="tab"
                    aria-controls="scheduler-panel-create"
                    :aria-selected="activeTab === 'create'"
                    @click="activeTab = 'create'"
                    :class="activeTab === 'create' ? 'border-[#1565D8] text-[#1565D8] dark:text-blue-300' : 'border-transparent text-zinc-600 hover:text-[#1565D8] dark:text-zinc-400'"
                    class="border-b-2 px-1 py-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1565D8]"
                >Buat Jadwal Kelas</button>
                <button
                    id="scheduler-tab-leave"
                    type="button"
                    role="tab"
                    aria-controls="scheduler-panel-leave"
                    :aria-selected="activeTab === 'leave'"
                    @click="activeTab = 'leave'"
                    :class="activeTab === 'leave' ? 'border-[#1565D8] text-[#1565D8] dark:text-blue-300' : 'border-transparent text-zinc-600 hover:text-[#1565D8] dark:text-zinc-400'"
                    class="border-b-2 px-1 py-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1565D8]"
                >Pergantian Instruktur Izin</button>
            </nav>
        </div>

        <section
            id="scheduler-panel-create"
            role="tabpanel"
            aria-labelledby="scheduler-tab-create"
            x-show="activeTab === 'create'"
            class="space-y-5"
        >
            <div x-show="notice" x-text="notice" aria-live="polite" role="status" class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900"></div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs font-medium text-zinc-500 dark:text-zinc-400" aria-label="Tahapan pengajuan jadwal">
                <span :aria-current="stage === 'draft' ? 'step' : null" :class="stage === 'draft' ? 'font-bold text-[#1565D8] dark:text-blue-300' : ''">01&nbsp; Kebutuhan Kelas</span>
                <span aria-hidden="true" class="px-1">›</span>
                <span :aria-current="stage === 'recommendations' ? 'step' : null" :class="stage === 'recommendations' ? 'font-bold text-[#1565D8] dark:text-blue-300' : ''">02&nbsp; Rekomendasi</span>
                <span aria-hidden="true" class="px-1">›</span>
                <span :aria-current="stage === 'review' ? 'step' : null" :class="stage === 'review' ? 'font-bold text-[#1565D8] dark:text-blue-300' : ''">03&nbsp; Tinjauan Akhir</span>
            </div>

            <div x-show="stage === 'draft'" class="space-y-5">
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Bagaimana Anda ingin membuat jadwal?</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Pilih satu cara mengisi kebutuhan kelas. AI Assistant dan Input Manual akan menggunakan satu draft kebutuhan yang sama.
                </p>
                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2" role="group" aria-label="Metode pembuatan jadwal">
                    <button
                        type="button"
                        :aria-pressed="inputMode === 'ai'"
                        @click="inputMode = 'ai'; draft.input_mode = 'ai_assistant'"
                        :class="inputMode === 'ai' ? 'border-[#1565D8] bg-blue-50 text-[#0A3D91] dark:bg-blue-950/30 dark:text-blue-200' : 'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200'"
                        class="rounded-xl border p-4 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1565D8]"
                    >
                        <span class="block text-sm font-bold">AI Assistant</span>
                        <span class="mt-1 block text-xs font-normal opacity-80">Ceritakan kebutuhan jadwal dengan bahasa sehari-hari.</span>
                    </button>
                    <button
                        type="button"
                        :aria-pressed="inputMode === 'manual'"
                        @click="inputMode = 'manual'; draft.input_mode = 'manual_input'"
                        :class="inputMode === 'manual' ? 'border-[#1565D8] bg-blue-50 text-[#0A3D91] dark:bg-blue-950/30 dark:text-blue-200' : 'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200'"
                        class="rounded-xl border p-4 text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1565D8]"
                    >
                        <span class="block text-sm font-bold">Input Manual</span>
                        <span class="mt-1 block text-xs font-normal opacity-80">Isi parameter kelas melalui formulir terstruktur.</span>
                    </button>
                </div>
            </div>

            {{-- Gemini collects requirements conversationally; G3 remains the only recommendation source. --}}
            <section x-show="inputMode === 'ai'" aria-label="Percakapan Schedora AI" class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 bg-gradient-to-r from-blue-50 to-white p-4 dark:border-zinc-800 dark:from-blue-950/40 dark:to-zinc-900 sm:p-5">
                    <div class="flex items-center gap-3">
                        <div class="relative flex size-10 items-center justify-center rounded-2xl bg-[#1565D8] text-lg text-white shadow-sm" aria-hidden="true">✦<span class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-white bg-emerald-500 dark:border-zinc-900"></span></div>
                        <div><h3 class="font-bold text-zinc-900 dark:text-white">Schedora AI</h3><p class="text-xs text-zinc-500 dark:text-zinc-400">Gemini • klarifikasi kebutuhan kelas</p></div>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300">Aktif</span>
                </div>
                <div class="grid min-w-0 lg:grid-cols-[minmax(0,1.55fr)_minmax(260px,0.75fr)]">
                    <div class="min-w-0 border-b border-zinc-200 dark:border-zinc-800 lg:border-b-0 lg:border-r">
                        <div x-ref="conversation" class="flex h-[390px] flex-col gap-4 overflow-y-auto p-4 sm:p-5" aria-live="polite" aria-label="Riwayat percakapan">
                            <template x-for="(message, index) in messages" :key="`${index}-${message.role}`">
                                <div x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" :class="message.role === 'user' ? 'ml-auto flex-row-reverse' : 'mr-auto'" class="flex max-w-[92%] items-end gap-2">
                                    <div x-show="message.role === 'assistant'" class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-xs text-[#1565D8] dark:bg-blue-950/60 dark:text-blue-300" aria-hidden="true">✦</div>
                                    <p x-text="message.content" :class="message.role === 'user' ? 'rounded-br-sm bg-[#1565D8] text-white' : 'rounded-bl-sm border border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100'" class="rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-xs"></p>
                                </div>
                            </template>
                            <div x-show="busy" x-transition.opacity class="flex items-end gap-2">
                                <div class="flex size-7 items-center justify-center rounded-lg bg-blue-100 text-xs text-[#1565D8] dark:bg-blue-950/60">✦</div>
                                <div class="flex gap-1 rounded-2xl rounded-bl-sm border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800" aria-label="Schedora sedang mengetik">
                                    <span class="size-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:-0.3s]"></span><span class="size-1.5 animate-bounce rounded-full bg-zinc-400 [animation-delay:-0.15s]"></span><span class="size-1.5 animate-bounce rounded-full bg-zinc-400"></span>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3 border-t border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 sm:p-5">
                            <div x-show="quickReplies.length" x-transition class="flex flex-wrap gap-2" aria-label="Saran jawaban">
                                <template x-for="reply in quickReplies" :key="reply"><button type="button" @click="sendChat(reply)" :disabled="busy" x-text="reply" class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-medium text-[#0A3D91] transition hover:-translate-y-0.5 hover:border-[#1565D8] dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200"></button></template>
                            </div>
                            <form @submit.prevent="sendChat()" class="flex items-end gap-2">
                                <div class="min-w-0 flex-1"><label for="ai-scheduler-prompt" class="sr-only">Permintaan Anda</label><textarea id="ai-scheduler-prompt" x-model="composer" @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); sendChat(); }" rows="2" maxlength="3000" placeholder="Tulis kebutuhan atau jawab pertanyaan Schedora…" class="block max-h-32 min-h-12 w-full resize-none rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[#1565D8] focus:ring-[#1565D8] dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"></textarea></div>
                                <button type="submit" :disabled="busy || !composer.trim()" class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-[#1565D8] text-white transition hover:-translate-y-0.5 hover:bg-[#0A3D91] disabled:translate-y-0 disabled:opacity-40" aria-label="Kirim ke Gemini AI"><span aria-hidden="true">➤</span></button>
                            </form>
                            <p class="text-[11px] text-zinc-500">Enter untuk mengirim • Shift+Enter untuk baris baru</p>
                        </div>
                    </div>
                    <aside class="space-y-4 bg-zinc-50/70 p-4 dark:bg-zinc-900/50 sm:p-5" aria-label="Draft Kebutuhan Kelas">
                        <div><div class="flex items-center justify-between gap-3"><h4 class="text-sm font-bold text-zinc-900 dark:text-white">Draft Kebutuhan Kelas</h4><span class="text-xs font-semibold text-[#1565D8]" x-text="`${draftProgress()}%`"></span></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700"><div class="h-full rounded-full bg-[#1565D8] transition-all duration-500" :style="`width: ${draftProgress()}%`"></div></div></div>
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div class="col-span-2 rounded-xl bg-white p-3 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700"><dt class="text-[11px] text-zinc-500">Materi / Program</dt><dd class="mt-1 font-semibold text-zinc-900 dark:text-white" x-text="draft.title || 'Belum diisi'"></dd></div>
                            <div class="rounded-xl bg-white p-3 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700"><dt class="text-[11px] text-zinc-500">Siswa</dt><dd class="mt-1 font-semibold dark:text-white" x-text="draft.student_count ? `${draft.student_count} orang` : '—'"></dd></div>
                            <div class="rounded-xl bg-white p-3 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700"><dt class="text-[11px] text-zinc-500">Pertemuan</dt><dd class="mt-1 font-semibold dark:text-white" x-text="draft.total_meetings ? `${draft.total_meetings} kali` : '—'"></dd></div>
                            <div class="col-span-2 rounded-xl bg-white p-3 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700"><dt class="text-[11px] text-zinc-500">Periode</dt><dd class="mt-1 font-semibold dark:text-white" x-text="formatPeriod(draft.start_date, draft.end_date)"></dd></div>
                        </dl>
                        <div x-show="aiMissing.length" class="space-y-2"><p class="text-xs font-semibold text-zinc-700 dark:text-zinc-200">Masih perlu dilengkapi</p><div class="flex flex-wrap gap-1.5"><template x-for="field in aiMissing" :key="field"><span x-text="missingLabels[field] || field" class="rounded-full bg-amber-100 px-2 py-1 text-[11px] font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"></span></template></div></div>
                        <div x-show="aiReady" x-transition class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">Draft percakapan lengkap. Engine G3 siap memeriksa slot nyata.</div>
                        <button type="button" @click="inputMode = 'manual'; draft.input_mode = 'manual_input'" class="w-full rounded-lg border border-[#1565D8] px-3 py-2 text-xs font-semibold text-[#1565D8] hover:bg-blue-50 dark:hover:bg-blue-950/40">Lengkapi melalui Input Manual</button>
                    </aside>
                </div>
            </section>

            <section
                x-show="inputMode === 'manual'"
                style="display: none"
                aria-label="Form Input Manual kebutuhan jadwal"
                class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 sm:p-6"
            >
                <div class="mb-5">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">Input Parameter Jadwal</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Data di sini langsung memperbarui draft yang sama dengan AI Assistant.</p>
                </div>
                <form @input="validationChecked = false" @change="validationChecked = false" @submit.prevent="checkDraft()" class="space-y-6">
                    <fieldset class="space-y-4">
                        <legend class="mb-3 text-sm font-bold text-[#0A3D91] dark:text-blue-300">Informasi Kelas</legend>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="scheduler-department" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Bidang</label>
                                <select id="scheduler-department" x-model="draft.department" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"><option value="">Pilih bidang</option><option value="computer">Komputer</option><option value="speakout">SpeakOut</option></select>
                            </div>
                            <div class="space-y-1.5">
                                <label for="scheduler-kind" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Jenis Kelas</label>
                                <select id="scheduler-kind" x-model="draft.class_kind" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"><option value="">Pilih jenis kelas</option><option value="regular">Reguler</option><option value="private">Privat</option></select>
                            </div>
                            <div class="space-y-1.5">
                                <label for="scheduler-title" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Program / Materi / Level</label>
                                <input id="scheduler-title" type="text" x-model="draft.title" maxlength="150" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                            </div>
                            <div class="space-y-1.5">
                                <label for="scheduler-students" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Jumlah Siswa</label>
                                <input id="scheduler-students" type="number" x-model.number="draft.student_count" min="1" step="1" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                            </div>
                        </div>
                    </fieldset>
                    <fieldset class="space-y-4 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <legend class="text-sm font-bold text-[#0A3D91] dark:text-blue-300">Periode dan Pola Pertemuan</legend>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5"><label for="scheduler-start" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tanggal Mulai</label><input id="scheduler-start" type="date" x-model="draft.start_date" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /></div>
                            <div class="space-y-1.5"><label for="scheduler-end" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tanggal Selesai</label><input id="scheduler-end" type="date" x-model="draft.end_date" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /></div>
                            <div class="space-y-1.5"><label for="scheduler-total" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Jumlah Pertemuan</label><input id="scheduler-total" type="number" min="1" step="1" x-model.number="draft.total_meetings" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /></div>
                            <div class="space-y-1.5"><label for="scheduler-frequency" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Frekuensi per Minggu</label><select id="scheduler-frequency" x-model.number="draft.frequency_per_week" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"><option value="">Pilih frekuensi</option><option value="1">1 kali</option><option value="2">2 kali</option><option value="3">3 kali</option><option value="4">4 kali</option><option value="5">5 kali</option><option value="6">6 kali</option></select></div>
                            <div class="space-y-1.5"><label for="scheduler-duration" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Durasi per Pertemuan</label><select id="scheduler-duration" x-model.number="draft.session_duration_minutes" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"><option value="">Pilih durasi</option><option value="60">60 menit</option><option value="90">90 menit</option><option value="120">120 menit</option><option value="180">180 menit</option></select></div>
                            <div class="space-y-1.5"><p id="scheduler-days-label" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Preferensi Hari</p><div class="flex flex-wrap gap-2" role="group" aria-labelledby="scheduler-days-label">
                                <template x-for="day in [{code:'monday',name:'Sen'},{code:'tuesday',name:'Sel'},{code:'wednesday',name:'Rab'},{code:'thursday',name:'Kam'},{code:'friday',name:'Jum'},{code:'saturday',name:'Sab'}]" :key="day.code"><label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-zinc-200 px-2 py-2 text-xs dark:border-zinc-700"><input type="checkbox" :value="day.code" x-model="draft.preferred_weekdays" class="rounded border-zinc-300 text-[#1565D8]" /><span x-text="day.name"></span></label></template>
                            </div></div>
                            <div class="space-y-1.5"><label for="scheduler-time-start" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Jam Mulai Preferensi</label><input id="scheduler-time-start" type="time" x-model="draft.preferred_time_window.start" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /></div>
                            <div class="space-y-1.5"><label for="scheduler-time-end" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Jam Selesai Preferensi</label><input id="scheduler-time-end" type="time" x-model="draft.preferred_time_window.end" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /></div>
                        </div>
                    </fieldset>
                    <fieldset class="space-y-4 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <legend class="text-sm font-bold text-[#0A3D91] dark:text-blue-300">Instruktur dan Ruangan</legend>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5"><label for="scheduler-instructor" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Instruktur (Opsional)</label><select id="scheduler-instructor" disabled aria-disabled="true" class="w-full cursor-not-allowed rounded-lg border border-zinc-300 bg-zinc-100 px-3 py-2.5 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"><option>Dipilih otomatis oleh engine dari data master</option></select></div>
                            <div class="space-y-1.5"><label for="scheduler-room" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Ruangan (Opsional)</label><select id="scheduler-room" disabled aria-disabled="true" class="w-full cursor-not-allowed rounded-lg border border-zinc-300 bg-zinc-100 px-3 py-2.5 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"><option>Dipilih otomatis oleh engine dari data master</option></select></div>
                        </div>
                        <div class="space-y-1.5"><label for="scheduler-facilities" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Fasilitas yang Dibutuhkan</label><input id="scheduler-facilities" type="text" x-model="draft.facility_notes" placeholder="Contoh: komputer, proyektor, internet" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" /><p class="text-xs text-zinc-500 dark:text-zinc-400">Catatan ini bersifat informasi; penapisan fasilitas wajib membutuhkan kode fasilitas resmi pada draft.</p></div>
                    </fieldset>
                    <div class="space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">Pemeriksaan berikut hanya mengecek kelengkapan draft di browser, belum memvalidasi ketersediaan instruktur atau ruangan.</p>
                            <button type="submit" class="rounded-lg border border-[#1565D8] px-4 py-2 text-sm font-semibold text-[#1565D8] hover:bg-blue-50 dark:hover:bg-blue-950/40">Periksa Kelengkapan Draft</button>
                        </div>
                        <div x-show="validationChecked" style="display: none" aria-live="polite" class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                            <p x-show="draftErrors.length === 0" class="font-semibold text-emerald-700 dark:text-emerald-300">Draft formulir lengkap. Ini bukan hasil analisis AI atau pemeriksaan bentrok jadwal.</p>
                            <div x-show="draftErrors.length > 0"><p class="font-semibold text-amber-800 dark:text-amber-200">Perbaiki kebutuhan kelas berikut:</p><ul class="mt-2 list-inside list-disc space-y-1"><template x-for="error in draftErrors" :key="error"><li x-text="error"></li></template></ul></div>
                        </div>
                    </div>
                </form>
            </section>

            <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row sm:items-center sm:justify-between">
                <p class="max-w-2xl text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                    Opsi berasal dari Scheduling Engine G3. Seluruh pertemuan diperiksa terhadap instruktur, ruang, kompetensi, dan benturan jadwal resmi.
                </p>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
                    <button type="button" @click="searchReal()" :disabled="busy" class="min-h-10 w-full rounded-lg border border-[#1565D8] bg-white px-4 py-2 text-sm font-semibold text-[#1565D8] hover:bg-blue-50 dark:bg-zinc-900 dark:hover:bg-blue-950/40 sm:w-auto">
                        Cari Alternatif Jadwal Nyata
                    </button>
                </div>
            </div>
            </div>{{-- End step 01: shared draft --}}

            {{-- Engine-validated alternatives; pending proposals are non-reserving. --}}
            <section x-show="stage === 'recommendations'" style="display: none" class="space-y-5" aria-label="Rekomendasi jadwal hasil engine">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Alternatif Jadwal</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Pilih rekomendasi berdasarkan data aktual. Slot belum direservasi hingga disetujui BAAK.</p>
                    </div>
                    <button type="button" @click="stage = 'draft'; expandedOptionId = null" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">← Ubah Kebutuhan</button>
                </div>
                <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-700">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Ringkasan Kebutuhan Kelas</h3>
                    <dl class="mt-3 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <div><dt class="text-xs text-zinc-500">Materi / Program</dt><dd class="mt-1 font-semibold text-zinc-900 dark:text-white" x-text="draft.title"></dd></div>
                        <div><dt class="text-xs text-zinc-500">Jumlah Siswa</dt><dd class="mt-1 font-semibold text-zinc-900 dark:text-white" x-text="`${draft.student_count} orang`"></dd></div>
                        <div><dt class="text-xs text-zinc-500">Pertemuan</dt><dd class="mt-1 font-semibold text-zinc-900 dark:text-white" x-text="`${draft.total_meetings} kali`"></dd></div>
                        <div><dt class="text-xs text-zinc-500">Periode</dt><dd class="mt-1 font-semibold text-zinc-900 dark:text-white" x-text="formatPeriod(draft.start_date, draft.end_date)"></dd></div>
                    </dl>
                </div>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900" role="note">
                    Semua opsi berasal dari pemeriksaan engine G3 terhadap database. Status sementara, bukan reservasi resmi.
                </div>
                <div id="scheduler-example-options" class="grid min-w-0 grid-cols-1 gap-3" aria-label="Rekomendasi jadwal berdasarkan database">
                    <template x-for="option in exampleOptions" :key="option.id">
                        <article x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-3 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" class="flex min-w-0 flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                            <div>
                                <h3 class="text-lg font-bold text-zinc-900 dark:text-white" x-text="option.label"></h3>
                                <p class="mt-1 text-xs text-zinc-500" x-text="option.subtitle"></p>
                            </div>
                            <div class="border-t border-zinc-100 pt-3 text-sm dark:border-zinc-800">
                                <p class="font-semibold text-zinc-900 dark:text-white" x-text="option.dayLabel"></p>
                                <p class="mt-1 text-[#1565D8] dark:text-blue-300" x-text="`${option.sessions[0].start}–${option.sessions[0].end} WIB`"></p>
                                <p class="mt-2 text-xs text-zinc-500" x-text="`${option.sessions.length} pertemuan terverifikasi`"></p>
                                <p class="mt-1 text-xs text-zinc-500"><span x-text="`${option.sessions[0].instructor} • ${option.sessions[0].room}`"></span></p>
                            </div>
                            <div class="mt-auto grid gap-2 pt-1">
                                <button type="button" @click="expandedOptionId = expandedOptionId === option.id ? null : option.id" :aria-expanded="expandedOptionId === option.id" aria-controls="scheduler-example-option-detail" :aria-label="`Lihat detail ${option.label}`" class="min-h-10 rounded-lg border border-[#1565D8] px-3 py-2 text-sm font-semibold text-[#1565D8] hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-[#1565D8] dark:hover:bg-blue-950/40">Lihat Detail</button>
                                <button type="button" @click="chooseExample(option.id)" :aria-label="`Pilih ${option.label}`" class="min-h-10 rounded-lg bg-[#1565D8] px-3 py-2 text-sm font-semibold text-white hover:bg-[#0A3D91] focus-visible:outline-2 focus-visible:outline-[#1565D8]">Pilih Opsi</button>
                            </div>
                        </article>
                    </template>
                </div>
                <template x-if="expandedOption()">
                    <div id="scheduler-example-option-detail" class="min-w-0 space-y-4 rounded-2xl border border-[#1565D8] bg-white p-4 dark:bg-zinc-900 sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div><h3 class="text-lg font-bold text-zinc-900 dark:text-white" x-text="`Detail ${expandedOption().label}`"></h3><p class="text-xs text-zinc-500">Kalender & seluruh pertemuan — data hasil engine</p></div>
                            <button type="button" @click="expandedOptionId = null" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-700 dark:text-white">Tutup</button>
                        </div>
                        <p class="rounded-lg bg-blue-50 p-3 text-sm text-[#0A3D91] dark:bg-blue-950/40 dark:text-blue-200" x-text="expandedOption().rationale"></p>
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-white">Kalender Pertemuan</h4>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <template x-for="meeting in expandedOption().sessions" :key="meeting.number">
                                <div class="rounded-lg border border-zinc-200 p-3 text-center dark:border-zinc-700">
                                    <p class="text-xs font-semibold text-[#1565D8]" x-text="`Pertemuan ${meeting.number}`"></p>
                                    <p class="mt-1 text-xs text-zinc-700 dark:text-zinc-200" x-text="meeting.date"></p>
                                </div>
                            </template>
                        </div>
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-white">Daftar Seluruh Pertemuan</h4>
                        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <table class="w-full min-w-[490px] text-left text-sm">
                                <thead class="bg-zinc-50 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"><tr><th scope="col" class="px-3 py-2">Ke-</th><th scope="col" class="px-3 py-2">Tanggal</th><th scope="col" class="px-3 py-2">Jam</th><th scope="col" class="px-3 py-2">Instruktur / Ruangan</th></tr></thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800"><template x-for="meeting in expandedOption().sessions" :key="meeting.iso"><tr><td class="px-3 py-2" x-text="meeting.number"></td><td class="px-3 py-2" x-text="meeting.date"></td><td class="px-3 py-2" x-text="`${meeting.start}–${meeting.end}`"></td><td class="px-3 py-2 text-zinc-600" x-text="`${meeting.instructor} / ${meeting.room}`"></td></tr></template></tbody>
                            </table>
                        </div>
                        <button type="button" @click="chooseExample(expandedOptionId)" class="min-h-10 w-full rounded-lg bg-[#1565D8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0A3D91] sm:w-auto">Pilih Opsi Ini</button>
                    </div>
                </template>
            </section>

            <section x-show="stage === 'review'" style="display: none" class="space-y-4" aria-label="Tinjauan akhir pengajuan">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><h2 class="text-lg font-bold text-zinc-900 dark:text-white">Tinjauan Akhir Pengajuan</h2><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Ringkasan pilihan contoh sebelum pengajuan resmi.</p></div>
                    <button type="button" @click="stage = 'recommendations'" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold dark:border-zinc-700 dark:text-white">← Kembali ke Rekomendasi</button>
                </div>
                <template x-if="selectedOption()">
                    <div class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                        <div class="border-b border-zinc-100 pb-3 dark:border-zinc-800">
                            <p class="text-lg font-bold text-zinc-900 dark:text-white" x-text="`${selectedOption().label} — ${draft.title}`"></p>
                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Opsi hasil validasi engine; belum diajukan atau disetujui BAAK.</p>
                        </div>
                        <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                            <div><dt class="text-xs text-zinc-500">Bidang</dt><dd class="mt-1 font-semibold dark:text-white" x-text="draft.department === 'computer' ? 'Komputer' : 'SpeakOut'"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Jenis Kelas</dt><dd class="mt-1 font-semibold dark:text-white" x-text="draft.class_kind === 'regular' ? 'Reguler' : 'Privat'"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Siswa</dt><dd class="mt-1 font-semibold dark:text-white" x-text="`${draft.student_count} orang`"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Pertemuan</dt><dd class="mt-1 font-semibold dark:text-white" x-text="`${draft.total_meetings} kali`"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Hari</dt><dd class="mt-1 font-semibold dark:text-white" x-text="selectedOption().dayLabel"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Jam</dt><dd class="mt-1 font-semibold dark:text-white" x-text="`${selectedOption().sessions[0].start}–${selectedOption().sessions[0].end} WIB`"></dd></div>
                            <div><dt class="text-xs text-zinc-500">Instruktur</dt><dd class="mt-1 font-semibold dark:text-white"><span x-text="selectedOption().sessions[0].instructor"></span></dd></div>
                            <div><dt class="text-xs text-zinc-500">Ruangan</dt><dd class="mt-1 font-semibold dark:text-white"><span x-text="selectedOption().sessions[0].room"></span></dd></div>
                        </dl>
                    </div>
                </template>
                <div class="space-y-2 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Alur Persetujuan AO → BAAK</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">Pengajuan AO disimpan sebagai <strong>pending</strong>. Belum membuat jadwal resmi. Persetujuan BAAK dilakukan terpisah.</p>
                </div>
                <button type="button" @click="submitReal()" :disabled="busy" class="w-full rounded-lg bg-[#1565D8] px-4 py-3 text-sm font-semibold text-white disabled:opacity-50" x-text="busy ? 'Menyimpan...' : 'Ajukan sebagai Pending (AO)'"></button>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Hanya pengguna dengan peran AO dapat mengajukan. Engine memvalidasi ulang opsi sebelum penyimpanan; tidak membuat jadwal resmi.</p>
            </section>
        </section>

        <section
            id="scheduler-panel-leave"
            role="tabpanel"
            aria-labelledby="scheduler-tab-leave"
            x-show="activeTab === 'leave'"
            style="display: none"
            class="space-y-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Rekomendasi otomatis instruktur pengganti dan penyesuaian ruangan ketika terjadi izin.
                </p>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-400">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    Tahap 1: Deterministik Aktif
                </span>
            </div>
            {{-- Do not rewrite the working Livewire component: preserve selector, engine, Gemini, results, and actions. --}}
            <livewire:ai-scheduler />
        </section>
    </main>
</x-layouts::app>
