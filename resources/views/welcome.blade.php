<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>PALCOM AI Scheduler — Penjadwalan Cerdas</title>
    <meta name="description" content="PALCOM AI Scheduler membantu pengelolaan jadwal kelas dan rekomendasi instruktur pengganti menggunakan Agentic AI dengan persetujuan manusia.">

    <style>
        :root {
            --blue: #1565D8;
            --deep: #0A3D91;
            --yellow: #FFC928;
            --ink: #13223A;
            --muted: #607087;
            --light: #F4F8FF;
            --line: #E1E9F4;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            color: var(--ink);
            background: white;
            line-height: 1.6;
        }
        a { color: inherit; text-decoration: none; }
        .container { width: min(1140px, 90%); margin: auto; }

        header {
            background: white;
            border-bottom: 1px solid var(--line);
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .navigation {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 850;
            font-size: 19px;
            letter-spacing: -0.6px;
        }
        .brand-mark {
            display: grid;
            place-items: center;
            width: 43px;
            height: 43px;
            background: var(--blue);
            color: var(--yellow);
            border-radius: 13px;
            font-size: 23px;
            font-weight: 900;
        }
	.brand-logo {
    width: 110px;
    height: 44px;
    object-fit: contain;
}
        .brand small {
            display: block;
            color: var(--muted);
            font-size: 10px;
            font-weight: 650;
            letter-spacing: 1.6px;
            line-height: 1.4;
        }
        .nav-links {
            display: flex;
            gap: 27px;
            align-items: center;
            font-size: 13px;
            font-weight: 700;
            color: #43536A;
        }
        .nav-links a:hover { color: var(--blue); }

        .hero {
            background:
                radial-gradient(circle at 85% 20%, rgba(255,201,40,.16), transparent 28%),
                linear-gradient(130deg, #092C6B, #0A3D91 58%, #1565D8);
            color: white;
            padding: 100px 0 110px;
            overflow: hidden;
        }
        .hero-grid {
            display: grid;
            grid-template-columns: 1.12fr 1fr;
            gap: 68px;
            align-items: center;
        }
        .eyebrow {
            display: inline-block;
            padding: 8px 14px;
            color: var(--yellow);
            border: 1px solid rgba(255,255,255,.27);
            background: rgba(255,255,255,.07);
            border-radius: 50px;
            font-size: 11px;
            font-weight: 850;
            letter-spacing: 1.5px;
        }
        h1 {
            font-size: clamp(39px, 5vw, 64px);
            line-height: 1.12;
            letter-spacing: -2.6px;
            margin: 25px 0 21px;
        }
        h1 span { color: var(--yellow); }
        .hero p {
            font-size: 16px;
            color: #D8E7FF;
            max-width: 560px;
            margin-bottom: 30px;
        }
        .buttons { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 12px 21px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
            transition: transform .2s;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-yellow { background: var(--yellow); color: #152743; }
        .btn-outline { border: 1px solid #799BCE; color: white; }

        .mockup {
            padding: 23px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 28px 65px rgba(0,15,50,.32);
            color: var(--ink);
            transform: rotate(1deg);
        }
        .mockup-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding-bottom: 17px;
            border-bottom: 1px solid var(--line);
        }
        .mockup-title { font-weight: 850; font-size: 15px; }
        .tag {
            border-radius: 7px;
            padding: 5px 8px;
            background: #EDF5FF;
            color: var(--blue);
            font-size: 10px;
            font-weight: 850;
        }
        .notice {
            padding: 15px;
            background: #FFF8E2;
            border: 1px solid #F9E6A2;
            border-radius: 11px;
            margin: 20px 0;
            font-size: 12px;
        }
        .notice strong { display: block; font-size: 14px; }
        .candidate {
            border: 1px solid var(--line);
            padding: 12px 14px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border-radius: 10px;
            margin-bottom: 9px;
        }
        .candidate strong { font-size: 13px; }
        .candidate small {
            display: block;
            font-size: 11px;
            color: var(--muted);
        }
        .state {
            font-size: 10px;
            font-weight: 850;
            align-self: center;
            white-space: nowrap;
        }
        .valid { color: #137A4B; }
        .invalid { color: #B44A46; }
        .mockup-foot {
            margin-top: 14px;
            padding: 12px;
            text-align: center;
            background: var(--light);
            color: var(--deep);
            border-radius: 9px;
            font-weight: 800;
            font-size: 11px;
        }

        section { padding: 88px 0; }
        .section-head {
            max-width: 730px;
            margin: 0 auto 40px;
            text-align: center;
        }
        .section-head .overline {
            color: var(--blue);
            text-transform: uppercase;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 2px;
        }
        h2 {
            margin: 12px 0 15px;
            font-size: clamp(29px, 4vw, 40px);
            line-height: 1.2;
            letter-spacing: -1.3px;
        }
        .section-head p, .card p, .step p {
            color: var(--muted);
            font-size: 14px;
        }
        .soft { background: var(--light); }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 19px;
        }
        .card {
            padding: 29px;
            background: white;
            border: 1px solid var(--line);
            border-radius: 15px;
        }
        .card-icon {
            display: grid;
            place-items: center;
            width: 47px;
            height: 47px;
            background: #EAF2FF;
            color: var(--blue);
            border-radius: 12px;
            font-size: 21px;
            font-weight: 900;
        }
        .card h3 { margin: 22px 0 8px; font-size: 18px; }
        .card p { margin-bottom: 0; }

        .steps {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
        }
        .step {
            border-top: 3px solid var(--blue);
            padding: 18px 10px 0 0;
        }
        .step-number {
            color: var(--blue);
            font-size: 28px;
            font-weight: 900;
        }
        .step h3 { font-size: 15px; margin: 12px 0 8px; }
        .step p { font-size: 12px; margin: 0; }

        .showcase {
            background: white;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 35px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 35px;
            align-items: center;
        }
        .showcase h3 {
            font-size: 27px;
            line-height: 1.3;
            margin: 12px 0;
        }
        .showcase p { color: var(--muted); font-size: 14px; }
        .showcase-label {
            color: var(--blue);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 1px;
        }
        .scenario-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .scenario-list li {
            padding: 12px 0 12px 19px;
            border-bottom: 1px solid var(--line);
            position: relative;
            font-size: 13px;
        }
        .scenario-list li::before {
            content: "";
            position: absolute;
            left: 0;
            top: 20px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--blue);
        }

        .closing {
            text-align: center;
            padding: 75px 0;
            color: white;
            background: var(--deep);
        }
        .closing h2 { max-width: 690px; margin: 0 auto 18px; }
        .closing p {
            color: #D6E6FF;
            max-width: 650px;
            margin: auto;
            font-size: 14px;
        }
        footer {
            background: #071F4D;
            color: #C6D7F1;
            padding: 22px 0;
            font-size: 12px;
        }
        .footer-inner {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        @media (max-width: 820px) {
            .hero-grid { grid-template-columns: 1fr; gap: 45px; }
            .cards { grid-template-columns: 1fr; }
            .steps { grid-template-columns: repeat(2, 1fr); }
            .showcase { grid-template-columns: 1fr; }
            .nav-links { display: none; }
            .hero { padding: 75px 0; }
        }
        @media (max-width: 480px) {
            .steps { grid-template-columns: 1fr; }
            .mockup { padding: 17px; transform: none; }
            section { padding: 65px 0; }
            .showcase { padding: 22px; }
            h1 { letter-spacing: -1.5px; }
        }
    </style>
</head>

<body>
    <header>
        <div class="container navigation">
            <a href="/" class="brand" aria-label="PALCOM AI Scheduler">
                <img src="{{ asset('palcomtech-logo.png') }}" alt="Logo PalComTech" class="brand-logo">
                <span>
                    PALCOM AI Scheduler
                    <small>POWERED BY PALCOMTECH</small>
                </span>
            </a>

            <nav class="nav-links" aria-label="Navigasi utama">
                <a href="#masalah">Masalah & Solusi</a>
                <a href="#alur">Cara Kerja</a>
                <a href="#fitur">Fitur</a>
                <a href="#skenario">Skenario AI</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="hero">
            <div class="container hero-grid">
                <div>
                    <span class="eyebrow">AGENTIC AI × SMART SCHEDULING</span>

                    <h1>
                        Instruktur berhalangan?<br>
                        <span>Jadwal tetap berjalan.</span>
                    </h1>

                    <p>
                        PALCOM AI Scheduler membantu tim akademik
                        menganalisis izin instruktur, menemukan kandidat
                        pengganti, serta memeriksa kompetensi, bentrok
                        jadwal, dan kelayakan ruangan.
                    </p>

                    <div class="buttons">
                        <a href="#alur" class="btn btn-yellow">
                            Jelajahi Cara Kerja →
                        </a>
                        <a href="#skenario" class="btn btn-outline">
                            Lihat Skenario Demo
                        </a>
                    </div>
                </div>

                <div class="mockup">
                    <div class="mockup-top">
                        <span class="mockup-title">Analisis Pengganti</span>
                        <span class="tag">ILUSTRASI SISTEM</span>
                    </div>

                    <div class="notice">
                        <strong>Pengajuan izin instruktur</strong>
                        Wahyu berhalangan mengajar pukul
                        13.00–18.00. Sistem mengevaluasi
                        jadwal terdampak.
                    </div>

                    <div class="candidate">
                        <span>
                            <strong>Kanero</strong>
                            <small>Kompetensi sesuai • Jadwal tersedia</small>
                        </span>
                        <span class="state valid">VALID</span>
                    </div>

                    <div class="candidate">
                        <span>
                            <strong>Dina Oktavia</strong>
                            <small>Kompetensi tidak sesuai</small>
                        </span>
                        <span class="state invalid">TIDAK VALID</span>
                    </div>

                    <div class="candidate">
                        <span>
                            <strong>Budi Santoso</strong>
                            <small>Bentrok dengan jadwal lain</small>
                        </span>
                        <span class="state invalid">BENTROK</span>
                    </div>

                    <div class="mockup-foot">
                        Rekomendasi memerlukan persetujuan admin
                    </div>
                </div>
            </div>
        </div>

        <section id="masalah" class="soft">
            <div class="container">
                <div class="section-head">
                    <span class="overline">Masalah yang Diselesaikan</span>
                    <h2>Penjadwalan akademik tidak harus serba manual.</h2>
                    <p>
                        Ketika instruktur izin mendadak,
                        pencarian pengganti membutuhkan pemeriksaan
                        beberapa faktor yang saling berkaitan.
                    </p>
                </div>

                <div class="cards">
                    <article class="card">
                        <span class="card-icon">01</span>
                        <h3>Pencarian pengganti</h3>
                        <p>
                            Admin perlu mengidentifikasi instruktur
                            yang memiliki kompetensi sesuai materi.
                        </p>
                    </article>

                    <article class="card">
                        <span class="card-icon">02</span>
                        <h3>Risiko bentrok jadwal</h3>
                        <p>
                            Ketersediaan pengajar, jadwal kelas,
                            dan penggunaan ruangan harus diperiksa.
                        </p>
                    </article>

                    <article class="card">
                        <span class="card-icon">03</span>
                        <h3>Keputusan & pencatatan</h3>
                        <p>
                            Setiap usulan perlu persetujuan yang jelas
                            serta riwayat perubahan yang bisa ditelusuri.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section id="alur">
            <div class="container">
                <div class="section-head">
                    <span class="overline">Alur Agentic AI</span>
                    <h2>Dari masalah menuju rekomendasi yang dapat ditindaklanjuti.</h2>
                    <p>
                        Menggabungkan evaluasi aturan penjadwalan,
                        bantuan AI, dan kendali keputusan oleh manusia.
                    </p>
                </div>

                <div class="steps">
                    <div class="step">
                        <span class="step-number">01</span>
                        <h3>Terima pengajuan</h3>
                        <p>Instruktur mengajukan izin pada waktu tertentu.</p>
                    </div>

                    <div class="step">
                        <span class="step-number">02</span>
                        <h3>Analisis dampak</h3>
                        <p>Sistem mengidentifikasi kelas yang terdampak.</p>
                    </div>

                    <div class="step">
                        <span class="step-number">03</span>
                        <h3>Evaluasi kandidat</h3>
                        <p>Kompetensi, jadwal, dan ruangan diperiksa.</p>
                    </div>

                    <div class="step">
                        <span class="step-number">04</span>
                        <h3>Rekomendasi AI</h3>
                        <p>AI membantu menyusun prioritas dari kandidat yang valid.</p>
                    </div>

                    <div class="step">
                        <span class="step-number">05</span>
                        <h3>Persetujuan admin</h3>
                        <p>Admin mengambil keputusan sebelum jadwal diperbarui.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="fitur" class="soft">
            <div class="container">
                <div class="section-head">
                    <span class="overline">Kapabilitas Sistem</span>
                    <h2>Dirancang untuk operasional akademik yang lebih terstruktur.</h2>
                </div>

                <div class="cards">
                    <article class="card">
                        <span class="card-icon">AI</span>
                        <h3>AI Scheduling Advisor</h3>
                        <p>
                            Memberikan bantuan analisis dan pemeringkatan
                            calon instruktur pengganti.
                        </p>
                    </article>

                    <article class="card">
                        <span class="card-icon">✓</span>
                        <h3>Validasi berbasis aturan</h3>
                        <p>
                            Memeriksa kompetensi instruktur,
                            konflik jadwal, dan kondisi ruangan.
                        </p>
                    </article>

                    <article class="card">
                        <span class="card-icon">↺</span>
                        <h3>Human-in-the-loop</h3>
                        <p>
                            Penggantian instruktur membutuhkan
                            persetujuan admin, bukan keputusan AI sepihak.
                        </p>
                    </article>

                    <article class="card">
                        <span class="card-icon">!</span>
                        <h3>Fallback deterministik</h3>
                        <p>
                            Sistem dirancang tetap menyediakan evaluasi
                            berbasis aturan ketika layanan AI tidak tersedia.
                        </p>
                    </article>

                    <article class="card">
                        <span class="card-icon">≡</span>
                        <h3>Activity Log</h3>
                        <p>
                            Riwayat analisis, persetujuan, dan
                            perubahan jadwal untuk kebutuhan audit.
                        </p>
                    </article>

                    <article class="card">
                        <span class="card-icon">▦</span>
                        <h3>Dashboard akademik</h3>
                        <p>
                            Informasi jadwal dan aktivitas penjadwalan
                            dalam satu antarmuka pengelolaan.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section id="skenario">
            <div class="container">
                <div class="section-head">
                    <span class="overline">Contoh Demonstrasi</span>
                    <h2>Satu izin instruktur, beberapa keputusan penting.</h2>
                    <p>
                        Berikut contoh skenario untuk menjelaskan
                        logika PALCOM AI Scheduler.
                        Ini adalah ilustrasi, bukan hasil analisis real-time.
                    </p>
                </div>

                <div class="showcase">
                    <div>
                        <span class="showcase-label">SKENARIO ILUSTRATIF</span>
                        <h3>Wahyu izin. Siapa yang paling tepat menggantikan?</h3>
                        <p>
                            Sistem tidak sekadar mencari instruktur
                            yang sedang kosong, tetapi mengevaluasi
                            kesesuaian kompetensi dan seluruh batasan
                            penjadwalan yang relevan.
                        </p>
                        <p>
                            Hasil evaluasi menjadi bahan pertimbangan
                            admin sebelum mengambil keputusan akhir.
                        </p>
                    </div>

                    <ul class="scenario-list">
                        <li>Identifikasi dua kelas yang terdampak.</li>
                        <li>Periksa kompetensi setiap kandidat.</li>
                        <li>Diskualifikasi kandidat dengan jadwal bentrok.</li>
                        <li>Periksa kapasitas dan status ruangan.</li>
                        <li>Susun rekomendasi kandidat yang valid.</li>
                        <li>Teruskan ke admin untuk persetujuan.</li>
                        <li>Catat keputusan dan pembaruan jadwal.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="closing">
            <div class="container">
                <h2>Keputusan lebih terstruktur. Penjadwalan lebih terkendali.</h2>
                <p>
                    PALCOM AI Scheduler merupakan proyek pengembangan
                    sistem penjadwalan akademik berbasis Agentic AI
                    untuk mendukung operasional PalComTech.
                </p>
                <p style="margin-top:18px;color:#FFC928;font-weight:700">
                    Demonstrasi interaktif dan pengujian akhir sedang disiapkan.
                </p>
            </div>
        </section>
    </main>

    <footer>
        <div class="container footer-inner">
            <span>© {{ date('Y') }} PALCOM AI Scheduler</span>
            <span>Proyek Agentic AI • PalComTech</span>
        </div>
    </footer>
</body>
</html>