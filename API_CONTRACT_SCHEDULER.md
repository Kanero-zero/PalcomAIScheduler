# API Contract & Spesifikasi Data: PALCOM AI Scheduler

Dokumen ini merupakan kontrak data resmi antara antarmuka pengguna (**Frontend / UI Form & Hasil Rekomendasi**) dan mesin penjadwalan (**Backend / `SchedulingEngine`**). Dokumen ini ditujukan sebagai panduan teknis implementasi UI menggunakan Laravel Livewire, Blade Flux UI, maupun REST API.

---

## 1. Spesifikasi Field Form Pengajuan Izin

Daftar input yang wajib disediakan pada formulir pengajuan izin instruktur:

| Nama Field | Tipe Data | Status | Aturan Validasi Laravel | Keterangan / Format |
|---|---|---|---|---|
| `instructor_id` | `integer` | **Wajib** | `required\|integer\|exists:instructors,id` | ID instruktur yang mengajukan izin |
| `date` | `string (date)` | **Wajib** | `required\|date` | Tanggal izin dengan format `YYYY-MM-DD` |
| `start_time` | `string (time)` | **Wajib** | `required\|date_format:H:i` | Jam mulai izin format 24 jam `HH:mm` (contoh: `"13:00"`) |
| `end_time` | `string (time)` | **Wajib** | `required\|date_format:H:i\|after:start_time` | Jam selesai izin format 24 jam `HH:mm` (harus setelah `start_time`, contoh: `"18:00"`) |
| `reason` | `string` | Opsional | `nullable\|string\|max:500` | Alasan izin / keterangan singkat (contoh: `"Izin urusan keluarga"`) |

---

## 2. Endpoint & Livewire Action

Aplikasi PALCOM AI Scheduler menggunakan arsitektur **Laravel Livewire 4 + Flux UI**. Frontend dan backend berkomunikasi secara reaktif melalui **Livewire Component Actions** tanpa memerlukan endpoint REST API terpisah, namun endpoint REST API tetap didukung sebagai alternatif.

### A. Pola Utama: Laravel Livewire 4 Component
* **Komponen:** `App\Livewire\InstructorLeaveForm` (atau `App\Livewire\AiScheduler`)
* **State Form (PHP):**
  ```php
  public array $form = [
      'instructor_id' => null,
      'date' => '',
      'start_time' => '',
      'end_time' => '',
      'reason' => '',
  ];

  public ?array $schedulingResult = null;
  ```
* **Trigger Action di Blade:**
  ```html
  <form wire:submit="submitLeave">
      <!-- input fields -->
      <flux:button type="submit">Jalankan Penjadwalan</flux:button>
  </form>
  ```
* **Alur Lifecycle Eksekusi:**
  1. Pengguna mengisi form yang terikat dengan `wire:model="form.*"`.
  2. Saat disubmit, metode `submitLeave()` menjalankan validasi form `$this->validate([...])`.
  3. Menyimpan data izin: `$leave = InstructorLeave::create($this->form);`.
  4. Mengeksekusi engine:
     ```php
     $engine = app(\App\Services\Scheduling\SchedulingEngine::class);
     $this->schedulingResult = $engine->evaluateLeave($leave)->toArray();
     ```
  5. Variabel reaktif `$schedulingResult` terisi dan merender tampilan rekomendasi secara instan.

### B. Alternatif: Endpoint REST API (JSON)
* **Route:** `POST /api/instructor-leaves/evaluate`
* **Method:** `POST`
* **Headers:** `Content-Type: application/json`, `Accept: application/json`

---

## 3. Format Input (Payload Form ke Backend)

Data yang dikirimkan saat pengajuan izin disubmit:

### Format State Livewire (PHP):
```php
public array $form = [
    'instructor_id' => 1,
    'date' => '2026-10-15',
    'start_time' => '13:00',
    'end_time' => '18:00',
    'reason' => 'Izin Urusan Keluarga Mendadak',
];
```

### Format Payload REST API (JSON):
```json
{
  "instructor_id": 1,
  "date": "2026-10-15",
  "start_time": "13:00",
  "end_time": "18:00",
  "reason": "Izin Urusan Keluarga Mendadak"
}
```

---

## 4. Format Output (Kontrak Hasil Penjadwalan)

Struktur data array/JSON hasil evaluasi yang dikembalikan oleh `SchedulingEngine`:

