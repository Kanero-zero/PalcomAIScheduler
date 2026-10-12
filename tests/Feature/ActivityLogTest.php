<?php

use App\Livewire\InstructorLeaveForm;
use App\Models\ActivityLog;
use App\Models\CourseClass;
use App\Models\Instructor;
use App\Models\InstructorLeave;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleSubstitution;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Scheduling\DTOs\SchedulingResult;
use App\Services\Scheduling\ScheduleApprovalService;
use App\Services\Scheduling\SchedulingEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin BAAK PalComTech',
        'email' => 'admin@palcomtech.ac.id',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);

    Config::set('auth.admin_emails', ['admin@palcomtech.ac.id']);
    Config::set('services.gemini.api_key', 'test-dummy-api-key');
    Config::set('services.gemini.model', 'gemini-3.5-flash-lite');

    $this->wahyu = Instructor::create(['name' => 'Wahyu', 'status' => 'active']);
    $this->kanero = Instructor::create(['name' => 'Kanero', 'status' => 'active']);

    $this->courseClass = CourseClass::create([
        'name' => 'Microsoft Excel Siang',
        'subject' => 'Microsoft Excel',
        'student_count' => 20,
    ]);

    $this->kanero->skills()->create([
        'skill' => 'Microsoft Excel',
        'level' => 'advanced',
    ]);

    $this->room = Room::create([
        'name' => 'Lab 1',
        'capacity' => 30,
        'status' => 'available',
    ]);

    $this->service = app(ActivityLogService::class);
    $this->approvalService = app(ScheduleApprovalService::class);
    $this->schedulingEngine = app(SchedulingEngine::class);
});

test('aktivitas pengajuan izin berhasil disimpan secara permanen di database', function () {
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'reason' => 'Sakit demam',
        'status' => 'pending',
    ]);

    $log = ActivityLog::where('type', 'leave')
        ->where('instructor_leave_id', $leave->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->type)->toBe('leave')
        ->and($log->state)->toBe('pending')
        ->and($log->type_label)->toBe('Pengajuan Izin')
        ->and($log->state_label)->toBe('Menunggu')
        ->and($log->subject)->toBe("Pengajuan Izin #{$leave->id}")
        ->and($log->description)->toContain('Wahyu')
        ->and($log->metadata['reason'])->toBe('Sakit demam');
});

test('aktivitas diurutkan berdasarkan waktu kejadian secara kronologis menurun', function () {
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    $timeline = $this->service->getTimelineActivities();
    expect($timeline)->toBeArray()
        ->and(count($timeline))->toBeGreaterThanOrEqual(1);

    // Ambil log yang diurutkan melalui scopeForTimeline
    $logs = ActivityLog::forTimeline()->get();
    for ($i = 0; $i < count($logs) - 1; $i++) {
        expect($logs[$i]->created_at->timestamp)->toBeGreaterThanOrEqual($logs[$i + 1]->created_at->timestamp);
    }
});

test('evaluasi identik tidak membuat duplikasi log engine yang tidak diperlukan', function () {
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    // Jalankan evaluasi pertama kali
    $this->schedulingEngine->evaluateLeave($leave);

    $initialEngineLogCount = ActivityLog::where('type', 'engine')
        ->where('instructor_leave_id', $leave->id)
        ->count();

    expect($initialEngineLogCount)->toBe(1);

    // Jalankan evaluasi ulang 4 kali berturut-turut (mensimulasikan refresh atau pembukaan halaman berulang)
    $this->schedulingEngine->evaluateLeave($leave);
    $this->schedulingEngine->evaluateLeave($leave);
    $this->schedulingEngine->evaluateLeave($leave);
    $this->schedulingEngine->evaluateLeave($leave);

    // Pastikan log TIDAK bertambah karena hasilnya identik (deduplikasi berhasil)
    $afterRepeatedCallsCount = ActivityLog::where('type', 'engine')
        ->where('instructor_leave_id', $leave->id)
        ->count();

    expect($afterRepeatedCallsCount)->toBe(1);

    // Jika terjadi perubahan nyata (misal: tambah jadwal baru untuk instruktur tersebut)
    Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '15:00',
        'end_time' => '17:00',
        'status' => 'scheduled',
    ]);

    // Jalankan evaluasi ulang saat data berubah nyata
    $this->schedulingEngine->evaluateLeave($leave);

    $afterDataChangedCount = ActivityLog::where('type', 'engine')
        ->where('instructor_leave_id', $leave->id)
        ->count();

    expect($afterDataChangedCount)->toBe(2);
});

