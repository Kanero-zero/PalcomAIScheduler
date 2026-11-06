window.schedoraAiChatbot = () => {
    const root = document.querySelector('[data-schedora-chatbot]');
    const config = {
        agentUrl: root?.dataset.agentUrl,
        agentSubmitUrl: root?.dataset.agentSubmitUrl,
        interpretUrl: root?.dataset.interpretUrl,
        previewUrl: root?.dataset.previewUrl,
        submitUrl: root?.dataset.submitUrl,
    };

    return {
    activeTab: 'create',
    busy: false,
    notice: '',
    aiMissing: [],
    aiModel: '',
    aiReady: false,
    inputMode: 'ai',
    stage: 'draft',
    composer: '',
    quickReplies: [],
    messages: [
        {
            role: 'assistant',
            content: 'Halo, saya Schedora. Ceritakan tujuan kelas dan preferensi Anda. Saya dapat membaca katalog serta kondisi operasional untuk menyusun rekomendasi nyata.',
        },
    ],
    exampleOptions: [],
    expandedOptionId: null,
    selectedOptionId: null,
    validationChecked: false,
    draftErrors: [],
    draft: {
        input_mode: 'ai_assistant',
        title: '',
        department: '',
        class_kind: '',
        student_count: '',
        start_date: '',
        end_date: '',
        total_meetings: '',
        frequency_per_week: '',
        session_duration_minutes: '',
        preferred_weekdays: [],
        preferred_time_window: { start: '', end: '' },
        preferred_instructor_id: null,
        preferred_room_id: null,
        required_facility_codes: [],
        required_competency_ids: [],
        facility_notes: '',
    },
    missingLabels: {
        title: 'materi/program',
        department: 'bidang',
        class_kind: 'jenis kelas',
        student_count: 'jumlah siswa',
        start_date: 'tanggal mulai',
        end_date: 'tanggal selesai',
        total_meetings: 'jumlah pertemuan',
        frequency_per_week: 'frekuensi',
        session_duration_minutes: 'durasi',
        preferred_weekdays: 'hari pilihan',
        preferred_time_window: 'rentang jam',
    },
    selectedOption() {
        return this.exampleOptions.find((option) => option.id === this.selectedOptionId) ?? null;
    },
    expandedOption() {
        return this.exampleOptions.find((option) => option.id === this.expandedOptionId) ?? null;
    },
    draftProgress() {
        const fields = Object.keys(this.missingLabels);
        const complete = fields.filter((field) => {
            const value = this.chatDraft()[field];
            return value !== undefined && value !== '' && (!Array.isArray(value) || value.length > 0);
        }).length;

        return Math.round((complete / fields.length) * 100);
    },
    formatExampleDate(iso) {
        const parts = iso.split('-').map(Number);
        return new Intl.DateTimeFormat('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
            .format(new Date(parts[0], parts[1] - 1, parts[2], 12));
    },
    formatPeriod(startIso, endIso) {
        if (!startIso || !endIso) return 'Belum ditentukan';
        const from = startIso.split('-').map(Number);
        const to = endIso.split('-').map(Number);
        const start = new Date(from[0], from[1] - 1, from[2], 12);
        const end = new Date(to[0], to[1] - 1, to[2], 12);
        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 'Belum ditentukan';
        const full = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        if (from[0] === to[0] && from[1] === to[1]) return `${from[2]}–${full.format(end)}`;
        if (from[0] === to[0]) {
            const short = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
            return `${short.format(start)} – ${full.format(end)}`;
        }
        return `${full.format(start)} – ${full.format(end)}`;
    },
    async requestApi(path, payload) {
        const token = document.querySelector('meta[name=csrf-token]')?.content;
        const response = await fetch(path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token || '',
            },
            body: JSON.stringify(payload),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const first = Object.values(result.errors || {}).flat()[0];
            throw new Error(first || result.message || `Server gagal merespons (HTTP ${response.status}).`);
        }
        return result;
    },
    chatDraft() {
        const days = { monday: 'Senin', tuesday: 'Selasa', wednesday: 'Rabu', thursday: 'Kamis', friday: 'Jumat', saturday: 'Sabtu' };
        const result = {};
        const numbers = ['student_count', 'total_meetings', 'frequency_per_week', 'session_duration_minutes'];
        for (const field of ['title', 'department', 'class_kind', 'start_date', 'end_date', ...numbers]) {
            if (this.draft[field] !== '' && this.draft[field] !== null) {
                result[field] = numbers.includes(field) ? Number(this.draft[field]) : this.draft[field];
            }
        }
        if (this.draft.preferred_weekdays.length) {
            result.preferred_weekdays = this.draft.preferred_weekdays.map((day) => days[day] || day);
        }
        if (this.draft.preferred_time_window.start && this.draft.preferred_time_window.end) {
            result.preferred_time_window = { ...this.draft.preferred_time_window };
        }
        return result;
    },
    canonicalDraft() {
        return {
            ...this.draft,
            ...this.chatDraft(),
            input_mode: this.draft.input_mode,
            preferred_instructor_id: null,
            preferred_room_id: null,
            required_competency_ids: this.draft.required_competency_ids || [],
            required_facility_codes: this.draft.required_facility_codes || [],
        };
    },
    applyAiFields(fields) {
        const inverseDays = { Senin: 'monday', Selasa: 'tuesday', Rabu: 'wednesday', Kamis: 'thursday', Jumat: 'friday', Sabtu: 'saturday' };
        for (const [name, value] of Object.entries(fields)) {
            if (name === 'preferred_weekdays') {
                this.draft.preferred_weekdays = value.map((day) => inverseDays[day]).filter(Boolean);
            } else if (name === 'preferred_time_window') {
                this.draft.preferred_time_window = { ...value };
            } else {
                this.draft[name] = value;
            }
        }
        this.draft.input_mode = 'ai_assistant';
    },
    mapOptions(options, source = 'agent') {
        return (options || []).map((option) => {
            const sessions = (option.proposed_schedules_payload || []).map((slot) => ({
                number: slot.meeting_sequence,
                iso: slot.date,
                date: this.formatExampleDate(slot.date),
                start: slot.start_time.slice(0, 5),
                end: slot.end_time.slice(0, 5),
                instructor: slot.instructor_name,
                room: slot.room_name,
                subject: slot.subject_name,
            }));
            return {
                id: option.option_label,
                label: `Opsi ${option.option_label}`,
                subtitle: `Skor ${option.score} • ${option.conflicts_count || 0} bentrok`,
                dayLabel: [...new Set((option.proposed_schedules_payload || []).map((slot) => slot.day_name))].join(' & '),
                sessions,
                rationale: option.explanation,
                preview_token: option.preview_token,
                source,
                raw: option,
            };
        });
    },
    scrollConversation() {
        this.$nextTick(() => {
            if (this.$refs.conversation) this.$refs.conversation.scrollTo({ top: this.$refs.conversation.scrollHeight, behavior: 'smooth' });
        });
    },
    async sendChat(suggestedMessage = null) {
        const message = String(suggestedMessage ?? this.composer).trim();
        if (this.busy || !message) return;
        const history = this.messages.slice(-12).map(({ role, content }) => ({ role, content }));
        this.messages.push({ role: 'user', content: message });
        this.composer = '';
        this.quickReplies = [];
        this.notice = '';
        this.busy = true;
        this.scrollConversation();
        try {
            const result = await this.requestApi(config.agentUrl, {
                message,
                current_state: this.chatDraft(),
                history,
            });
            this.applyAiFields(result.agent.state || {});
            this.aiMissing = [];
            this.aiModel = result.agent.model || '';
            this.quickReplies = result.agent.clarification?.choices || [];
            const recommendations = this.mapOptions(result.agent.recommendations || [], 'agent');
            if (recommendations.length) {
                this.exampleOptions = recommendations;
                this.selectedOptionId = null;
                this.expandedOptionId = null;
            }
            this.aiReady = recommendations.length > 0;
            this.messages.push({
                role: 'assistant',
                content: result.agent.assistant_message,
                recommendations,
            });
            this.validationChecked = false;
        } catch (error) {
            this.notice = error.message;
            this.composer = message;
            this.messages.push({ role: 'assistant', content: `Maaf, pesan belum dapat diproses. ${error.message}` });
        } finally {
            this.busy = false;
            this.scrollConversation();
        }
    },
    async searchReal() {
        if (this.busy || this.inputMode !== 'manual') return;
        this.checkDraft();
        if (this.draftErrors.length) {
            this.notice = this.draftErrors.join(' ');
            return;
        }
        this.busy = true;
        this.notice = '';
        this.exampleOptions = [];
        try {
            const result = await this.requestApi(config.previewUrl, { draft: this.canonicalDraft() });
            this.exampleOptions = this.mapOptions(result.options || [], 'manual');
            this.selectedOptionId = null;
            this.expandedOptionId = null;
            if (!this.exampleOptions.length) {
                this.notice = (result.reasons || []).join(' ') || 'Tidak ada jadwal valid untuk parameter ini.';
                return;
            }
            this.stage = 'recommendations';
            this.notice = `${this.exampleOptions.length} opsi valid ditemukan berdasarkan data instruktur dan ruangan saat ini.`;
        } catch (error) {
            this.notice = error.message;
        } finally {
            this.busy = false;
        }
    },
    async submitReal() {
        if (this.busy || !this.selectedOption()?.preview_token) return;
        this.busy = true;
        this.notice = '';
        try {
            const isAgentOption = this.selectedOption().source === 'agent';
            const result = await this.requestApi(isAgentOption ? config.agentSubmitUrl : config.submitUrl, {
                ...(isAgentOption ? {} : { draft: this.canonicalDraft() }),
                option_label: this.selectedOptionId,
                preview_token: this.selectedOption().preview_token,
            });
            this.notice = `Proposal #${result.proposal_id} tersimpan dengan status pending. Belum menjadi jadwal resmi.`;
        } catch (error) {
            this.notice = error.message;
        } finally {
            this.busy = false;
        }
    },
    chooseExample(id) {
        if (!this.exampleOptions.some((option) => option.id === id)) return;
        this.selectedOptionId = id;
        this.expandedOptionId = null;
        this.stage = 'review';
    },
    checkDraft() {
        const draft = this.draft;
        const errors = [];
        if (!String(draft.title).trim()) errors.push('Nama program atau materi harus diisi.');
        if (!['computer', 'speakout'].includes(draft.department)) errors.push('Bidang kelas harus dipilih.');
        if (!['regular', 'private'].includes(draft.class_kind)) errors.push('Jenis kelas harus dipilih.');
        if (!Number.isInteger(Number(draft.student_count)) || Number(draft.student_count) < 1) errors.push('Jumlah siswa harus minimal 1.');
        if (!draft.start_date || !draft.end_date || draft.end_date <= draft.start_date) errors.push('Periode mulai dan selesai tidak valid.');
        if (!Number.isInteger(Number(draft.total_meetings)) || Number(draft.total_meetings) < 1) errors.push('Jumlah pertemuan harus minimal 1.');
        if (!Number.isInteger(Number(draft.frequency_per_week)) || Number(draft.frequency_per_week) < 1 || Number(draft.frequency_per_week) > 6) errors.push('Frekuensi harus antara 1 dan 6 per minggu.');
        if (![60, 90, 120, 180].includes(Number(draft.session_duration_minutes))) errors.push('Durasi harus 60, 90, 120, atau 180 menit.');
        if (!draft.preferred_weekdays.length || draft.preferred_weekdays.length < Number(draft.frequency_per_week)) errors.push('Hari pilihan harus tersedia sebanyak frekuensi per minggu.');
        if (!draft.preferred_time_window.start || !draft.preferred_time_window.end || draft.preferred_time_window.end <= draft.preferred_time_window.start) errors.push('Rentang jam preferensi tidak valid.');
        this.draftErrors = errors;
        this.validationChecked = true;
        this.aiReady = errors.length === 0;
    },
    };
};