```json
{
  "leave_id": 1,
  "instructor_id": 1,
  "instructor_name": "Wahyu",
  "leave_date": "2026-10-15",
  "leave_start_time": "13:00:00",
  "leave_end_time": "18:00:00",
  "leave_reason": "Izin Urusan Keluarga Mendadak",
  "total_affected_schedules": 2,
  "total_resolved_schedules": 2,
  "all_schedules_resolved": true,
  "summary": "Berhasil menemukan kandidat instruktur pengganti yang valid untuk seluruh 2 kelas yang terdampak.",
  "affected_schedules": [
    {
      "schedule_id": 1,
      "course_class_id": 1,
      "class_name": "Kelas Microsoft Excel - Reguler Siang",
      "subject": "Microsoft Excel",
      "date": "2026-10-15",
      "start_time": "13:00:00",
      "end_time": "15:00:00",
      "status": "resolved",
      "is_resolved": true,
      "has_candidate": true,
      "has_valid_room": true,
      "summary": "Ditemukan 2 kandidat pengganti yang valid. Rekomendasi utama: Kanero (Skor: 110). Ruangan siap.",
      "warnings": [],
      "room": {
        "room_id": 1,
        "room_name": "Lab 1",
        "capacity": 20,
        "student_count": 10,
        "is_capacity_sufficient": true,
        "is_status_available": true,
        "has_room_conflict": false,
        "is_valid": true,
        "notes": "Ruangan Lab 1 siap digunakan (Kapasitas: 20, Siswa: 10).",
        "requires_room_change": false,
        "has_usable_room": true,
        "suggested_alternative_room": null,
        "alternative_rooms": []
      },
      "best_candidate": {
        "instructor_id": 2,
        "instructor_name": "Kanero",
        "is_valid": true,
        "competency_matched": true,
        "skill_name": "Microsoft Excel",
        "skill_level": "advanced",
        "has_schedule_conflict": false,
        "has_leave_conflict": false,
        "score": 110,
        "other_classes_count_today": 1,
        "reasons": [
          "Memiliki kompetensi Microsoft Excel (Tingkat: Advanced).",
          "Jadwal mengajar kosong pada pukul 13:00:00 - 15:00:00.",
          "Tidak ada pengajuan izin pada jam tersebut.",
          "Memiliki 1 jadwal mengajar lain pada hari ini."
        ],
        "disqualification_reason": null
      },
      "valid_candidates": [
        {
          "instructor_id": 2,
          "instructor_name": "Kanero",
          "is_valid": true,
          "competency_matched": true,
          "skill_name": "Microsoft Excel",
          "skill_level": "advanced",
          "score": 110,
          "other_classes_count_today": 1,
          "reasons": [
            "Memiliki kompetensi Microsoft Excel (Tingkat: Advanced).",
            "Jadwal mengajar kosong pada pukul 13:00:00 - 15:00:00.",
            "Tidak ada pengajuan izin pada jam tersebut.",
            "Memiliki 1 jadwal mengajar lain pada hari ini."
          ],
          "disqualification_reason": null
        },
        {
          "instructor_id": 5,
          "instructor_name": "Budi Santoso",
          "is_valid": true,
          "competency_matched": true,
          "skill_name": "Microsoft Excel",
          "skill_level": "intermediate",
          "score": 95,
          "other_classes_count_today": 0,
          "reasons": [
            "Memiliki kompetensi Microsoft Excel (Tingkat: Intermediate).",
            "Jadwal mengajar kosong pada pukul 13:00:00 - 15:00:00.",
            "Tidak ada pengajuan izin pada jam tersebut.",
            "Tidak ada jadwal mengajar lain pada hari ini (beban mengajar ringan)."
          ],
          "disqualification_reason": null
        }
      ],
      "disqualified_candidates": [
        {
          "instructor_id": 3,
          "instructor_name": "Rizky Pratama",
          "is_valid": false,
          "competency_matched": false,
          "skill_name": null,
          "skill_level": null,
          "has_schedule_conflict": true,
          "has_leave_conflict": false,
          "score": 0,
          "other_classes_count_today": 1,
          "reasons": [
            "Tidak memiliki keahlian atau kompetensi untuk mata pelajaran 'Microsoft Excel'.",
            "Jadwal bentrok dengan 'Kelas Web Programming - Reguler Pagi' pada jam 13:00:00 - 16:00:00."
          ],
          "disqualification_reason": "Tidak memiliki keahlian atau kompetensi untuk mata pelajaran 'Microsoft Excel'. Jadwal bentrok dengan 'Kelas Web Programming - Reguler Pagi' pada jam 13:00:00 - 16:00:00."
        },
        {
          "instructor_id": 4,
          "instructor_name": "Dina Oktavia",
          "is_valid": false,
          "competency_matched": false,
          "skill_name": null,
          "skill_level": null,
          "has_schedule_conflict": false,
          "has_leave_conflict": false,
          "score": 0,
          "other_classes_count_today": 1,
          "reasons": [
            "Tidak memiliki keahlian atau kompetensi untuk mata pelajaran 'Microsoft Excel'."
          ],
          "disqualification_reason": "Tidak memiliki keahlian atau kompetensi untuk mata pelajaran 'Microsoft Excel'."
        }
      ]
    }
  ]
}
```

---

## 5. Kondisi Khusus & Penanganan Kesalahan

### A. Kondisi Khusus: Tidak Ditemukan Pengganti yang Memenuhi Syarat
Terjadi ketika semua instruktur lain tidak memiliki kompetensi mata kuliah atau jadwalnya bentrok:
* `status`: `"no_candidate"`
* `has_candidate`: `false`
* `best_candidate`: `null`
* `valid_candidates`: `[]` (array kosong)
* `disqualified_candidates`: Berisi daftar semua instruktur beserta alasan rinci diskualifikasi.
* `summary`: `"Belum ada instruktur pengganti yang memenuhi kualifikasi kompetensi dan bebas bentrok jadwal untuk kelas ini."`
* **Implementasi UI:** Tampilkan callout warning merah/oranye (*alert*) dan accordion daftar instruktur yang tidak memenuhi syarat beserta alasannya.

### B. Kondisi Khusus: Tidak Ada Jadwal Kelas yang Terdampak
Terjadi ketika instruktur mengajukan izin pada rentang jam di mana ia tidak memiliki jadwal mengajar:
* `total_affected_schedules`: `0`
* `total_resolved_schedules`: `0`
* `all_schedules_resolved`: `false`
* `affected_schedules`: `[]`
* `summary`: `"Tidak ada jadwal kelas yang terdampak pada rentang waktu izin ini."`
* **Implementasi UI:** Tampilkan pesan informatif hijau/netral bahwa permohonan izin berhasil dicatat tanpa perlu instruktur pengganti.

### C. Kondisi Khusus: Kesalahan Validasi Input
* **Pada Livewire:** Kesalahan otomatis masuk ke message bag `$errors`. Di template Blade ditampilkan menggunakan:
  ```html
  <flux:error name="form.instructor_id" />
  <flux:error name="form.end_time" />
  ```
