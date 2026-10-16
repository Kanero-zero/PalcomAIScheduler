<?php

namespace Database\Seeders;

use App\Models\ClassCurriculumSnapshot;
use App\Models\Competency;
use App\Models\CourseClass;
use App\Models\CurriculumModule;
use App\Models\CurriculumPeriod;
use App\Models\CurriculumSubject;
use App\Models\CurriculumVersion;
use App\Models\Facility;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\InstructorLeave;
use App\Models\InstructorSkill;
use App\Models\Program;
use App\Models\RecurringPattern;
use App\Models\Room;
use App\Models\RoomFacility;
use App\Models\Schedule;
use App\Models\ScheduleApprovalDecision;
use App\Models\ScheduleProposal;
use App\Models\ScheduleProposalOption;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Seeder Master & Dataset Demo V2 (Gate D3) — PalComTech AI Scheduler.
 *
 * Menerapkan jaminan idempoten mutlak:
 * - Menggunakan updateOrCreate berbasis business key unik (bukan synthetic auto-increment ID).
 * - Tanggal jangkar statis (November 2026 – Februari 2027) tanpa dynamic date drift.
 * - Proteksi lingkungan produksi (abort 403 jika dijalankan di production).
 */
class PalcomDemoSeeder extends Seeder
{
    /**
     * Jalankan proses seeding seluruh skenario demo.
     */
    public function run(): void
    {
        // 0. Strict Production Environment Guard
        if (app()->environment('production')) {
            throw new RuntimeException('PalcomDemoSeeder dilarang keras dijalankan pada lingkungan produksi!');
        }

        $this->command?->info('Menjalankan PalcomDemoSeeder (12 Skenario Demo Baku)...');

        // DEMO-01: Akun Pengguna & RBAC Eksplisit
        $users = $this->seedUsers();

        // DEMO-02: Master Fasilitas & Ruangan Terstruktur
        $rooms = $this->seedFacilitiesAndRooms();

        // DEMO-03: Master Program & Kurikulum Modular V2
        $curriculum = $this->seedCurriculum();

        // DEMO-04: Master Instruktur, Akun Login & Matriks Kompetensi
        $instructors = $this->seedInstructors($users);

        // DEMO-05: Kelas Kursus Aktif V2, Snapshot Kurikulum & Recurring Pattern
        $class = $this->seedActiveCourseClass($curriculum);

        // DEMO-06: Jadwal Pertemuan Resmi Multi-Materi (Completed & Scheduled)
        $schedules = $this->seedSchedules($class, $curriculum, $instructors, $rooms);

        // DEMO-07: Kasus Izin Instruktur & Penggantian Terverifikasi
        $this->seedInstructorLeaveAndSubstitution($instructors, $schedules);

        // DEMO-08: Proposal Penjadwalan AI Assistant (Status Pending)
        $this->seedAiProposal($users['ao'], $curriculum, $rooms);

        // DEMO-09: Proposal Penjadwalan Input Manual (Status Pending)
        $this->seedManualProposal($users['ao'], $curriculum, $instructors['sarah'], $rooms['teori_2']);

        // DEMO-10: Proposal Siklus Revisi (Putaran 1 & Putaran 2)
        $this->seedResubmittedProposal($users['ao'], $users['baak'], $curriculum, $rooms);

        // DEMO-11: Proposal Disetujui BAAK (Approved)
        $this->seedApprovedProposal($users['ao'], $users['baak'], $curriculum, $instructors['sarah'], $rooms['teori_1']);

        // DEMO-12: Proposal Ditolak BAAK (Rejected)
        $this->seedRejectedProposal($users['ao'], $users['baak'], $curriculum);

        $this->command?->info('PalcomDemoSeeder selesai dieksekusi secara idempoten.');
    }