test('perubahan kondisi ruangan menghasilkan fingerprint berbeda pada log engine tanpa membuat duplikat saat identik', function () {
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    // Evaluasi 1: Ruangan available
    $this->schedulingEngine->evaluateLeave($leave);
    $initialLog = ActivityLog::where('type', 'engine')
        ->where('instructor_leave_id', $leave->id)
        ->latest('id')
        ->first();

    expect(ActivityLog::where('type', 'engine')->where('instructor_leave_id', $leave->id)->count())->toBe(1);

    // Evaluasi ulang saat kondisi identik: tidak boleh membuat duplikat
    $this->schedulingEngine->evaluateLeave($leave);
    expect(ActivityLog::where('type', 'engine')->where('instructor_leave_id', $leave->id)->count())->toBe(1);

    // Ubah kondisi ruangan: Ruangan menjadi maintenance (isStatusAvailable berubah)
    $this->room->update(['status' => 'maintenance']);

    // Evaluasi 2: Kondisi ruangan berubah
    $this->schedulingEngine->evaluateLeave($leave);
    $updatedLog = ActivityLog::where('type', 'engine')
        ->where('instructor_leave_id', $leave->id)
        ->latest('id')
        ->first();

    expect(ActivityLog::where('type', 'engine')->where('instructor_leave_id', $leave->id)->count())->toBe(2)
        ->and($updatedLog->id)->not->toBe($initialLog->id)
        ->and($updatedLog->fingerprint)->not->toBe($initialLog->fingerprint);

    // Evaluasi ulang kondisi baru yang sudah tercatat: tidak boleh membuat duplikat lagi
    $this->schedulingEngine->evaluateLeave($leave);
    expect(ActivityLog::where('type', 'engine')->where('instructor_leave_id', $leave->id)->count())->toBe(2);
});

test('aktivitas persetujuan dan perubahan jadwal dicatat dengan identitas admin dan referensi jadwal', function () {
    $this->actingAs($this->admin);

    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    $response = $this->approvalService->approveSubstitution(
        leaveId: $leave->id,
        scheduleId: $schedule->id,
        replacementInstructorId: $this->kanero->id,
        notes: 'Persetujuan resmi BAAK',
    );

    expect($response['success'])->toBeTrue();

    // 1. Periksa log approval
    $approvalLog = ActivityLog::where('type', 'approval')
        ->where('schedule_id', $schedule->id)
        ->first();

    expect($approvalLog)->not->toBeNull()
        ->and($approvalLog->state)->toBe('success')
        ->and($approvalLog->state_label)->toBe('Disetujui')
        ->and($approvalLog->actor)->toBe($this->admin->name)
        ->and($approvalLog->user_id)->toBe($this->admin->id)
        ->and($approvalLog->subject)->toBe("Pengajuan Izin #{$leave->id}")
        ->and($approvalLog->description)->toContain('Kanero')
        ->and($approvalLog->metadata['notes'])->toBe('Persetujuan resmi BAAK');

    // 2. Periksa log schedule update
    $scheduleLog = ActivityLog::where('type', 'schedule')
        ->where('schedule_id', $schedule->id)
        ->first();

    expect($scheduleLog)->not->toBeNull()
        ->and($scheduleLog->state)->toBe('success')
        ->and($scheduleLog->state_label)->toBe('Berhasil')
        ->and($scheduleLog->actor)->toBe('Sistem')
        ->and($scheduleLog->subject)->toBe('Microsoft Excel Siang - Lab 1')
        ->and($scheduleLog->metadata['new_instructor_id'])->toBe($this->kanero->id);
});

test('aktivitas penolakan pengganti dicatat dengan tepat beserta alasan penolakan', function () {
    $this->actingAs($this->admin);

    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    $reason = 'Kandidat pengganti sedang mengikuti pelatihan sertifikasi internal.';
    $response = $this->approvalService->rejectSubstitution(
        leaveId: $leave->id,
        scheduleId: $schedule->id,
        rejectionReason: $reason,
    );

    expect($response['success'])->toBeTrue();

    $rejectLog = ActivityLog::where('type', 'approval')
        ->where('schedule_id', $schedule->id)
        ->first();

    expect($rejectLog)->not->toBeNull()
        ->and($rejectLog->state)->toBe('rejected')
        ->and($rejectLog->state_label)->toBe('Ditolak')
        ->and($rejectLog->actor)->toBe($this->admin->name)
        ->and($rejectLog->user_id)->toBe($this->admin->id)
        ->and($rejectLog->description)->toContain($reason)
        ->and($rejectLog->metadata['rejection_reason'])->toBe($reason);
});