* **Pada REST API (HTTP 422 Unprocessable Content):**
  ```json
  {
    "message": "Data pengajuan izin tidak valid.",
    "errors": {
      "instructor_id": ["Instruktur wajib dipilih."],
      "date": ["Tanggal izin wajib diisi."],
      "end_time": ["Waktu selesai harus setelah waktu mulai."]
    }
  }
  ```

### D. Kondisi Khusus: Masalah Ruangan & Rekomendasi Ruangan Alternatif
Ketika ruangan awal bentrok dengan jadwal lain, kapasitas tidak mencukupi, atau sedang dalam perawatan (*maintenance*):
* Sistem otomatis mencari ruangan lain yang kosong dan kapasitasnya mencukupi (*best fit capacity*).
* **Jika ruangan alternatif ditemukan:**
  * `status`: `"resolved"`
  * `is_resolved`: `true`
  * `room.requires_room_change`: `true`
  * `room.has_usable_room`: `true`
  * `room.suggested_alternative_room`: Objek ruangan alternatif yang disarankan
  * `summary`: Menyebutkan saran pengalihan ke ruangan alternatif.
* **Jika TIDAK ADA ruangan alternatif yang memenuhi syarat:**
  * `status`: `"room_issue"`
  * `is_resolved`: `false`
  * `room.has_usable_room`: `false`
  * `room.suggested_alternative_room`: `null`
  * `summary`: Menyebutkan bahwa instruktur ada namun ruangan bermasalah dan tidak ada alternatif.
* **Pencegahan Bentrok Ruangan Alternatif Lintas Kelas (Edge Case):**
  * Jika dua kelas terdampak yang jadwalnya bertabrakan sama-sama membutuhkan ruangan alternatif, engine memastikan **keduanya tidak direkomendasikan ruangan alternatif yang sama**.
  * Ruangan alternatif yang sudah dialokasikan ke kelas pertama akan di-lock. Kelas kedua dialokasikan ke ruangan alternatif berikutnya yang masih tersedia.
  * Jika tidak ada ruangan alternatif lain, kelas kedua ditandai `status: "room_issue"`, `is_resolved: false`, `has_valid_room: false`.

### E. Kondisi Khusus: Pencegahan Double-Booking Instruktur Lintas Kelas
Ketika seorang instruktur yang izin memiliki **dua kelas atau lebih yang jamnya bertabrakan/beririsan**:
* Kandidat instruktur pengganti **tidak boleh dialokasikan ke dua kelas yang berlangsung pada waktu yang sama**.
* **Jika tersedia kandidat pengganti lain:**
  * Kelas kedua otomatis dialokasikan ke kandidat peringkat berikutnya, dan pesan peringatan dimasukkan ke array `warnings`:
    ```json
    "warnings": [
      "Kandidat Kanero berpotensi bentrok jika ditugaskan ke dua kelas terdampak sekaligus (bersamaan dengan 'Kelas Microsoft Excel - Reguler Siang')."
    ]
    ```
* **Jika hanya tersedia satu kandidat pengganti (Edge Case):**
  * Kelas pertama mendapatkan kandidat tersebut (`status: "resolved"`, `is_resolved: true`, `has_candidate: true`).
  * Pada kelas kedua, kandidat tersebut dipindahkan ke `disqualified_candidates` dengan keterangan bentrok lintas kelas.
  * Kelas kedua secara konsisten memiliki:
    * `status`: `"no_candidate"`
    * `best_candidate`: `null`
    * `valid_candidates`: `[]` (kosong)
    * `has_candidate`: `false`
    * `is_resolved`: `false`
  * Nilai `total_resolved_schedules` pada hasil akhir secara konsisten hanya menghitung kelas yang berhasil (`1`), dan `all_schedules_resolved`: `false`.

---

## 6. Sumber Data Dropdown Instruktur

Untuk mengisi opsi elemen `<select>` atau `<flux:select>`:

### Di Komponen Livewire (PHP):
```php
use App\Models\Instructor;
use Illuminate\Database\Eloquent\Collection;

public function getInstructorsProperty(): Collection
{
    return Instructor::query()
        ->where('status', 'active')
        ->orderBy('name')
        ->get(['id', 'name']);
}
```

### Di Template Blade Flux UI:
```html
<flux:field>
    <flux:label>{{ __('Instruktur') }}</flux:label>
    <flux:select wire:model="form.instructor_id" placeholder="Pilih instruktur...">
        @foreach ($this->instructors as $instructor)
            <flux:select.option value="{{ $instructor->id }}">
                {{ $instructor->name }}
            </flux:select.option>
        @endforeach
    </flux:select>
    <flux:error name="form.instructor_id" />
</flux:field>
```

---

## 7. Skenario Demo Pengujian

Untuk memverifikasi integrasi UI dan engine secara instan, database seeder menyediakan skenario demo default:
* **Instruktur Izin:** Wahyu (ID: 1)
* **Tanggal:** Kamis terdekat
* **Rentang Waktu:** 13:00:00 - 18:00:00
* **Kelas Terdampak:**
  1. `Kelas Microsoft Excel - Reguler Siang` (13:00 - 15:00) &rarr; Rekomendasi Utama: **Kanero** (Skor 110), Alternatif: **Budi Santoso** (Skor 95).
  2. `Kelas Microsoft Word - Reguler Sore` (16:00 - 18:00) &rarr; Rekomendasi Utama: **Kanero** (Skor 110).

Eksekusi verifikasi via Artisan Command:
```bash
php artisan schedule:evaluate
```

---

## 8. Panduan Integrasi Frontend (Untuk Anggota 1)

Komponen backend formulir pengajuan izin telah siap di `App\Livewire\InstructorLeaveForm` dengan view template mandiri di `resources/views/livewire/instructor-leave-form.blade.php`.