    /**
     * DEMO-01: Akun Pengguna & RBAC Eksplisit
     *
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $accounts = [
            'admin' => ['email' => 'admin@palcomtech.ac.id', 'name' => 'Admin Sistem PalComTech', 'role' => 'admin'],
            'baak' => ['email' => 'baak@palcomtech.ac.id', 'name' => 'Budi Santoso (BAAK)', 'role' => 'baak'],
            'ao' => ['email' => 'ao@palcomtech.ac.id', 'name' => 'Siti Rahma (Academic Officer)', 'role' => 'ao'],
            'wahyu' => ['email' => 'wahyu@palcomtech.ac.id', 'name' => 'Wahyu Pratama', 'role' => 'instructor'],
            'kanero' => ['email' => 'kanero@palcomtech.ac.id', 'name' => 'Kanero Zero', 'role' => 'instructor'],
            'sarah' => ['email' => 'sarah@palcomtech.ac.id', 'name' => 'Sarah Jenkins', 'role' => 'instructor'],
            'rahmat' => ['email' => 'rahmat@palcomtech.ac.id', 'name' => 'Rahmat Hidayat', 'role' => 'instructor'],
            'staff' => ['email' => 'staff@palcomtech.ac.id', 'name' => 'Staf Operasional', 'role' => 'staff'],
        ];

        $users = [];
        foreach ($accounts as $key => $data) {
            $users[$key] = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'role' => $data['role'],
                    'email_verified_at' => Carbon::create(2026, 1, 1),
                ]
            );
        }

        return $users;
    }

    /**
     * DEMO-02: Master Fasilitas & Ruangan Terstruktur
     *
     * @return array<string, Room>
     */
    private function seedFacilitiesAndRooms(): array
    {
        $facilityData = [
            'pc_core_i7' => ['name' => 'PC Intel Core i7 16GB RAM', 'category' => 'hardware'],
            'pc_core_i5' => ['name' => 'PC Intel Core i5 8GB RAM', 'category' => 'hardware'],
            'projector_hd' => ['name' => 'Proyektor Full HD', 'category' => 'multimedia'],
            'sound_system' => ['name' => 'Sound System Audio', 'category' => 'multimedia'],
            'whiteboard_large' => ['name' => 'Papan Tulis Whiteboard Besar', 'category' => 'facility'],
            'air_conditioner' => ['name' => 'AC Split 2 PK', 'category' => 'facility'],
        ];

        $facilities = [];
        foreach ($facilityData as $code => $f) {
            $facilities[$code] = Facility::updateOrCreate(
                ['code' => $code],
                ['name' => $f['name'], 'category' => $f['category'], 'is_active' => true]
            );
        }

        $roomDefs = [
            'lab_1' => [
                'name' => 'Lab Komputer 1',
                'department' => 'computer',
                'capacity' => 25,
                'status' => 'available',
                'notes' => '[DEMO-02] Laboratorium utama pemrograman web dan database',
                'facilities' => [
                    ['code' => 'pc_core_i7', 'quantity' => 25, 'condition' => 'good'],
                    ['code' => 'projector_hd', 'quantity' => 1, 'condition' => 'good'],
                    ['code' => 'air_conditioner', 'quantity' => 2, 'condition' => 'good'],
                ],
            ],
            'lab_2' => [
                'name' => 'Lab Komputer 2',
                'department' => 'computer',
                'capacity' => 20,
                'status' => 'available',
                'notes' => '[DEMO-02] Laboratorium aplikasi perkantoran dan desain grafis',
                'facilities' => [
                    ['code' => 'pc_core_i5', 'quantity' => 20, 'condition' => 'good'],
                    ['code' => 'projector_hd', 'quantity' => 1, 'condition' => 'good'],
                    ['code' => 'air_conditioner', 'quantity' => 2, 'condition' => 'good'],
                ],
            ],
            'lab_multimedia' => [
                'name' => 'Lab Multimedia',
                'department' => 'computer',
                'capacity' => 15,
                'status' => 'available',
                'notes' => '[DEMO-02] Laboratorium editing audio, video, dan animasi',
                'facilities' => [
                    ['code' => 'pc_core_i7', 'quantity' => 15, 'condition' => 'good'],
                    ['code' => 'sound_system', 'quantity' => 1, 'condition' => 'good'],
                    ['code' => 'projector_hd', 'quantity' => 1, 'condition' => 'good'],
                ],
            ],
            'teori_1' => [
                'name' => 'Ruang Teori 1',
                'department' => 'speakout',
                'capacity' => 30,
                'status' => 'available',
                'notes' => '[DEMO-02] Ruang kelas teori dan pelatihan bahasa Inggris umum',
                'facilities' => [
                    ['code' => 'whiteboard_large', 'quantity' => 1, 'condition' => 'good'],
                    ['code' => 'projector_hd', 'quantity' => 1, 'condition' => 'good'],
                    ['code' => 'air_conditioner', 'quantity' => 2, 'condition' => 'good'],
                ],
            ],
            'teori_2' => [
                'name' => 'Ruang Teori 2',
                'department' => 'speakout',
                'capacity' => 20,
                'status' => 'available',
                'notes' => '[DEMO-02] Ruang diskusi intensif Speakout English',
                'facilities' => [
                    ['code' => 'whiteboard_large', 'quantity' => 1, 'condition' => 'good'],
                    ['code' => 'air_conditioner', 'quantity' => 1, 'condition' => 'good'],
                ],
            ],
            'lab_maintenance' => [
                'name' => 'Lab Maintenance',
                'department' => 'computer',
                'capacity' => 20,
                'status' => 'maintenance',
                'notes' => '[DEMO-05] Sedang dalam perbaikan berkala jaringan dan kelistrikan',
                'facilities' => [
                    ['code' => 'pc_core_i5', 'quantity' => 20, 'condition' => 'maintenance'],
                ],
            ],
        ];

        $rooms = [];
        foreach ($roomDefs as $key => $r) {
            $room = Room::updateOrCreate(
                ['name' => $r['name']],
                [
                    'department' => $r['department'],
                    'capacity' => $r['capacity'],
                    'status' => $r['status'],
                    'notes' => $r['notes'],
                ]
            );

            foreach ($r['facilities'] as $rf) {
                RoomFacility::updateOrCreate(
                    [
                        'room_id' => $room->id,
                        'facility_id' => $facilities[$rf['code']]->id,
                    ],
                    [
                        'quantity' => $rf['quantity'],
                        'condition' => $rf['condition'],
                    ]
                );
            }

            $rooms[$key] = $room;
        }

        return $rooms;
    }

