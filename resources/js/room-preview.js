// PALCOM AI Scheduler V2: Ruangan & Lab modal state & dialog manager.
window.palcomRoomPreview = () => ({
    mode: 'create',
    selected: null,
    form: { name: '', department: '', capacity: '', status: 'available', notes: '' },

    init() {
        window.addEventListener('close-room-form-modal', () => {
            if (this.$refs.roomFormDialog?.open) {
                this.$refs.roomFormDialog.close();
            }
        });
        window.addEventListener('open-room-form-modal', (event) => {
            const detail = event.detail?.[0] ?? event.detail ?? {};
            this.mode = detail.mode ?? 'create';
            if (detail.room) {
                this.selected = detail.room;
                this.form = {
                    name: detail.room.name ?? '',
                    department: detail.room.department ?? '',
                    capacity: detail.room.capacity ?? '',
                    status: detail.room.status ?? 'available',
                    notes: detail.room.notes ?? '',
                };
            } else {
                this.form = { name: '', department: '', capacity: '', status: 'available', notes: '' };
            }
            if (this.$refs.roomFormDialog && !this.$refs.roomFormDialog.open) {
                this.$nextTick(() => this.$refs.roomFormDialog.showModal());
            }
        });
        window.addEventListener('open-room-detail-modal', (event) => {
            const detail = event.detail?.[0] ?? event.detail ?? {};
            if (detail.room) {
                this.selected = detail.room;
            }
            if (this.$refs.roomDetailDialog && !this.$refs.roomDetailDialog.open) {
                this.$nextTick(() => this.$refs.roomDetailDialog.showModal());
            }
        });
        window.addEventListener('close-room-detail-modal', () => {
            if (this.$refs.roomDetailDialog?.open) {
                this.$refs.roomDetailDialog.close();
            }
        });
    },

    statusLabel(status) {
        return {
            available: 'Operasional',
            maintenance: 'Maintenance / Perawatan',
            unavailable: 'Tidak Operasional',
        }[status] ?? 'Status tidak dikenal';
    },
    openCreate() {
        this.mode = 'create';
        this.selected = null;
        this.form = { name: '', department: '', capacity: '', status: 'available', notes: '' };
        this.$nextTick(() => this.$refs.roomFormDialog?.showModal());
    },
    openEdit(room) {
        this.mode = 'edit';
        this.selected = room;
        this.form = {
            name: room.name,
            department: room.department ?? '',
            capacity: room.capacity,
            status: room.status,
            notes: room.notes ?? '',
        };
        this.$nextTick(() => this.$refs.roomFormDialog?.showModal());
    },
    openDetail(room) {
        this.selected = room;
        this.$nextTick(() => this.$refs.roomDetailDialog?.showModal());
    },
    closeForm() {
        this.$refs.roomFormDialog?.close();
    },
    closeDetail() {
        this.$refs.roomDetailDialog?.close();
    },
});