### Cara Pemasangan di View Halaman (`resources/views/pages/instructor-leaves.blade.php`):
Cukup sematkan tag Livewire berikut di dalam kontainer halaman:
```html
<livewire:instructor-leave-form />
```

### Properti & State yang Tersedia di Komponen:
1. **`$form` (`array`):**
   * `form.instructor_id`: ID instruktur (integer)
   * `form.date`: Tanggal izin (`YYYY-MM-DD`)
   * `form.start_time`: Jam mulai (`HH:mm`, contoh: `13:00`)
   * `form.end_time`: Jam selesai (`HH:mm`, contoh: `18:00`)
   * `form.reason`: Alasan izin (opsional, max 500 karakter)
2. **`$this->instructors` (`Collection`):**
   * Koleksi model `Instructor` aktif yang terurut alfabetis berdasarkan nama (`id`, `name`), siap digunakan untuk mengisi dropdown atau `<flux:select>`.
3. **`$schedulingResult` (`array|null`):**
   * Berisi hasil evaluasi penjadwalan lengkap sesuai kontrak data bagian 4 setelah `submitLeave()` berhasil dieksekusi.
4. **`$submittedLeaveId` (`int|null`):**
   * ID record izin baru yang tersimpan di tabel `instructor_leaves` dengan status awal `'pending'`.
5. **`$feedbackMessage` (`string|null`):**
   * Pesan informatif untuk notifikasi status pengajuan izin dan deteksi jadwal.

### Action yang Tersedia:
* **`wire:submit="submitLeave"`** &rarr; Memvalidasi form, mencegah duplikasi, menyimpan izin dengan status `pending`, dan menjalankan `SchedulingEngine`.
* **`wire:click="analyzeWithAi"`** &rarr; Meminta analisis lanjutan dan penjelasan mendalam menggunakan **Google Gemini 3.5 Flash-Lite** (hanya dijalankan saat admin meminta).
* **`wire:click="resetForm"`** &rarr; Mengosongkan form input dan menghapus hasil evaluasi.

---

### Integrasi pada Halaman AI Auto-Scheduler (`App\Livewire\AiScheduler`):
Pada halaman AI Auto-Scheduler (`resources/views/livewire/ai-scheduler.blade.php`), evaluasi deterministik dimuat sebagai hasil utama.
* **`wire:click="runScheduler"`** / **`selectLeave(id)`** &rarr; Menjalankan evaluasi deterministik murni dan mereset status/analisis AI sebelumnya.
* **`wire:click="analyzeWithAi"`** &rarr; Meminta analisis peringkat dan ringkasan eksekutif dari Gemini AI untuk izin yang sedang aktif.

---

## 9. Integrasi AI: Google Gemini 3.5 Flash-Lite

Untuk memperkaya rekomendasi deterministik dengan penjelasan manusiawi yang mendalam dan skor keyakinan, sistem mengintegrasikan **Google Gemini 3.5 Flash-Lite**:
* **Provider:** Google Gemini API (`https://generativelanguage.googleapis.com/v1beta`)
* **Model ID:** `gemini-3.5-flash-lite` (Stable GA)
* **Keamanan Kunci API:**
  - Disimpan aman di `.env` (`GEMINI_API_KEY`), tidak di-commit ke Git.
  - **Dikirim via HTTP Header `x-goog-api-key`**, bukan melalui URL query parameter.
  - Exception dan log disanitasi agar tidak membocorkan data sensitif atau kredensial API.

### Prinsip Utama & Aturan Validasi Ketat:
1. **Penentu Kebenaran Mutlak:** `SchedulingEngine` deterministik lokal tetap menjadi satu-satunya otoritas penentu kelayakan instruktur (keahlian, bentrok jadwal, izin) dan ruangan.
2. **Peran Gemini AI:** Hanya memeringkat dan menjelaskan kandidat yang **sudah dinyatakan valid** oleh Scheduling Engine. AI tidak pernah diberi akses untuk meloloskan kandidat yang didiskualifikasi.
3. **Validasi Ketat Anti-Halusinasi:**
   - **ID Kandidat:** Harus terdaftar dalam pool `validCandidates` dan wajib unik (tidak boleh duplikat).
   - **Nama Instruktur:** Wajib diambil dari database/hasil deterministik, bukan dari teks keluaran AI.
   - **Peringkat (Rank):** Tidak boleh ada ranking duplikat; dinormalisasi secara sekuensial (1, 2, ...).
   - **Skor Keyakinan (Confidence Score):** Metrik khusus AI yang dibatasi ketat dalam rentang valid `0` hingga `100`. Pada mode fallback, nilai ini bernilai `null` dan tidak diisi oleh skor sistem deterministik (karena skor deterministik bisa > 100).
4. **Pencegahan Konflik Lintas Kelas (Cross-Schedule Allocation):**
   - Hasil peringkat AI diproses ulang melalui `resolveCrossScheduleAiConflicts` untuk memastikan tidak ada instruktur yang sama direkomendasikan pada dua kelas terdampak yang waktu pelaksanaannya beririsan/bersamaan.
5. **Otorisasi & Keamanan Akses:**
   - Action `analyzeWithAi()` dilindungi oleh otorisasi `Gate::authorize('analyze-with-ai')` sehingga hanya pengguna/admin terotentikasi yang berhak mengeksekusi analisis AI.
6. **Mekanisme 4 Status Analisis Transparan:**
   Sistem membedakan secara tegas 4 status hasil analisis pada `ai_summary.status`:
   - `success`: Seluruh kelas yang memenuhi syarat berhasil dianalisis penuh oleh Gemini AI.
   - `partial`: Sebagian kelas berhasil dianalisis AI, sebagian menggunakan fallback deterministik akibat kendala API.
   - `fallback`: Seluruh kelas gagal dianalisis AI dan beralih ke rekomendasi deterministik sistem.
   - `not_applicable`: Tidak ada kelas terdampak atau tidak ada kandidat pengganti yang memenuhi syarat.