    /**
     * DEMO-03: Master Program & Kurikulum Modular V2
     *
     * @return array{program: Program, version: CurriculumVersion, subject_html: CurriculumSubject, subject_js: CurriculumSubject}
     */
    private function seedCurriculum(): array
    {
        // Kompetensi Terkatalog
        $compHtml = Competency::updateOrCreate(['name' => 'HTML & CSS'], ['department' => 'computer', 'is_active' => true]);
        $compJs = Competency::updateOrCreate(['name' => 'JavaScript'], ['department' => 'computer', 'is_active' => true]);
        $compPhp = Competency::updateOrCreate(['name' => 'PHP & Laravel'], ['department' => 'computer', 'is_active' => true]);
        $compExcel = Competency::updateOrCreate(['name' => 'Microsoft Excel'], ['department' => 'computer', 'is_active' => true]);
        $compEnglish = Competency::updateOrCreate(['name' => 'English Basic'], ['department' => 'speakout', 'is_active' => true]);
        $compPublic = Competency::updateOrCreate(['name' => 'Public Speaking'], ['department' => 'speakout', 'is_active' => true]);

        // Program Studi
        $progWeb = Program::updateOrCreate(
            ['code' => 'PROG-WEB-01'],
            ['name' => 'Web Programming', 'department' => 'computer', 'is_active' => true]
        );

        $progSpeakout = Program::updateOrCreate(
            ['code' => 'PROG-ENG-01'],
            ['name' => 'Speakout English', 'department' => 'speakout', 'is_active' => true]
        );

        // Versi Kurikulum
        $versionWeb = CurriculumVersion::updateOrCreate(
            ['program_id' => $progWeb->id, 'version_code' => '2026.1'],
            ['status' => 'published', 'published_at' => Carbon::create(2026, 8, 1)]
        );

        $versionSpeakout = CurriculumVersion::updateOrCreate(
            ['program_id' => $progSpeakout->id, 'version_code' => '2026.1'],
            ['status' => 'published', 'published_at' => Carbon::create(2026, 8, 1)]
        );

        // Periode Kurikulum
        $periodWeb = CurriculumPeriod::updateOrCreate(
            ['curriculum_version_id' => $versionWeb->id, 'name' => 'Tahap Dasar Frontend'],
            ['sort_order' => 1, 'duration_weeks' => 12]
        );

        // Mata Pelajaran V2
        $subjectHtml = CurriculumSubject::updateOrCreate(
            ['curriculum_period_id' => $periodWeb->id, 'name' => 'HTML5 & CSS3 Dasar'],
            ['competency_id' => $compHtml->id, 'sessions_per_week' => 2, 'sort_order' => 1]
        );

        $subjectJs = CurriculumSubject::updateOrCreate(
            ['curriculum_period_id' => $periodWeb->id, 'name' => 'JavaScript Modern'],
            ['competency_id' => $compJs->id, 'sessions_per_week' => 2, 'sort_order' => 2]
        );

        // Modul Ajar (Target Pertemuan Faktual Completed)
        CurriculumModule::updateOrCreate(
            ['curriculum_subject_id' => $subjectHtml->id, 'title' => 'Struktur Semantik HTML5'],
            ['target_completed_meetings' => 2, 'sort_order' => 1]
        );

        CurriculumModule::updateOrCreate(
            ['curriculum_subject_id' => $subjectHtml->id, 'title' => 'Styling CSS3, Flexbox & Responsive Grid'],
            ['target_completed_meetings' => 4, 'sort_order' => 2]
        );

        CurriculumModule::updateOrCreate(
            ['curriculum_subject_id' => $subjectJs->id, 'title' => 'Dasar Sintaks & Variabel JS'],
            ['target_completed_meetings' => 2, 'sort_order' => 1]
        );

        CurriculumModule::updateOrCreate(
            ['curriculum_subject_id' => $subjectJs->id, 'title' => 'DOM Manipulation & Event Handling'],
            ['target_completed_meetings' => 4, 'sort_order' => 2]
        );

        // Speakout Kurikulum
        $periodSpeakout = CurriculumPeriod::updateOrCreate(
            ['curriculum_version_id' => $versionSpeakout->id, 'name' => 'Level 1: Everyday Communication'],
            ['sort_order' => 1, 'duration_weeks' => 8]
        );

        $subjectEnglish = CurriculumSubject::updateOrCreate(
            ['curriculum_period_id' => $periodSpeakout->id, 'name' => 'General English & Small Talk'],
            ['competency_id' => $compEnglish->id, 'sessions_per_week' => 2, 'sort_order' => 1]
        );

        CurriculumModule::updateOrCreate(
            ['curriculum_subject_id' => $subjectEnglish->id, 'title' => 'Self Introduction & Greetings'],
            ['target_completed_meetings' => 4, 'sort_order' => 1]
        );

        return [
            'program' => $progWeb,
            'program_speakout' => $progSpeakout,
            'version' => $versionWeb,
            'version_speakout' => $versionSpeakout,
            'subject_html' => $subjectHtml,
            'subject_js' => $subjectJs,
            'subject_english' => $subjectEnglish,
            'comp_html' => $compHtml,
            'comp_js' => $compJs,
            'comp_php' => $compPhp,
            'comp_excel' => $compExcel,
            'comp_english' => $compEnglish,
            'comp_public' => $compPublic,
        ];
    }

