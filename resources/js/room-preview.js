// PALCOM AI Scheduler V2: Ruangan & Lab frontend preview only.
// No writes, API calls, or scheduling decisions are performed here.
window.palcomRoomPreview = () => ({
    mode: 'create',
    selected: null,
    form: { name: '', department: 'computer', capacity: '', status: 'available', notes: '' },

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
        this.form = { name: '', department: 'computer', capacity: '', status: 'available', notes: '' };
        this.$nextTick(() => this.$refs.roomFormDialog.showModal());
    },
    openEdit(room) {
        this.mode = 'edit';
        this.selected = room;
        // The legacy rooms table does not hold department or notes.
        // Do not prefill them with fabricated data.
        this.form = {
            name: room.name,
            department: '',
            capacity: room.capacity,
            status: room.status,
            notes: '',
        };
        this.$nextTick(() => this.$refs.roomFormDialog.showModal());
    },
    openDetail(room) {
        this.selected = room;
        this.$nextTick(() => this.$refs.roomDetailDialog.showModal());
    },
    closeForm() {
        this.$refs.roomFormDialog.close();
    },
    closeDetail() {
        this.$refs.roomDetailDialog.close();
    },
});