7. **Eksekusi Eksplisit (On-Demand):** Panggilan AI **tidak dijalankan otomatis saat submit**, melainkan hanya saat admin secara sadar menekan tombol *"Analisis dengan Gemini AI"* atau menambahkan opsi `--ai` pada CLI Artisan.

### Struktur Data Tambahan Hasil AI pada Kontrak JSON:

#### A. Saat Panggilan Gemini AI Berhasil:
Pada setiap jadwal terdampak (`affected_schedules.*`):
```json
"ai_recommendation": {
  "status": "success",
  "is_ai_generated": true,
  "model": "gemini-3.5-flash-lite",
  "best_candidate_id": 2,
  "summary_explanation": "Kanero sangat direkomendasikan karena memiliki kompetensi tingkat Advanced...",
  "rankings": [
    {
      "instructor_id": 2,
      "instructor_name": "Kanero",
      "rank": 1,
      "ai_reasoning": "Sangat menguasai materi Microsoft Excel dan memiliki beban mengajar ringan...",
      "confidence_score": 96
    }
  ],
  "fallback_used": false,
  "fallback_reason": null
}
```

Pada root hasil evaluasi (`ai_summary`):
```json
"ai_summary": {
  "status": "success",
  "is_ai_generated": true,
  "model": "gemini-3.5-flash-lite",
  "executive_summary": "Analisis Gemini AI (gemini-3.5-flash-lite): Seluruh 2 kelas terdampak berhasil dianalisis dan diperingkat berdasarkan kesesuaian keahlian serta beban mengajar...",
  "fallback_used": false,
  "fallback_reason": null,
  "generated_at": "2026-10-10T08:25:00+07:00"
}
```

#### B. Saat Terjadi Kegagalan API (Mode Fallback Digunakan):
Pada setiap jadwal terdampak (`affected_schedules.*`):
```json
"ai_recommendation": {
  "status": "fallback",
  "is_ai_generated": false,
  "model": "gemini-3.5-flash-lite",
  "best_candidate_id": 2,
  "summary_explanation": "Mode Fallback: Menggunakan rekomendasi deterministik sistem (Alasan: Layanan Gemini AI tidak dapat diakses (HTTP 503)).",
  "rankings": [
    {
      "instructor_id": 2,
      "instructor_name": "Kanero",
      "rank": 1,
      "ai_reasoning": "Memiliki kompetensi Microsoft Excel (Advanced); Beban mengajar 0 kelas hari ini",
      "confidence_score": null
    }
  ],
  "fallback_used": true,
  "fallback_reason": "Layanan Gemini AI tidak dapat diakses (HTTP 503)."
}
```

Pada root hasil evaluasi (`ai_summary`):
```json
"ai_summary": {
  "status": "fallback",
  "is_ai_generated": false,
  "model": "gemini-3.5-flash-lite",
  "executive_summary": "Mode Fallback Aktif: Analisis AI Gemini tidak tersedia (Layanan Gemini AI tidak dapat diakses (HTTP 503)). Rekomendasi dihitung menggunakan Scheduling Engine deterministik berbasis kompetensi dan ketersediaan.",
  "fallback_used": true,
  "fallback_reason": "Layanan Gemini AI tidak dapat diakses (HTTP 503).",
  "generated_at": "2026-10-10T08:25:00+07:00"
}
```

Eksekusi CLI dengan AI:
```bash
php artisan schedule:evaluate --ai
```

---

### 8. Hak Akses & Otorisasi Penggunaan Gemini AI
Aksi `analyzeWithAi()` dilindungi secara ketat oleh otorisasi `Gate::authorize('analyze-with-ai')`:
- **Admin**: Diizinkan (`Response::allow()`). Menjalankan evaluasi AI Google Gemini 3.5 Flash-Lite.
- **Pengguna Biasa (Non-Admin)**: Ditolak dengan HTTP 403 Forbidden (`Response::deny('Hanya pengguna dengan hak akses administrator yang dapat menjalankan analisis Gemini AI.')`).
- **Pengguna Belum Login (Guest)**: Ditolak dengan HTTP 403 Forbidden.
- **Proteksi API**: Penolakan otorisasi terjadi sebelum pemanggilan layer AI, sehingga request tidak berwenang dijamin tidak memicu panggilan HTTP ke endpoint Gemini API.
- **Mekanisme Penentuan Admin Sementara (Interim RBAC)**: Menggunakan `$user->isAdmin()`, yang memvalidasi email terhadap allowlist eksplisit `config('auth.admin_emails')` (default: `admin@palcomtech.ac.id`, `admin@example.com`).

---

## 9. Kontrak Integrasi Backend Approval / Reject (Tahap 12)

Kontrak ini mengatur alur interaksi antara antarmuka frontend (yang dikembangkan oleh Anggota 1 pada branch `feat/ui-approval-reject`) dan backend engine persetujuan (yang dikembangkan oleh Anggota 2 pada branch `feat/backend-approval-reject`).

---

### A. Alur Bisnis Keputusan Admin (Scope MVP)
1. **Fokus per Jadwal Kelas:**
   Persetujuan atau penolakan difokuskan pada keputusan penugasan instruktur pengganti **per jadwal kelas terdampak**. Status pada `schedule_substitutions` **independen** dari status izin induk `instructor_leaves.status`. Persetujuan atau penolakan pengganti tidak otomatis mengubah status izin induk.
2. **Kewenangan & Keamanan:**
   Hanya admin berwenang (`Gate::authorize('manage-schedule-approval')`) yang dapat menyetujui atau menolak.