    /**
     * DEMO-04: Master Instruktur, Akun Login & Matriks Kompetensi
     *
     * @param  array<string, User>  $users
     * @return array<string, Instructor>
     */
    private function seedInstructors(array $users): array
    {
        $compHtml = Competency::where('name', 'HTML & CSS')->firstOrFail();
        $compJs = Competency::where('name', 'JavaScript')->firstOrFail();
        $compPhp = Competency::where('name', 'PHP & Laravel')->firstOrFail();
        $compExcel = Competency::where('name', 'Microsoft Excel')->firstOrFail();
        $compEnglish = Competency::where('name', 'English Basic')->firstOrFail();
        $compPublic = Competency::where('name', 'Public Speaking')->firstOrFail();

        $instructorsData = [
            'wahyu' => [
                'name' => 'Wahyu Pratama',
                'department' => 'computer',
                'status' => 'active',
                'user' => $users['wahyu'],
                'notes' => '[DEMO-04] Senior Frontend & Backend Web Instructor',
                'skills' => [
                    ['skill' => 'HTML & CSS', 'level' => 'advanced', 'comp_id' => $compHtml->id],
                    ['skill' => 'JavaScript', 'level' => 'intermediate', 'comp_id' => $compJs->id],
                    ['skill' => 'PHP & Laravel', 'level' => 'advanced', 'comp_id' => $compPhp->id],
                ],
            ],
            'kanero' => [
                'name' => 'Kanero Zero',
                'department' => 'computer',
                'status' => 'active',
                'user' => $users['kanero'],
                'notes' => '[DEMO-04] Web Design & Office Specialist Instructor',
                'skills' => [
                    ['skill' => 'HTML & CSS', 'level' => 'intermediate', 'comp_id' => $compHtml->id],
                    ['skill' => 'JavaScript', 'level' => 'beginner', 'comp_id' => $compJs->id],
                    ['skill' => 'Microsoft Excel', 'level' => 'advanced', 'comp_id' => $compExcel->id],
                ],
            ],
            'sarah' => [
                'name' => 'Sarah Jenkins',
                'department' => 'speakout',
                'status' => 'active',
                'user' => $users['sarah'],
                'notes' => '[DEMO-04] Native English & Public Speaking Coach',
                'skills' => [
                    ['skill' => 'English Basic', 'level' => 'advanced', 'comp_id' => $compEnglish->id],
                    ['skill' => 'Public Speaking', 'level' => 'advanced', 'comp_id' => $compPublic->id],
                ],
            ],
            'rahmat' => [
                'name' => 'Rahmat Hidayat',
                'department' => 'speakout',
                'status' => 'active',
                'user' => $users['rahmat'],
                'notes' => '[DEMO-04] Conversational English Instructor',
                'skills' => [
                    ['skill' => 'English Basic', 'level' => 'intermediate', 'comp_id' => $compEnglish->id],
                ],
            ],
        ];

        $instructors = [];
        foreach ($instructorsData as $key => $data) {
            $inst = Instructor::updateOrCreate(
                ['name' => $data['name']],
                [
                    'user_id' => $data['user']->id,
                    'department' => $data['department'],
                    'status' => $data['status'],
                    'notes' => $data['notes'],
                ]
            );

            // Skills
            foreach ($data['skills'] as $s) {
                InstructorSkill::updateOrCreate(
                    [
                        'instructor_id' => $inst->id,
                        'skill' => $s['skill'],
                    ],
                    [
                        'competency_id' => $s['comp_id'],
                        'level' => $s['level'],
                    ]
                );
            }

            // Availabilities: Senin–Jumat jam 08:00–12:00 dan 13:00–17:00
            $workDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
            foreach ($workDays as $dayName) {
                InstructorAvailability::updateOrCreate(
                    [
                        'instructor_id' => $inst->id,
                        'day' => $dayName,
                        'start_time' => '08:00',
                    ],
                    [
                        'end_time' => '12:00',
                    ]
                );

                InstructorAvailability::updateOrCreate(
                    [
                        'instructor_id' => $inst->id,
                        'day' => $dayName,
                        'start_time' => '13:00',
                    ],
                    [
                        'end_time' => '17:00',
                    ]
                );
            }

            $instructors[$key] = $inst;
        }

        return $instructors;
    }

