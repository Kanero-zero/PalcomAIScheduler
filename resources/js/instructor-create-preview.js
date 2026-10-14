// Shared frontend implementation for Instructor Create and Edit forms (PALCOM AI Scheduler V2).
// Supports master competency catalog injection and full two-way hydration from database records.
window.palcomInstructorCreate = (initial = null, masterCatalog = []) => ({
    isEditing: Boolean(initial),
    savedSkills: Array.isArray(initial?.skills) ? initial.skills : [],
    name: initial?.name ? String(initial.name) : '',
    department: initial?.department ? String(initial.department) : (initial ? '' : 'computer'),
    status: initial?.status ? String(initial.status) : (initial ? '' : 'active'),
    notes: initial?.notes ? String(initial.notes) : '',
    bulkLevel: 'advanced',
    previewCatalog: Array.isArray(masterCatalog) ? masterCatalog : [],
    selections: [],
    sequence: 0,
    days: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'].map(label => ({
        label,
        enabled: false,
        slots: [],
    })),

    init() {
        if (!this.isEditing) return;

        // Hydrate availability slots from saved database records
        if (Array.isArray(initial?.availabilities)) {
            const availMap = {};
            initial.availabilities.forEach(a => {
                if (!availMap[a.day]) availMap[a.day] = [];
                availMap[a.day].push({ key: ++this.sequence, start: a.start_time, end: a.end_time });
            });
            this.days.forEach(day => {
                if (availMap[day.label] && availMap[day.label].length > 0) {
                    day.enabled = true;
                    day.slots = availMap[day.label];
                }
            });
        }

        // Hydrate selections matching the current department
        if (this.department) {
            this.departmentChanged();
        }
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

        const used = new Set();
        this.savedSkills.forEach(saved => {
            let match = null;
            if (saved.competency_id) {
                match = this.catalog.find(item => Number(item.id) === Number(saved.competency_id));
            }
            if (!match) {
                match = this.catalog.find(item => item.name.toLowerCase() === String(saved.skill).trim().toLowerCase());
            }
            if (!match || used.has(match.id)) return;
            used.add(match.id);
            this.selections.push({
                key: ++this.sequence,
                id: match.id,
                level: ['beginner', 'intermediate', 'advanced'].includes(saved.level) ? saved.level : 'intermediate',
            });
        });
    },
    get unmatchedSkills() {
        if (!this.isEditing) return [];
        return this.savedSkills.filter(saved => !this.catalog.some(item =>
            (saved.competency_id && Number(item.id) === Number(saved.competency_id)) ||
            item.name.toLowerCase() === String(saved.skill).trim().toLowerCase()));
    },
    addCompetency() {
        const next = this.catalog.find(item => !this.selections.some(selected => Number(selected.id) === Number(item.id)));
        if (next) this.selections.push({ key: ++this.sequence, id: next.id, level: this.bulkLevel });
    },
    removeCompetency(key) {
        this.selections = this.selections.filter(item => item.key !== key);
    },
    changeCompetency(key, id) {
        if (this.selections.some(item => Number(item.id) === Number(id) && item.key !== key)) return;
        const selection = this.selections.find(item => item.key === key);
        if (selection) selection.id = Number(id);
    },
    toggleAll(checked) {
        if (checked) {
            this.selections = this.catalog.map(item => ({
                key: ++this.sequence,
                id: item.id,
                level: this.selections.find(selected => Number(selected.id) === Number(item.id))?.level ?? this.bulkLevel,
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
        if (!slot.start || !slot.end) return false;
        if (slot.end <= slot.start) return true;
        return day.slots.some(other => other.key !== slot.key && other.start && other.end
            && slot.start < other.end && slot.end > other.start);
    },
});