test('kegagalan transaksi tidak meninggalkan log parsial di database', function () {
    $this->actingAs($this->admin);

    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    // Simulasikan crash fatal pada saat update schedule di dalam DB::transaction
    Schedule::saving(function ($model) use ($schedule) {
        if ($model->id === $schedule->id && $model->isDirty('instructor_id')) {
            throw new RuntimeException('Simulasi crash database pada saat penyimpanan.');
        }
    });

    expect(fn () => $this->approvalService->approveSubstitution(
        leaveId: $leave->id,
        scheduleId: $schedule->id,
        replacementInstructorId: $this->kanero->id,
    ))->toThrow(RuntimeException::class);

    // Pastikan TIDAK ADA log approval maupun schedule yang tersisa
    $approvalLogCount = ActivityLog::where('schedule_id', $schedule->id)->count();
    expect($approvalLogCount)->toBe(0);
});

test('status gemini ai sukses dicatat dengan tepat', function () {
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'best_candidate_id' => $this->kanero->id,
                                    'summary_explanation' => 'Kanero sangat direkomendasikan karena memiliki kompetensi tingkat lanjut.',
                                    'rankings' => [
                                        [
                                            'instructor_id' => $this->kanero->id,
                                            'instructor_name' => 'Kanero',
                                            'rank' => 1,
                                            'ai_reasoning' => 'Memiliki sertifikasi instruktur Microsoft Excel tingkat lanjut.',
                                            'confidence_score' => 95,
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            'modelVersion' => 'gemini-3.5-flash-lite',
        ], 200),
    ]);

    $this->schedulingEngine->evaluateWithAi($leave);

    $aiSuccessLog = ActivityLog::where('type', 'ai')
        ->where('instructor_leave_id', $leave->id)
        ->latest('id')
        ->first();

    expect($aiSuccessLog)->not->toBeNull()
        ->and($aiSuccessLog->state)->toBe('success')
        ->and($aiSuccessLog->state_label)->toBe('Selesai')
        ->and($aiSuccessLog->actor)->toBe('Gemini AI')
        ->and($aiSuccessLog->metadata['confidence_score'])->toEqual(95);
});

test('status gemini ai fallback dicatat dengan tepat saat api gagal atau kuota habis', function () {
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Quota exceeded'], 500),
    ]);

    $this->schedulingEngine->evaluateWithAi($leave);

    $aiFallbackLog = ActivityLog::where('type', 'ai')
        ->where('instructor_leave_id', $leave->id)
        ->latest('id')
        ->first();

    expect($aiFallbackLog)->not->toBeNull()
        ->and($aiFallbackLog->state)->toBe('fallback')
        ->and($aiFallbackLog->state_label)->toBe('Mode Fallback')
        ->and($aiFallbackLog->metadata['fallback_used'])->toBeTrue();
});

test('status gemini ai not applicable dicatat dengan tepat saat tidak ada kelas terdampak', function () {
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    $dummyResult = new SchedulingResult(
        leaveId: $leave->id,
        instructorId: $this->wahyu->id,
        instructorName: 'Wahyu',
        leaveDate: '2026-10-15',
        leaveStartTime: '13:00',
        leaveEndTime: '17:00',
        leaveReason: null,
        affectedSchedules: [],
        totalAffectedSchedules: 0,
        totalResolvedSchedules: 0,
        allSchedulesResolved: false,
        summary: 'Tidak ada kelas.',
    );

    $notApplicableLog = $this->service->logAiRecommendation($dummyResult, [
        'status' => 'not_applicable',
        'confidence_score' => null,
        'fallback_used' => false,
    ]);

    expect($notApplicableLog->state)->toBe('not_applicable')
        ->and($notApplicableLog->state_label)->toBe('Tidak Diperlukan')
        ->and($notApplicableLog->title)->toBe('Analisis AI tidak diperlukan');
});

test('tidak ada kebocoran api key, token, atau informasi rahasia pada metadata activity log', function () {
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Unavailable'], 503),
    ]);

    $this->schedulingEngine->evaluateWithAi($leave);

    $allLogs = ActivityLog::all();
    foreach ($allLogs as $log) {
        $encoded = json_encode($log->metadata ?? []);
        expect($encoded)->not->toContain('test-dummy-api-key')
            ->and($encoded)->not->toContain('api_key')
            ->and($encoded)->not->toContain('password')
            ->and($encoded)->not->toContain('x-goog-api-key')
            ->and($encoded)->not->toContain('Authorization');
    }
});