    /**
     * DEMO-05: Kelas Kursus Aktif V2, Snapshot Kurikulum & Recurring Pattern
     *
     * @param  array<string, mixed>  $curriculum
     */
    private function seedActiveCourseClass(array $curriculum): CourseClass
    {
        $class = CourseClass::updateOrCreate(
            ['name' => '[DEMO-05] Web Programming Pagi 2026-A'],
            [
                'subject' => 'Web Programming',
                'student_count' => 18,
                'program_id' => $curriculum['program']->id,
                'department' => 'computer',
                'kind' => 'regular',
                'start_date' => '2026-11-02',
                'end_date' => '2027-01-28',
                'total_meetings' => 24,
                'session_duration_minutes' => 120,
                'status' => 'active',
            ]
        );

        // Snapshot Kurikulum Beku (Immutable)
        ClassCurriculumSnapshot::updateOrCreate(
            ['course_class_id' => $class->id],
            [
                'source_curriculum_version_id' => $curriculum['version']->id,
                'snapshot_payload' => [
                    'program_code' => 'PROG-WEB-01',
                    'version_code' => '2026.1',
                    'subjects' => [
                        ['name' => 'HTML5 & CSS3 Dasar', 'target_meetings' => 6],
                        ['name' => 'JavaScript Modern', 'target_meetings' => 6],
                    ],
                ],
                'captured_at' => Carbon::create(2026, 11, 1),
            ]
        );

        // Recurring Pattern
        RecurringPattern::updateOrCreate(
            ['course_class_id' => $class->id],
            [
                'frequency_per_week' => 2,
                'preferred_weekdays' => ['monday', 'wednesday'],
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'duration_minutes' => 120,
            ]
        );

        return $class;
    }

    /**
     * DEMO-06: Jadwal Pertemuan Resmi Multi-Materi (Completed & Scheduled)
     *
     * @param  array<string, mixed>  $curriculum
     * @param  array<string, Instructor>  $instructors
     * @param  array<string, Room>  $rooms
     * @return array<int, Schedule>
     */
    private function seedSchedules(CourseClass $class, array $curriculum, array $instructors, array $rooms): array
    {
        $scheduleSlots = [
            1 => ['date' => '2026-11-02', 'subject' => $curriculum['subject_html'], 'status' => 'completed'],
            2 => ['date' => '2026-11-04', 'subject' => $curriculum['subject_html'], 'status' => 'completed'],
            3 => ['date' => '2026-11-09', 'subject' => $curriculum['subject_html'], 'status' => 'scheduled'],
            4 => ['date' => '2026-11-11', 'subject' => $curriculum['subject_html'], 'status' => 'scheduled'],
            5 => ['date' => '2026-11-16', 'subject' => $curriculum['subject_js'], 'status' => 'scheduled'],
            6 => ['date' => '2026-11-18', 'subject' => $curriculum['subject_js'], 'status' => 'scheduled'],
        ];

        $schedules = [];
        foreach ($scheduleSlots as $seq => $slot) {
            $schedules[$seq] = Schedule::updateOrCreate(
                [
                    'course_class_id' => $class->id,
                    'meeting_sequence' => $seq,
                ],
                [
                    'curriculum_subject_id' => $slot['subject']->id,
                    'instructor_id' => $instructors['wahyu']->id,
                    'room_id' => $rooms['lab_1']->id,
                    'date' => $slot['date'],
                    'start_time' => '09:00:00',
                    'end_time' => '11:00:00',
                    'status' => $slot['status'],
                ]
            );
        }

        return $schedules;
    }

    /**
     * DEMO-07: Kasus Izin Instruktur & Penggantian Terverifikasi
     *
     * @param  array<string, Instructor>  $instructors
     * @param  array<int, Schedule>  $schedules
     */
    private function seedInstructorLeaveAndSubstitution(array $instructors, array $schedules): void
    {
        $targetSchedule = $schedules[5]; // Pertemuan 5: 2026-11-16 (Materi: JavaScript Modern)

        // Cuti Instruktur Wahyu (Pencarian menggunakan whereDate untuk kompatibilitas lintas SQLite & MySQL)
        $leave = InstructorLeave::where('instructor_id', $instructors['wahyu']->id)
            ->whereDate('date', '2026-11-16')
            ->where('start_time', 'like', '08:00%')
            ->first();

        if ($leave) {
            $leave->update([
                'end_time' => '12:00:00',
                'reason' => '[DEMO-07] Keperluan keluarga mendadak ke luar kota',
                'status' => 'pending',
            ]);
        } else {
            $leave = InstructorLeave::create([
                'instructor_id' => $instructors['wahyu']->id,
                'date' => '2026-11-16',
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'reason' => '[DEMO-07] Keperluan keluarga mendadak ke luar kota',
                'status' => 'pending',
            ]);
        }

        // Rekomendasi Pengganti ke Kanero (memiliki kompetensi JavaScript)
        ScheduleSubstitution::updateOrCreate(
            [
                'instructor_leave_id' => $leave->id,
                'schedule_id' => $targetSchedule->id,
            ],
            [
                'original_instructor_id' => $instructors['wahyu']->id,
                'replacement_instructor_id' => $instructors['kanero']->id,
                'status' => 'pending',
                'notes' => '[DEMO-07] Rekomendasi sistem (skor kecocokan 85%): Instruktur Kanero memiliki kompetensi JavaScript Dasar dan slot kosong pada jam tersebut.',
            ]
        );
    }