3. **Persetujuan Instruktur Pengganti (`approveSubstitution`):**
   - Admin memilih salah satu kandidat valid dari hasil evaluasi Scheduling Engine.
   - Backend melakukan **re-validasi penuh saat tombol ditekan** (memeriksa keaktifan, kompetensi, bentrok jadwal, bentrok izin, dan kelayakan ruangan).
   - **Pencegahan Konflik Ruangan:** Jika ruangan bermasalah atau memerlukan perpindahan ruangan (`requiresRoomChange === true`), persetujuan diblokir dengan pesan informatif hingga fitur pemilihan ruangan alternatif tersedia.
   - **Pencegahan Double-Booking Lintas Kelas:** Backend memastikan kandidat pengganti belum disetujui untuk kelas lain yang jadwalnya saling tumpang tindih.
   - Jadwal kelas terkait (`schedules.instructor_id`) diperbarui ke ID instruktur pengganti terpilih.
   - Keputusan dicatat secara permanen di database.
4. **Penolakan Rekomendasi Pengganti (`rejectSubstitution`):**
   - Admin wajib menyertakan **alasan penolakan** (`rejection_reason`) dengan panjang **minimal 10 karakter dan maksimal 500 karakter** (selaras dengan validasi UI Anggota 1).
   - Jadwal kelas **TIDAK MENGALAMI PERUBAHAN** (`schedules.instructor_id` tetap instruktur awal).
   - Keputusan penolakan dan alasannya dicatat secara permanen di database.
5. **Pencegahan Keputusan Ganda & Concurrency:**
   - Jadwal yang sudah disetujui atau ditolak tidak dapat diproses ulang (idempoten).
   - Menggunakan `DB::transaction()` dan unique constraint `(instructor_leave_id, schedule_id)` yang kompatibel dengan SQLite untuk menjamin integritas data saat diakses bersamaan.

---

### B. Mekanisme Penelusuran Jadwal Pasca-Persetujuan (Schedule Tracking)
Ketika admin menyetujui penggantian, kolom `schedules.instructor_id` akan berubah menjadi ID instruktur pengganti (misal: Kanero).
Jika pencarian jadwal hanya mengandalkan `where('instructor_id', $leave->instructor_id)`, maka jadwal yang sudah disetujui **akan hilang dari daftar evaluasi izin**.

**Solusi Backend:**
1. Menyimpan `original_instructor_id` secara permanen pada tabel `schedule_substitutions`.
2. Saat mengambil jadwal terdampak untuk suatu izin, backend menggabungkan:
   - Jadwal yang saat ini masih diasosiasikan dengan instruktur izin (`schedules.instructor_id == $leave->instructor_id`), **DAN**
   - Jadwal yang sudah memiliki riwayat keputusan pada `schedule_substitutions` untuk izin tersebut (`instructor_leave_id == $leave->id`).
3. Menjamin jadwal yang telah disetujui **tetap tampil di UI setelah halaman direfresh**, lengkap dengan badge status `approved` dan nama instruktur pengganti yang aktif.

---

### C. Perubahan Skema Database (Kompatibel SQLite & MySQL)

Tabel baru: **`schedule_substitutions`**
Dibuat menggunakan Laravel Schema Builder standar:
```php
Schema::create('schedule_substitutions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('instructor_leave_id')->constrained('instructor_leaves')->cascadeOnDelete();
    $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
    $table->foreignId('original_instructor_id')->constrained('instructors')->restrictOnDelete();
    $table->foreignId('replacement_instructor_id')->nullable()->constrained('instructors')->restrictOnDelete();
    $table->string('status')->default('pending'); // 'pending', 'approved', 'rejected'
    $table->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('decision_at')->nullable();
    $table->text('rejection_reason')->nullable(); // Wajib jika status 'rejected'
    $table->text('notes')->nullable();            // Catatan opsional admin
    $table->timestamps();

    // Mencegah keputusan ganda pada jadwal yang sama untuk izin yang sama
    $table->unique(['instructor_leave_id', 'schedule_id'], 'uq_leave_schedule_sub');
});
```

---

### D. Struktur Data Status Keputusan pada Hasil Jadwal (`approval_status`)
Setiap elemen jadwal terdampak (`affected_schedules.*`) dilengkapi atribut objek `approval_status`:

```json
{
  "schedule_id": 1,
  "course_class_id": 1,
  "class_name": "Kelas Microsoft Excel - Reguler Siang",
  "subject": "Microsoft Excel",
  "date": "2026-10-15",
  "start_time": "13:00:00",
  "end_time": "15:00:00",
  "status": "resolved",
  "approval_status": {
    "is_decided": true,
    "status": "approved",
    "substitution_id": 10,
    "original_instructor_id": 1,
    "original_instructor_name": "Wahyu",
    "replacement_instructor_id": 2,
    "replacement_instructor_name": "Kanero",
    "decision_by": 1,
    "decision_by_name": "Admin BAAK PalComTech",
    "decision_at": "2026-10-10T10:30:00+07:00",
    "rejection_reason": null,
    "notes": "Disetujui sesuai rekomendasi utama"
  }
}
```

*Kondisi Belum Diputuskan (`pending`):*
```json
"approval_status": {
  "is_decided": false,
  "status": "pending",
  "substitution_id": null,
  "original_instructor_id": 1,
  "original_instructor_name": "Wahyu",
  "replacement_instructor_id": null,
  "replacement_instructor_name": null,
  "decision_by": null,
  "decision_by_name": null,
  "decision_at": null,
  "rejection_reason": null,
  "notes": null
}
```