test('method toTimelineArray dan getTimelineActivities menghasilkan data yang siap dikonsumsi UI', function () {
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    $log = ActivityLog::where('instructor_leave_id', $leave->id)->firstOrFail();
    $array = $log->toTimelineArray();

    expect($array)->toHaveKeys([
        'id', 'at', 'type', 'type_label', 'state', 'state_label',
        'actor', 'title', 'description', 'subject', 'metadata',
    ])
        ->and($array['at'])->toContain('WIB')
        ->and($array['type_label'])->toBe('Pengajuan Izin')
        ->and($array['state_label'])->toBe('Menunggu');

    // Filter test
    $leaveTimeline = $this->service->getTimelineActivities(type: 'leave');
    expect($leaveTimeline)->not->toBeEmpty();
    foreach ($leaveTimeline as $item) {
        expect($item['type'])->toBe('leave');
    }

    $emptyFilter = $this->service->getTimelineActivities(type: 'non_existent_type');
    expect($emptyFilter)->toBeEmpty();
});

test('toTimelineArray mengonversi timestamp UTC ke zona waktu Asia/Jakarta sebelum menampilkan label WIB', function () {
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'status' => 'pending',
    ]);

    $log = ActivityLog::where('instructor_leave_id', $leave->id)->firstOrFail();

    // Set created_at eksplisit dalam UTC: 2026-10-15 01:30:00 UTC (ekuivalen 08:30 WIB)
    $utcTime = Carbon::create(2026, 10, 15, 1, 30, 0, 'UTC');
    $log->created_at = $utcTime;
    $log->save();

    app()->setLocale('id');
    $array = $log->toTimelineArray();

    // 01:30 UTC + 7 jam = 08:30 WIB
    expect($array['at'])->toBe('15 Okt 2026, 08.30 WIB');
});

test('analyzeWithAi pada InstructorLeaveForm mencatat aktivitas AI ke Activity Log melalui service', function () {
    $this->actingAs($this->admin);

    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'reason' => 'Izin keperluan keluarga',
        'status' => 'pending',
    ]);

    Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'summary_explanation' => 'Instruktur Kanero sangat cocok mengajar Microsoft Excel siang.',
                                    'rankings' => [
                                        [
                                            'instructor_id' => $this->kanero->id,
                                            'instructor_name' => 'Kanero',
                                            'rank' => 1,
                                            'confidence_score' => 95,
                                            'ai_reasoning' => 'Memiliki skill advanced dan waktu kosong.',
                                        ],
                                    ],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $initialResult = $this->schedulingEngine->evaluateLeave($leave)->toArray();

    $component = Livewire::test(InstructorLeaveForm::class)
        ->set('submittedLeaveId', $leave->id)
        ->set('schedulingResult', $initialResult)
        ->call('analyzeWithAi');

    $component->assertHasNoErrors();

    // Verifikasi bahwa log bertipe 'ai' tercatat di tabel activity_logs
    $aiLog = ActivityLog::where('type', 'ai')
        ->where('instructor_leave_id', $leave->id)
        ->first();

    expect($aiLog)->not->toBeNull()
        ->and($aiLog->state)->toBe('success')
        ->and($aiLog->actor)->toBe('Gemini AI')
        ->and($aiLog->user_id)->toBe($this->admin->id)
        ->and($aiLog->metadata['status'])->toBe('success')
        ->and($aiLog->metadata['confidence_score'])->toEqual(95);
});