    /**
     * DEMO-08: Proposal Penjadwalan AI Assistant (Status Pending)
     *
     * @param  array<string, mixed>  $curriculum
     * @param  array<string, Room>  $rooms
     */
    private function seedAiProposal(User $ao, array $curriculum, array $rooms): void
    {
        $proposal = ScheduleProposal::updateOrCreate(
            ['title' => '[DEMO-08] Pengajuan Kelas Desain Web Pagi B (AI Assistant)'],
            [
                'requested_by' => $ao->id,
                'input_mode' => 'ai_assistant',
                'program_id' => $curriculum['program']->id,
                'curriculum_version_id' => $curriculum['version']->id,
                'department' => 'computer',
                'class_kind' => 'regular',
                'student_count' => 16,
                'start_date' => '2026-11-23',
                'end_date' => '2027-02-15',
                'total_meetings' => 24,
                'session_duration_minutes' => 120,
                'frequency_per_week' => 2,
                'preferred_weekdays' => ['monday', 'wednesday'],
                'preferred_time_window' => ['start' => '09:00', 'end' => '12:00'],
                'required_competency_ids' => [$curriculum['comp_html']->id, $curriculum['comp_js']->id],
                'required_facility_codes' => ['pc_core_i7', 'projector_hd'],
                'ai_prompt' => 'Optimalkan jadwal pagi hari, utamakan Lab Komputer 1 dan instruktur senior untuk modul JavaScript.',
                'status' => 'pending',
                'submitted_at' => Carbon::create(2026, 11, 10, 10, 0, 0),
            ]
        );

        $optionA = ScheduleProposalOption::updateOrCreate(
            ['proposal_id' => $proposal->id, 'option_label' => 'A'],
            [
                'proposed_schedules_payload' => [
                    ['date' => '2026-11-23', 'start_time' => '09:00:00', 'end_time' => '11:00:00', 'room_id' => $rooms['lab_1']->id],
                    ['date' => '2026-11-25', 'start_time' => '09:00:00', 'end_time' => '11:00:00', 'room_id' => $rooms['lab_1']->id],
                ],
                'conflicts_count' => 0,
                'score' => 95,
                'rationale' => 'Rekomendasi terbaik: Lab Komputer 1 berdaya tampung 25 siswa dengan PC Core i7 dan Proyektor lengkap.',
            ]
        );

        ScheduleProposalOption::updateOrCreate(
            ['proposal_id' => $proposal->id, 'option_label' => 'B'],
            [
                'proposed_schedules_payload' => [
                    ['date' => '2026-11-24', 'start_time' => '13:00:00', 'end_time' => '15:00:00', 'room_id' => $rooms['lab_2']->id],
                ],
                'conflicts_count' => 0,
                'score' => 85,
                'rationale' => 'Opsi alternatif siang hari di Lab Komputer 2.',
            ]
        );

        $proposal->selectOption($optionA);
    }

    /**
     * DEMO-09: Proposal Penjadwalan Input Manual (Status Pending)
     *
     * @param  array<string, mixed>  $curriculum
     */
    private function seedManualProposal(User $ao, array $curriculum, Instructor $sarah, Room $roomTeori): void
    {
        $proposal = ScheduleProposal::updateOrCreate(
            ['title' => '[DEMO-09] Pengajuan Kelas Speakout Sore Khusus (Input Manual)'],
            [
                'requested_by' => $ao->id,
                'input_mode' => 'manual_input',
                'program_id' => $curriculum['program_speakout']->id,
                'curriculum_version_id' => $curriculum['version_speakout']->id,
                'department' => 'speakout',
                'class_kind' => 'private',
                'student_count' => 8,
                'start_date' => '2026-11-24',
                'end_date' => '2027-01-15',
                'total_meetings' => 16,
                'session_duration_minutes' => 90,
                'frequency_per_week' => 2,
                'preferred_weekdays' => ['tuesday', 'thursday'],
                'preferred_instructor_id' => $sarah->id,
                'preferred_room_id' => $roomTeori->id,
                'status' => 'pending',
                'submitted_at' => Carbon::create(2026, 11, 12, 14, 0, 0),
            ]
        );

        $optionA = ScheduleProposalOption::updateOrCreate(
            ['proposal_id' => $proposal->id, 'option_label' => 'A'],
            [
                'proposed_schedules_payload' => [
                    ['date' => '2026-11-24', 'start_time' => '15:30:00', 'end_time' => '17:00:00', 'instructor_id' => $sarah->id, 'room_id' => $roomTeori->id],
                    ['date' => '2026-11-26', 'start_time' => '15:30:00', 'end_time' => '17:00:00', 'instructor_id' => $sarah->id, 'room_id' => $roomTeori->id],
                ],
                'conflicts_count' => 0,
                'score' => 100,
                'rationale' => 'Jadwal manual sesuai permintaan instruktur Sarah Jenkins di Ruang Teori 2.',
            ]
        );

        $proposal->selectOption($optionA);
    }