*Kondisi Ditolak (`rejected`):*
```json
"approval_status": {
  "is_decided": true,
  "status": "rejected",
  "substitution_id": 11,
  "original_instructor_id": 1,
  "original_instructor_name": "Wahyu",
  "replacement_instructor_id": null,
  "replacement_instructor_name": null,
  "decision_by": 1,
  "decision_by_name": "Admin BAAK PalComTech",
  "decision_at": "2026-10-10T10:32:00+07:00",
  "rejection_reason": "Kandidat pengganti berhalangan karena persiapan akreditasi kampus.",
  "notes": null
}
```

---

### E. Definisi Action Livewire Utama

#### 1. Action: Setujui Instruktur Pengganti (`approveSubstitution`)
* **Signature Method:**
  ```php
  public function approveSubstitution(int $leaveId, int $scheduleId, int $replacementInstructorId, ?string $notes = null): array
  ```
* **Parameter:**
  | Parameter | Tipe Data | Wajib | Keterangan |
  |---|---|---|---|
  | `$leaveId` | `int` | Ya | ID pengajuan izin terkait |
  | `$scheduleId` | `int` | Ya | ID jadwal kelas yang terdampak |
  | `$replacementInstructorId` | `int` | Ya | ID instruktur pengganti yang dipilih |
  | `$notes` | `?string` | Tidak | Catatan opsional admin (maksimal 500 karakter) |

* **Aturan Validasi & Keamanan:**
  1. Otorisasi: `Gate::authorize('manage-schedule-approval')` (hanya pengguna admin).
  2. Verifikasi Relasi: Jadwal `$scheduleId` **wajib benar-benar merupakan jadwal yang terdampak** oleh izin `$leaveId` (beririsan tanggal, jam, dan instruktur asal).
  3. Re-validasi Kelayakan: Kandidat pengganti wajib valid berdasarkan evaluasi Scheduling Engine pada waktu eksekusi.
  4. Pemeriksaan Ruangan: Jika ruangan kelas bermasalah atau memerlukan perpindahan ruangan yang belum terselesaikan, persetujuan dibatalkan.
  5. Pemeriksaan Bentrok Lintas Kelas: Memastikan instruktur pengganti belum ditugaskan pada kelas lain yang jadwalnya bersamaan.
  6. Idempotensi: Jadwal yang sudah berstatus `approved` atau `rejected` tidak dapat diproses ulang.

* **Format Respons:**
  ```json
  {
    "success": true,
    "message": "Instruktur pengganti berhasil disetujui dan jadwal kelas telah diperbarui.",
    "data": {
      "substitution_id": 10,
      "leave_id": 1,
      "schedule_id": 1,
      "status": "approved",
      "assigned_instructor": {
        "id": 2,
        "name": "Kanero"
      },
      "decision_by": 1,
      "decision_by_name": "Admin BAAK PalComTech",
      "decision_at": "2026-10-10T10:30:00+07:00"
    }
  }
  ```

---

#### 2. Action: Tolak Rekomendasi Pengganti (`rejectSubstitution`)
* **Signature Method:**
  ```php
  public function rejectSubstitution(int $leaveId, int $scheduleId, string $rejectionReason): array
  ```
* **Parameter:**
  | Parameter | Tipe Data | Wajib | Keterangan |
  |---|---|---|---|
  | `$leaveId` | `int` | Ya | ID pengajuan izin terkait |
  | `$scheduleId` | `int` | Ya | ID jadwal kelas yang terdampak |
  | `$rejectionReason` | `string` | **Ya** | Alasan penolakan (**minimal 10 karakter, maksimal 500 karakter**) |

* **Aturan Validasi & Keamanan:**
  1. Otorisasi: `Gate::authorize('manage-schedule-approval')` (hanya pengguna admin).
  2. Verifikasi Relasi: Jadwal `$scheduleId` wajib terverifikasi sebagai kelas terdampak izin `$leaveId`.
  3. Validasi Alasan: Alasan penolakan **wajib diisi minimal 10 karakter**.
  4. Keutuhan Jadwal: Kolom `schedules.instructor_id` **tidak disentuh sama sekali** (jadwal tetap seperti semula).
  5. Idempotensi: Jadwal yang sudah diputuskan tidak dapat diproses ulang.

* **Format Respons:**
  ```json
  {
    "success": true,
    "message": "Rekomendasi pengganti berhasil ditolak. Jadwal kelas tidak mengalami perubahan.",
    "data": {
      "substitution_id": 11,
      "leave_id": 1,
      "schedule_id": 1,
      "status": "rejected",
      "rejection_reason": "Kandidat pengganti berhalangan karena persiapan akreditasi kampus.",
      "decision_by": 1,
      "decision_by_name": "Admin BAAK PalComTech",
      "decision_at": "2026-10-10T10:32:00+07:00"
    }
  }
  ```

---

### F. Penanganan Kesalahan (Error Handling & HTTP Response)
- **HTTP 403 Forbidden:** Pengguna tidak terautentikasi atau bukan admin.
- **HTTP 422 Unprocessable Content:**
  - `rejection_reason`: "Alasan penolakan wajib diisi minimal 10 karakter."
  - `schedule_id`: "Jadwal kelas bukan merupakan bagian dari pengajuan izin yang dipilih."
  - `replacement_instructor_id`: "Instruktur yang dipilih tidak memenuhi kualifikasi kompetensi kelas ini."
  - Status Ganda: "Jadwal ini sudah memiliki keputusan persetujuan dan tidak dapat diubah kembali."
  - Ruangan Bermasalah: "Persetujuan ditunda: Ruangan kelas bermasalah atau memerlukan pemindahan ruangan. Selesaikan kendala ruangan sebelum menetapkan instruktur pengganti."
- **HTTP 409 Conflict:** Terjadi bentrok jadwal baru pada saat re-validasi kandidat.

---

