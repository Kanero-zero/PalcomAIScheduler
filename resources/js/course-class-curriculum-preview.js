// Patch 05 V2 — UI preview ONLY. Templates here are illustrative and never persisted.
// The real curriculum, cohort snapshots and approved schedules require Anggota 2 backend.
const examplePrograms = [
    { id: 'professional', name: 'Profesional 1 Tahun', department: 'computer', duration: '12 bulan', frequency: '5×/minggu', session: '120 menit', periods: [
        { name: 'Triwulan 1', duration: '3 bulan', tracks: [{ name: 'Microsoft Office', weekly: 3, modules: [{ name: 'Word', target: '' }, { name: 'Excel', target: '' }] }, { name: 'IoT', weekly: 2, modules: [] }] },
        { name: 'Triwulan 2', duration: '3 bulan', tracks: [] },
        { name: 'Triwulan 3', duration: '3 bulan', tracks: [] },
        { name: 'Triwulan 4', duration: '3 bulan', tracks: [] },
    ] },
    { id: 'ptik', name: 'PTIK 10 Bulan', department: 'computer', duration: '10 bulan', frequency: '3×/minggu', session: '120 menit', periods: [
        { name: 'Semester 1', duration: '5 bulan', tracks: [{ name: 'Microsoft Office', weekly: 2, modules: [{ name: 'Word', target: '' }, { name: 'Excel', target: '' }] }, { name: 'IoT', weekly: 1, modules: [] }] },
        { name: 'Semester 2', duration: '5 bulan', tracks: [{ name: 'Desain Grafis', weekly: 1, modules: [] }, { name: 'Aplikasi Web', weekly: 1, modules: [] }, { name: 'AI Programming', weekly: 1, modules: [] }] },
    ] },
    { id: 'shortcourse', name: 'Short Course', department: 'computer', duration: '2 bulan', frequency: '2×/minggu', session: '90 menit', periods: [] },
    { id: 'express', name: 'Express', department: 'computer', duration: '1 pertemuan', frequency: '1×', session: '240 menit', periods: [] },
    { id: 'bootcamp', name: 'Bootcamp', department: 'computer', duration: '4 bulan', frequency: 'Belum diputuskan', session: 'Belum diputuskan', periods: [] },
    { id: 'speakout', name: 'SpeakOut', department: 'speakout', duration: 'Belum diputuskan', frequency: 'Belum diputuskan', session: 'Belum diputuskan', periods: [] },
];

const freshClass = () => ({ name: '', department: 'computer', program: 'ptik', kind: 'regular', status: 'upcoming', count: '', start: '', end: '', notes: '', version: 'example-published' });
const copy = (value) => JSON.parse(JSON.stringify(value));

window.palcomCourseClassV2 = () => ({
    tab: 'classes',
    screen: 'index', // index | class-form | curriculum-form
    mode: 'create',
    programs: examplePrograms,
    programId: 'ptik',
    curriculumStatus: 'draft',
    curriculumVersion: 'PTIK v2026.2',
    selectedVersion: 'example-published',
    classForm: freshClass(),
    chosenClass: null,
    draftPeriods: [],
    competencyOpen: false,
    detailOpen: false,
    get selectedProgram() { return this.programs.find(program => program.id === this.programId) || null; },
    get formProgram() { return this.programs.find(program => program.id === this.classForm.program) || null; },
    get programOptions() { return this.programs.filter(program => program.department === this.classForm.department); },
    get selectedPeriods() { return this.selectedProgram?.periods || []; },
    chooseTab(tab) { this.tab = tab; this.screen = 'index'; },
    chooseProgram(programId) {
        const matching = this.programs.find(program => program.id === programId);
        if (!matching) return;
        this.programId = matching.id;
        this.selectedVersion = 'example-published';
        this.curriculumVersion = `${matching.name} v2026.2`;
    },
    changeDepartment(department) {
        this.classForm.department = department;
        this.classForm.program = this.programOptions[0]?.id || '';
        this.classForm.version = '';
    },
    changeClassProgram(programId) {
        this.classForm.program = programId;
        this.classForm.version = programId === 'speakout' ? '' : 'example-published';
    },
    newClass() {
        this.mode = 'create';
        this.classForm = freshClass();
        this.chosenClass = null;
        this.screen = 'class-form';
    },
    openClassEdit(data) {
        // The legacy CourseClass table has no V2 metadata: leave missing fields empty.
        this.mode = 'edit';
        this.chosenClass = data;
        this.classForm = { name: data.name ?? '', department: '', program: '', kind: '', status: '', count: data.student_count ?? '', start: '', end: '', notes: '', version: '' };
        this.screen = 'class-form';
    },
    showClassDetail(data) { this.chosenClass = data; this.detailOpen = true; },
    editDetail() {
        const data = this.chosenClass;
        this.detailOpen = false;
        if (data) this.openClassEdit(data);
    },
    newCurriculum() {
        this.mode = 'create';
        this.curriculumStatus = 'draft';
        this.curriculumVersion = `${this.selectedProgram?.name || 'Program'} — versi baru`;
        this.draftPeriods = copy(this.selectedPeriods);
        this.screen = 'curriculum-form';
    },
    editExampleCurriculum() {
        this.mode = 'edit';
        this.curriculumStatus = this.selectedVersion === 'example-published' ? 'published' : 'draft';
        this.curriculumVersion = `${this.selectedProgram?.name || 'Program'} v2026.1`;
        this.draftPeriods = copy(this.selectedPeriods);
        this.screen = 'curriculum-form';
    },
    addPeriod() {
        this.draftPeriods.push({ name: `Periode ${this.draftPeriods.length + 1}`, duration: '', tracks: [] });
    },
    removePeriod(index) { this.draftPeriods.splice(index, 1); },
    addTrack(periodIndex) { this.draftPeriods[periodIndex]?.tracks.push({ name: '', weekly: 1, modules: [] }); },
    removeTrack(periodIndex, trackIndex) { this.draftPeriods[periodIndex]?.tracks.splice(trackIndex, 1); },
    addModule(periodIndex, trackIndex) { this.draftPeriods[periodIndex]?.tracks[trackIndex]?.modules.push({ name: '', target: '' }); },
    removeModule(periodIndex, trackIndex, moduleIndex) { this.draftPeriods[periodIndex]?.tracks[trackIndex]?.modules.splice(moduleIndex, 1); },
    closeForm() { this.screen = 'index'; },
});
