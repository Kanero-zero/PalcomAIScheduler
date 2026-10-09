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
  * `room.suggested_alternative_room`: Objek ruangan alternatif yang disarankan
  * `summary`: Menyebutkan saran pengalihan ke ruangan alternatif.
* **Jika TIDAK ADA ruangan alternatif yang memenuhi syarat:**
  * `status`: `"room_issue"`
  * `is_resolved`: `false`
  * `summary`: Menyebutkan bahwa instruktur ada namun ruangan bermasalah dan tidak ada alternatif.

### E. Kondisi Khusus: Pencegahan Double-Booking Kelas Beririsan
Ketika seorang instruktur yang izin memiliki **dua kelas atau lebih yang jamnya bertabrakan/beririsan**:
* Kandidat instruktur pengganti **tidak boleh dialokasikan ke dua kelas yang berlangsung pada waktu yang sama**.
* Kelas kedua otomatis dialokasikan ke kandidat peringkat berikutnya, dan pesan peringatan dimasukkan ke array `warnings`:
  ```json
  "warnings": [
    "Kandidat Kanero berpotensi bentrok jika ditugaskan ke dua kelas terdampak sekaligus (bersamaan dengan 'Kelas Microsoft Excel - Reguler Siang')."
  ]
  ```
* Jika tidak ada kandidat pengganti lain untuk kelas kedua, kelas kedua ditandai `no_candidate` / `unresolved`.

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