    /**
     * DEMO-10: Proposal Siklus Revisi (Putaran 1 & Putaran 2)
     *
     * @param  array<string, mixed>  $curriculum
     * @param  array<string, Room>  $rooms
     */
    private function seedResubmittedProposal(User $ao, User $baak, array $curriculum, array $rooms): void
    {
        $proposal = ScheduleProposal::updateOrCreate(
            ['title' => '[DEMO-10] Pengajuan Kelas Desain Web Sore Angkatan 2026 (Siklus Revisi)'],
            [
                'requested_by' => $ao->id,
                'input_mode' => 'ai_assistant',
                'program_id' => $curriculum['program']->id,
                'curriculum_version_id' => $curriculum['version']->id,
                'department' => 'computer',
                'class_kind' => 'regular',
                'student_count' => 18,
                'start_date' => '2026-11-30',
                'end_date' => '2027-02-20',
                'total_meetings' => 24,
                'session_duration_minutes' => 120,
                'frequency_per_week' => 2,
                'status' => 'pending',
                'submitted_at' => Carbon::create(2026, 11, 14, 11, 30, 0),
            ]
        );

        $optionA = ScheduleProposalOption::updateOrCreate(
            ['proposal_id' => $proposal->id, 'option_label' => 'A'],
            [
                'proposed_schedules_payload' => [
                    ['date' => '2026-11-30', 'start_time' => '13:00:00', 'end_time' => '15:00:00', 'room_id' => $rooms['lab_1']->id],
                ],
                'conflicts_count' => 0,
                'score' => 90,
            ]
        );

        $proposal->selectOption($optionA);

        // Putaran 1: BAAK meminta revisi
        ScheduleApprovalDecision::updateOrCreate(
            ['proposal_id' => $proposal->id, 'round' => 1],
            [
                'decision_by' => $baak->id,
                'decision' => 'needs_revision',
                'reason' => '[DEMO-10] Mohon sesuaikan ruangan karena Lab Komputer 2 sedang dalam penjadwalan pemeliharaan berkala.',
                'idempotency_token' => 'idemp-decision-demo-10-round-1',
                'decided_at' => Carbon::create(2026, 11, 13, 16, 0, 0),
            ]
        );
    }

