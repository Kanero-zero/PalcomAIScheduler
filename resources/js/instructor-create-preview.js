// Shared, frontend-only preview for the approved Instructor Create and Edit forms (PALCOM AI Scheduler V2).
// No persistence, API calls, or backend mutations are performed here.
// The preview catalog MUST be replaced by the shared competency master from backend.
window.palcomInstructorCreate = (initial = null) => ({
    isEditing: Boolean(initial),
    savedSkills: Array.isArray(initial?.skills) ? initial.skills : [],
    name: '',
    // The current Instructor table has no department field. Never assume a saved value.
    department: initial ? '' : 'computer',
    status: initial ? '' : 'active',
    notes: '',
    bulkLevel: 'advanced',
    // Illustrative values ONLY. Not an authoritative list of course competencies.
    previewCatalog: [
        { id: 'preview-word', name: 'Microsoft Word', department: 'computer' },
        { id: 'preview-excel', name: 'Microsoft Excel', department: 'computer' },
        { id: 'preview-powerpoint', name: 'Microsoft PowerPoint', department: 'computer' },
        { id: 'preview-design', name: 'Desain Grafis', department: 'computer' },
        { id: 'preview-web', name: 'Web Programming', department: 'computer' },
        { id: 'preview-ai', name: 'AI & Python', department: 'computer' },
        { id: 'preview-speakout-1', name: 'SpeakOut Level 1', department: 'speakout' },
        { id: 'preview-speakout-2', name: 'SpeakOut Level 2', department: 'speakout' },
        { id: 'preview-speakout-3', name: 'SpeakOut Level 3', department: 'speakout' },
    ],
    selections: [],
    sequence: 0,
    days: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'].map(label => ({
        label,
        enabled: false,
        slots: [],
    })),

    init() {
        if (!this.isEditing) return;
        this.name = String(initial.name ?? '');
        this.status = ['active', 'inactive'].includes(initial.status) ? initial.status : '';
        // Notes, department and availability are not stored in the current schema.
        // Do not hydrate them with fabricated data.
    },
    get catalog() {
        return this.previewCatalog.filter(item => item.department === this.department);
    },
    get allSelected() {
        return this.catalog.length > 0 && this.selections.length === this.catalog.length;
    },
    departmentChanged() {
        this.selections = [];
        if (!this.isEditing || !this.department) return;

        // Preview only: exact-name match with the illustrative catalog. Unmatched
        // saved skills remain visible separately, rather than silently disappearing.
        const used = new Set();
        this.savedSkills.forEach(saved => {
            const match = this.catalog.find(item => item.name.toLowerCase() === String(saved.skill).trim().toLowerCase());
            if (!match || used.has(match.id)) return;
            used.add(match.id);
            this.selections.push({
                key: ++this.sequence,
                id: match.id,
                level: ['beginner', 'intermediate', 'advanced'].includes(saved.level) ? saved.level : '',
            });
        });
    },
    get unmatchedSkills() {
        if (!this.isEditing) return [];
        return this.savedSkills.filter(saved => !this.catalog.some(item =>
            item.name.toLowerCase() === String(saved.skill).trim().toLowerCase()));
    },
    addCompetency() {
        const next = this.catalog.find(item => !this.selections.some(selected => selected.id === item.id));
        if (next) this.selections.push({ key: ++this.sequence, id: next.id, level: this.bulkLevel });
    },
    removeCompetency(key) {
        this.selections = this.selections.filter(item => item.key !== key);
    },
    changeCompetency(key, id) {
        if (this.selections.some(item => item.id === id && item.key !== key)) return;
        const selection = this.selections.find(item => item.key === key);
        if (selection) selection.id = id;
    },
    toggleAll(checked) {
        if (checked) {
            this.selections = this.catalog.map(item => ({
                key: ++this.sequence,
                id: item.id,
                level: this.selections.find(selected => selected.id === item.id)?.level ?? this.bulkLevel,
            }));
        } else {
            this.selections = [];
        }
    },
    applyBulkLevel() {
        this.selections.forEach(selection => { selection.level = this.bulkLevel; });
    },
    toggleDay(day) {
        if (day.enabled && day.slots.length === 0) this.addSlot(day);
        if (!day.enabled) day.slots = [];
    },
    addSlot(day) {
        day.enabled = true;
        day.slots.push({ key: ++this.sequence, start: '', end: '' });
    },
    removeSlot(day, key) {
        day.slots = day.slots.filter(slot => slot.key !== key);
        if (day.slots.length === 0) day.enabled = false;
    },
    invalidSlot(day, slot) {
        if (!slot.start || !slot.end) return false; // Incomplete inputs are indicated by HTML required.
        if (slot.end <= slot.start) return true;
        return day.slots.some(other => other.key !== slot.key && other.start && other.end
            && slot.start < other.end && slot.end > other.start);
    },
});