### G. Kesiapan Integrasi ke Activity Log (Tahap 13)
Setiap transaksi menyimpan data yang dapat langsung dipetakan ke timeline `/activity-log`:
- `type`: `'approval'` (dan `'schedule'` saat penugasan diperbarui)
- `state`: `'success'` (disetujui) atau `'rejected'` (ditolak)
- `actor`: `$user->name` (Admin yang memutuskan)
- `subject`: `'Pengajuan Izin #' . $leaveId` dan `'Kelas ' . $className`
- `title`: `'Rekomendasi instruktur disetujui'` / `'Usulan pengganti ditolak'`
- `description`: Detail nama pengganti atau alasan penolakan yang tersimpan.

---

## 6. KONTRAK DATA & ARSITEKTUR BACKEND ACTIVITY LOG (TAHAP 13)

### A. Tujuan & Ruang Lingkup
Mencatat seluruh siklus hidup penjadwalan instruktur pengganti secara persisten di database SQLite/MySQL, merekam kejadian nyata dari:
1. **Pengajuan Izin (`leave`)**: Perekaman pengajuan izin instruktur baru.
2. **Scheduling Engine (`engine`)**: Deteksi kelas terdampak serta evaluasi kesesuaian kandidat dan ruangan.
3. **Analisis AI (`ai`)**: Hasil rekomendasi Gemini AI atau fallback deterministik.
4. **Persetujuan Admin (`approval`)**: Keputusan Approve atau Reject pengganti oleh admin.
5. **Pembaruan Jadwal (`schedule`)**: Penerapan instruktur pengganti ke jadwal kelas.

---

### B. Skema Database (`activity_logs`)

Tabel baru: **`activity_logs`**

```php
Schema::create('activity_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('type', 32)->index(); // leave, engine, ai, approval, schedule
    $table->string('state', 32)->index(); // success, pending, rejected, info, warning
    $table->string('actor'); // Nama user, 'Sistem', atau 'Gemini AI'
    $table->string('title');
    $table->text('description');
    $table->string('subject'); // e.g. 'Pengajuan Izin #1', 'Microsoft Excel - Lab 2'
    $table->foreignId('instructor_leave_id')->nullable()->constrained('instructor_leaves')->nullOnDelete();
    $table->foreignId('schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
    $table->string('fingerprint', 64)->nullable()->index(); // Untuk pencegahan duplikasi log evaluasi
    $table->json('metadata')->nullable(); // Context data terstruktur (tanpa API key)
    $table->timestamps();

    $table->index(['created_at', 'type']);
});
```

---

### C. Pemetaan Tipe Aktivitas & Label UI

| Type | Type Label | State | State Label | Actor Default | Subject | Contoh Title |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `leave` | Pengajuan Izin | `pending` | Menunggu | User Name / Admin | `Pengajuan Izin #{id}` | Pengajuan izin instruktur dicatat |
| `engine` | Scheduling Engine | `success` | Selesai | `Sistem` | `Pengajuan Izin #{id}` | Kandidat pengganti ditemukan / evaluasi selesai |
| `ai` | Analisis AI | `success` / `fallback` | Selesai / Fallback | `Gemini AI` | `Pengajuan Izin #{id}` | Rekomendasi instruktur dianalisis oleh AI |
| `approval` | Persetujuan | `success` / `rejected` | Disetujui / Ditolak | Admin Name | `Pengajuan Izin #{id}` | Rekomendasi instruktur disetujui / Usulan ditolak |
| `schedule` | Jadwal | `success` | Berhasil | `Sistem` / Admin Name | `{Class Name} - {Room}` | Jadwal kelas diperbarui |

---

### D. Format Data untuk Konsumsi Livewire / Timeline UI

Method pada model `ActivityLog::toTimelineArray()` menghasilkan format yang persis dibutuhkan oleh view `resources/views/pages/activity-log.blade.php`:

```php
[
    'id' => 12,
    'at' => '10 Okt 2026, 11.45 WIB',
    'type' => 'approval',
    'type_label' => 'Persetujuan',
    'state' => 'success',
    'state_label' => 'Disetujui',
    'actor' => 'Admin BAAK PalComTech',
    'title' => 'Rekomendasi instruktur disetujui',
    'description' => 'Admin menyetujui Wahyu sebagai instruktur pengganti untuk sesi terdampak.',
    'subject' => 'Pengajuan Izin #1',
    'metadata' => [
        'leave_id' => 1,
        'schedule_id' => 3,
        'replacement_instructor_id' => 2,
        'original_instructor_id' => 1,
    ],
]
```

---

### E. Strategi Pencegahan Duplikasi Log (Deduplication)

1. **Evaluasi Engine:**
   - Karena halaman `AiScheduler` dan `ApprovalReview` menjalankan evaluasi setiap kali dibuka (`mount()`, `selectLeave()`), evaluasi engine menggunakan hash `fingerprint`:
     `sha1("engine:{$leaveId}:{$totalAffected}:{$totalResolved}:" . implode(',', $scheduleIds))`
   - Log engine tidak akan diduplikasi jika fingerprint identik sudah dicatat dalam kurun waktu 1 jam terakhir, kecuali status evaluasi mengalami perubahan nyata.
2. **Analisis AI:**
   - Hanya dicatat ketika action `analyzeWithAi()` dipicu secara eksplisit oleh admin.
3. **Persetujuan & Penolakan:**
   - Dicatat satu kali per eksekusi keputusan pada jadwal kelas terkait.

---

### F. Integritas Transaksi & Keamanan Data

1. **Atomisitas Transaksi:**
   - Log untuk `approval` dan `schedule` dicatat di dalam blok `DB::transaction()` pada `ScheduleApprovalService`. Jika validasi gagal atau rollback terjadi, entri log otomatis ikut ter-rollback.
2. **Zero Leakage:**
   - Metadata JSON dilarang menyimpan API Key, token otentikasi, atau password hash.


