<x-layouts::auth.schedora-login>
<div class="shell">
  <aside class="brand-side" aria-label="Tentang SCHEDORA AI">
    <div class="brand-grid"></div>
    <header class="identity">
      <img src="{{ asset('schedora/schedora-logo.png') }}" alt="Logo SCHEDORA AI">
      <div><div class="identity-name">SCHEDORA <span>AI</span></div><div class="identity-kicker">INTELLIGENT SCHEDULING</div></div>
    </header>
    <section class="brand-middle">
      <div class="eyebrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 2 1.9 6.1L20 10l-6.1 1.9L12 18l-1.9-6.1L4 10l6.1-1.9L12 2z"/><path d="m19 17 .7 2.3L22 20l-2.3.7L19 23l-.7-2.3L16 20l2.3-.7L19 17z"/></svg>WORK SMARTER, SCHEDULE BETTER</div>
      <h1>Jadwal lebih rapi.<br><span>Keputusan lebih cepat.</span></h1>
      <p class="intro">Satu ruang kerja untuk merencanakan kelas, menemukan opsi jadwal, dan meninjau persetujuan secara terstruktur.</p>
      <div class="mini-features"><span>Penjadwalan cerdas</span><span>Rekomendasi terukur</span><span>Approval terkontrol</span></div>
      <div class="mock-board" aria-label="Alur kerja SCHEDORA AI dalam tiga langkah">
        <div class="mock-head"><strong>Alur Kerja Cerdas</strong><small><b></b>3 langkah sederhana</small></div>
        <div class="workflow">
          <div class="workflow-step"><div class="workflow-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/><path d="M8 15h3"/></svg></div><strong>Rencanakan</strong><span>Kebutuhan kelas</span></div>
          <div class="workflow-arrow" aria-hidden="true">→</div>
          <div class="workflow-step"><div class="workflow-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 14 8l6 2-6 2-2 6-2-6-6-2 6-2 2-6Z"/><path d="M19 17v5m-2.5-2.5h5"/></svg></div><strong>Pilih Opsi</strong><span>Jadwal terukur</span></div>
          <div class="workflow-arrow" aria-hidden="true">→</div>
          <div class="workflow-step"><div class="workflow-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 20 7v5c0 5-3 8-8 9-5-1-8-4-8-9V7l8-4Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg></div><strong>BAAK Tinjau</strong><span>Keputusan akhir</span></div>
        </div>
        <p class="workflow-note"><span class="workflow-note-dot"></span> Sistem membantu merencanakan. Persetujuan tetap oleh BAAK.</p>
      </div>
    </section>
    <footer class="brand-foot"><span>© 2026 SCHEDORA AI</span><span>Built for smarter academic operations</span></footer>
  </aside>
  <main class="login-side">
    <div class="top-right"><i></i> Akses pengguna terdaftar</div>
    <section class="login-content" aria-label="Form login">
      <div class="mobile-brand"><img src="{{ asset('schedora/schedora-logo.png') }}" alt="Logo resmi SCHEDORA AI"><div class="wordmark">SCHEDORA <span>AI</span></div><p>SMART ACADEMIC SCHEDULING</p></div>
      <div class="greeting">WELCOME BACK</div>
      <h2>Selamat datang kembali</h2>
      <p class="sub">Masuk ke akun SCHEDORA AI untuk mengelola jadwal dan aktivitas akademik Anda.</p>
      <x-auth-session-status class="auth-feedback success" :status="session('status')" />
      @error('email')
        <div class="auth-feedback" role="alert">{{ $message }}</div>
      @enderror
      <div class="schedora-passkey">
          <x-passkey-verify label="Masuk dengan passkey" loading-label="Memverifikasi..." separator="Atau lanjut menggunakan email" />
      </div>
      <form id="login-form" method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="field"><label for="email">Alamat email</label><div class="inputwrap"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.7" y="4.5" width="18.6" height="15" rx="3"/><path d="m4.7 7.7 7.3 5.4 7.3-5.4"/></svg><input id="email" name="email" type="email" placeholder="Masukkan alamat email" autocomplete="email" value="{{ old('email') }}" required autofocus aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"></div></div>
        <div class="field"><label for="password">Password</label><div class="inputwrap"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4.8" y="10" width="14.4" height="11" rx="2.1"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><input id="password" name="password" type="password" placeholder="Masukkan password" autocomplete="current-password" required><button class="reveal" type="button" aria-label="Tampilkan password" data-password-toggle aria-controls="password" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="2.8"/></svg></button></div></div>
        <div class="row-remember"><label class="checklabel"><input type="checkbox" name="remember" value="1" @checked(old('remember'))>Ingat saya</label>@if (Route::has('password.request'))
        <a href="{{ route('password.request') }}">Lupa password?</a>
        @endif</div>
        <button type="submit" class="submit" data-test="login-button">Masuk ke SCHEDORA AI <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16m-7-7 7 7-7 7"/></svg></button>
      </form>
      <div class="security"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 20 6v6c0 5.3-3.4 8.7-8 10-4.6-1.3-8-4.7-8-10V6l8-4Z"/><path d="m9 12 2 2 4-4"/></svg> Akses aman untuk pengguna terdaftar</div>
      <div class="rule"></div>
      <p class="note">Belum memiliki akun? <strong>Hubungi administrator sistem.</strong><br>Tidak tersedia pendaftaran akun publik.</p>
    </section>
    <footer class="mobile-foot">© 2026 SCHEDORA AI</footer>
  </main>
</div>
</x-layouts::auth.schedora-login>