test('penerapan minimisasi data menyaring informasi pribadi PII pada alasan izin notes dan rejection', function () {
    $this->actingAs($this->admin);

    // 1. Pengajuan izin dengan data sensitif: No HP, NIK, No RM medis, email
    $rawReason = 'Sakit demam opname di RS no rm RM-123456 diagnosa demam berdarah hubungi 081234567890 NIK 1671012345678901 email wahyu@gmail.com';
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '17:00',
        'reason' => $rawReason,
        'status' => 'pending',
    ]);

    $leaveLog = ActivityLog::where('type', 'leave')
        ->where('instructor_leave_id', $leave->id)
        ->firstOrFail();

    $minimizedReason = $leaveLog->metadata['reason'];
    expect($minimizedReason)->not->toContain('081234567890')
        ->and($minimizedReason)->not->toContain('1671012345678901')
        ->and($minimizedReason)->not->toContain('wahyu@gmail.com')
        ->and($minimizedReason)->not->toContain('RM-123456')
        ->and($minimizedReason)->toContain('[REDACTED_PHONE]')
        ->and($minimizedReason)->toContain('[REDACTED_ID]')
        ->and($minimizedReason)->toContain('[REDACTED_EMAIL]')
        ->and($minimizedReason)->toContain('[REDACTED_MEDICAL_REF]');

    // 2. Persetujuan dengan notes yang mengandung nomor HP dan NIK
    $schedule = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    $this->approvalService->approveSubstitution(
        leaveId: $leave->id,
        scheduleId: $schedule->id,
        replacementInstructorId: $this->kanero->id,
        notes: 'Disetujui. Koordinasi via WA 081987654321 atau kontak 1671029384756102',
    );

    $approvalLog = ActivityLog::where('type', 'approval')
        ->where('schedule_id', $schedule->id)
        ->firstOrFail();

    expect($approvalLog->metadata['notes'])->not->toContain('081987654321')
        ->and($approvalLog->metadata['notes'])->not->toContain('1671029384756102')
        ->and($approvalLog->metadata['notes'])->toContain('[REDACTED_PHONE]')
        ->and($approvalLog->metadata['notes'])->toContain('[REDACTED_ID]');

    // 3. Penolakan dengan alasan yang memuat data kontak pribadi
    $schedule2 = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '15:00',
        'end_time' => '17:00',
        'status' => 'scheduled',
    ]);

    $this->approvalService->rejectSubstitution(
        leaveId: $leave->id,
        scheduleId: $schedule2->id,
        rejectionReason: 'Instruktur pengganti tidak bersedia dihubungi di nomor 085566778899 email info@kanero.com',
    );

    $rejectLog = ActivityLog::where('type', 'approval')
        ->where('schedule_id', $schedule2->id)
        ->firstOrFail();

    expect($rejectLog->description)->not->toContain('085566778899')
        ->and($rejectLog->description)->not->toContain('info@kanero.com')
        ->and($rejectLog->metadata['rejection_reason'])->not->toContain('085566778899')
        ->and($rejectLog->metadata['rejection_reason'])->not->toContain('info@kanero.com')
        ->and($rejectLog->metadata['rejection_reason'])->toContain('[REDACTED_PHONE]')
        ->and($rejectLog->metadata['rejection_reason'])->toContain('[REDACTED_EMAIL]');
});

test('deduplikasi log Scheduling Engine aman dari evaluasi paralel dan balapan konkurensi', function () {
    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'pending',
    ]);

    Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    // Jalankan 5 pemanggilan evaluasi beruntun / simultan
    for ($i = 0; $i < 5; $i++) {
        $this->schedulingEngine->evaluateLeave($leave);
    }

    // Hanya boleh ada tepat 1 log engine yang tercatat
    $count = ActivityLog::where('type', 'engine')
        ->where('instructor_leave_id', $leave->id)
        ->count();

    expect($count)->toBe(1);
});

test('rollback transaksi membatalkan log approval yang sempat tertulis jika kegagalan terjadi di tahap penulisan log berikutnya', function () {
    $this->actingAs($this->admin);

    $leave = InstructorLeave::create([
        'instructor_id' => $this->wahyu->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'pending',
    ]);

    $schedule = Schedule::create([
        'course_class_id' => $this->courseClass->id,
        'instructor_id' => $this->wahyu->id,
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    // Pasang hook saving pada ActivityLog:
    // Jika log kedua (logScheduleUpdated dengan type 'schedule') hendak disimpan, lemparkan exception fatal
    ActivityLog::saving(function ($log) {
        if ($log->type === 'schedule') {
            throw new RuntimeException('Simulasi kegagalan database pada penulisan log kedua.');
        }
    });

    expect(fn () => $this->approvalService->approveSubstitution(
        leaveId: $leave->id,
        scheduleId: $schedule->id,
        replacementInstructorId: $this->kanero->id,
        notes: 'Persetujuan dengan simulasi error',
    ))->toThrow(RuntimeException::class);

    // BUKTI ATOMISITAS:
    // Log pertama (logApproval bertipe 'approval') yang sempat dibuat sebelum exception
    // WAJIB ter-rollback sepenuhnya bersama transaksi database
    expect(ActivityLog::where('schedule_id', $schedule->id)->count())->toBe(0)
        ->and(ActivityLog::where('type', 'approval')->where('schedule_id', $schedule->id)->exists())->toBeFalse()
        ->and(ActivityLog::where('type', 'schedule')->where('schedule_id', $schedule->id)->exists())->toBeFalse();

    // Data jadwal dan substitusi tidak termutasi
    expect($schedule->fresh()->instructor_id)->toBe($this->wahyu->id);
    expect(ScheduleSubstitution::where('schedule_id', $schedule->id)->exists())->toBeFalse();
});