    /**
     * DEMO-11: Skenario Dua Pengajuan Bersaing pada Slot Sama (Approved vs Pending)
     *
     * @param  array<string, mixed>  $curriculum
     */
    private function seedApprovedProposal(User $ao, User $baak, array $curriculum, Instructor $sarah, Room $roomTeori): void
    {
        // 1. Proposal A: Pemenang Slot Pertama (Status: Approved)
        $proposalA = ScheduleProposal::updateOrCreate(
            ['title' => '[DEMO-11] Pengajuan Kelas Speakout Pagi Reguler (Disetujui)'],
            [
                'requested_by' => $ao->id,
                'input_mode' => 'ai_assistant',
                'program_id' => $curriculum['program_speakout']->id,
                'curriculum_version_id' => $curriculum['version_speakout']->id,
                'department' => 'speakout',
                'class_kind' => 'regular',
                'student_count' => 12,
                'start_date' => '2026-12-01',
                'end_date' => '2027-02-10',
                'total_meetings' => 20,
                'session_duration_minutes' => 90,
                'frequency_per_week' => 2,
                'status' => 'approved',
                'submitted_at' => Carbon::create(2026, 11, 15, 9, 0, 0),
            ]
        );

        $optionA = ScheduleProposalOption::updateOrCreate(
            ['proposal_id' => $proposalA->id, 'option_label' => 'A'],
            [
                'proposed_schedules_payload' => [
                    ['date' => '2026-12-01', 'start_time' => '09:00:00', 'end_time' => '10:30:00', 'instructor_id' => $sarah->id, 'room_id' => $roomTeori->id],
                ],
                'conflicts_count' => 0,
                'score' => 98,
                'rationale' => 'Alokasi optimal di Ruang Teori 1 bersama instruktur Sarah Jenkins.',
            ]
        );

        $proposalA->selectOption($optionA);

        ScheduleApprovalDecision::updateOrCreate(
            ['proposal_id' => $proposalA->id, 'round' => 1],
            [
                'decision_by' => $baak->id,
                'decision' => 'approved',
                'reason' => '[DEMO-11] Rencana jadwal telah diverifikasi aman dari konflik ruangan dan instruktur.',
                'idempotency_token' => 'idemp-decision-demo-11-approved',
                'decided_at' => Carbon::create(2026, 11, 15, 14, 0, 0),
            ]
        );

        // Kelas Resmi & Jadwal Resmi untuk Proposal A yang telah disetujui
        $speakoutClass = CourseClass::updateOrCreate(
            ['name' => '[DEMO-11] Speakout Pagi Reguler 2026-A'],
            [
                'subject' => 'General English & Small Talk',
                'student_count' => 12,
                'program_id' => $curriculum['program_speakout']->id,
                'department' => 'speakout',
                'kind' => 'regular',
                'start_date' => '2026-12-01',
                'end_date' => '2027-02-10',
                'total_meetings' => 20,
                'session_duration_minutes' => 90,
                'status' => 'active',
            ]
        );

        $proposalA->update(['course_class_id' => $speakoutClass->id]);

        Schedule::updateOrCreate(
            [
                'course_class_id' => $speakoutClass->id,
                'meeting_sequence' => 1,
            ],
            [
                'curriculum_subject_id' => $curriculum['subject_english']->id,
                'instructor_id' => $sarah->id,
                'room_id' => $roomTeori->id,
                'date' => '2026-12-01',
                'start_time' => '09:00:00',
                'end_time' => '10:30:00',
                'status' => 'scheduled',
            ]
        );

        // 2. Proposal B: Pesaing Slot Sama (Status: Pending) — Mendemonstrasikan DEMO-11 DB-DATA-1.0
        // Menginginkan Ruang Teori 1 pada 2026-12-01 09:00 - 10:30 (Slot sama persis dengan Proposal A)
        // Proposal pending tidak memesan resource; jika diajukan approval akan terdeteksi bentrok dengan Proposal A
        $proposalB = ScheduleProposal::updateOrCreate(
            ['title' => '[DEMO-11] Pengajuan Kelas Speakout Percakapan Pagi (Pesaing Slot Sama)'],
            [
                'requested_by' => $ao->id,
                'input_mode' => 'manual_input',
                'program_id' => $curriculum['program_speakout']->id,
                'curriculum_version_id' => $curriculum['version_speakout']->id,
                'department' => 'speakout',
                'class_kind' => 'regular',
                'student_count' => 10,
                'start_date' => '2026-12-01',
                'end_date' => '2027-02-10',
                'total_meetings' => 20,
                'session_duration_minutes' => 90,
                'frequency_per_week' => 2,
                'status' => 'pending',
                'submitted_at' => Carbon::create(2026, 11, 15, 10, 0, 0),
            ]
        );

        $optionB = ScheduleProposalOption::updateOrCreate(
            ['proposal_id' => $proposalB->id, 'option_label' => 'A'],
            [
                'proposed_schedules_payload' => [
                    ['date' => '2026-12-01', 'start_time' => '09:00:00', 'end_time' => '10:30:00', 'instructor_id' => $sarah->id, 'room_id' => $roomTeori->id],
                ],
                'conflicts_count' => 0,
                'score' => 90,
                'rationale' => '[DEMO-11] Pilihan manual mengajukan slot yang sama dengan proposal reguler untuk verifikasi persaingan approval.',
            ]
        );

        $proposalB->selectOption($optionB);
    }

    /**
     * DEMO-12: Proposal Ditolak BAAK (Rejected)
     *
     * @param  array<string, mixed>  $curriculum
     */
    private function seedRejectedProposal(User $ao, User $baak, array $curriculum): void
    {
        $proposal = ScheduleProposal::updateOrCreate(
            ['title' => '[DEMO-12] Pengajuan Kelas Ekstra Weekend Minggu (Ditolak)'],
            [
                'requested_by' => $ao->id,
                'input_mode' => 'manual_input',
                'program_id' => $curriculum['program']->id,
                'curriculum_version_id' => $curriculum['version']->id,
                'department' => 'computer',
                'class_kind' => 'private',
                'student_count' => 5,
                'start_date' => '2026-12-06',
                'end_date' => '2027-01-10',
                'total_meetings' => 6,
                'session_duration_minutes' => 120,
                'frequency_per_week' => 1,
                'status' => 'rejected',
                'submitted_at' => Carbon::create(2026, 11, 16, 8, 30, 0),
            ]
        );

        $optionA = ScheduleProposalOption::updateOrCreate(
            ['proposal_id' => $proposal->id, 'option_label' => 'A'],
            [
                'proposed_schedules_payload' => [
                    ['date' => '2026-12-06', 'start_time' => '10:00:00', 'end_time' => '12:00:00'],
                ],
                'conflicts_count' => 1,
                'score' => 40,
                'rationale' => 'Permintaan hari Minggu di luar jam operasional kampus.',
            ]
        );

        $proposal->selectOption($optionA);

        ScheduleApprovalDecision::updateOrCreate(
            ['proposal_id' => $proposal->id, 'round' => 1],
            [
                'decision_by' => $baak->id,
                'decision' => 'rejected',
                'reason' => '[DEMO-12] Permintaan ditolak: Kampus PalComTech tidak menyelenggarakan sesi tatap muka pada hari Minggu.',
                'idempotency_token' => 'idemp-decision-demo-12-rejected',
                'decided_at' => Carbon::create(2026, 11, 16, 10, 0, 0),
            ]
        );
    }
}
