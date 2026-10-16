<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f6f8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sentra — Pemantauan keamanan</title>
    <link rel="stylesheet" href="{{ asset('assets/security.css') }}?v=minimal-20261016">
</head>
<body>
    <aside>
        <a class="brand" href="#live"><span class="brand-icon">S</span>Sentra</a>
        <p class="workspace-name">Technolife<small>Pemantauan keamanan</small></p>
        <nav aria-label="Menu utama">
            <button data-page="live" class="active"><span aria-hidden="true">▦</span>Kamera</button>
            <button data-page="recordings"><span aria-hidden="true">◷</span>Rekaman</button>
            <button data-page="incidents"><span aria-hidden="true">⚑</span>Kejadian <span id="incident-count" class="nav-count">0</span></button>
            <div class="nav-divider" aria-hidden="true"></div>
            <button data-page="backup"><span aria-hidden="true">▤</span>Backup</button>
            <button data-page="settings"><span aria-hidden="true">⚙</span>Pengaturan</button>
        </nav>
        <div class="profile"><strong>{{ auth()->user()->name }}</strong><small>Administrator</small></div>
    </aside>
    <div class="app">
        <header>
            <span class="header-context">Workspace keamanan<span id="breadcrumb-title" class="sr-only"></span></span>
            <div class="header-actions"><time id="clock"></time><span class="demo-pill">Mode demo</span><form method="post" action="{{ route('logout') }}">@csrf<button class="text-button" type="submit">Keluar</button></form></div>
        </header>
        <main>
            <div class="page-heading"><div><h1 id="page-title">Kamera</h1><p id="page-subtitle">Pilih kamera untuk melihat rekaman atau menandai kejadian.</p></div></div>
            <div class="demo-notice"><span>Video contoh · bukan siaran langsung.</span><button id="notification" class="text-button">Status koneksi</button></div>

            <section id="live-page" class="page" aria-label="Pemantauan kamera">
                <div class="camera-toolbar">
                    <div class="tabs" id="camera-tabs"><button data-filter="all" class="selected">Semua ({{ count($boot['cameras']) }})</button><button data-filter="available">Tersedia ({{ count(array_filter($boot['cameras'], fn ($c) => $c['online'])) }})</button><button data-filter="offline">Offline ({{ count(array_filter($boot['cameras'], fn ($c) => ! $c['online'])) }})</button></div>
                    <label class="search"><span class="sr-only">Cari kamera</span><input id="camera-search" placeholder="Cari kamera…" aria-label="Cari kamera"></label>
                    <button class="primary" id="quick-incident">Catat kejadian</button>
                </div>
                <div class="camera-view-options"><span>Klik kamera untuk membuka pemutar.</span><div class="view-switch" aria-label="Tampilan kamera"><button class="selected" id="grid-view" aria-label="Tampilan kotak">Kotak</button><button id="list-view" aria-label="Tampilan daftar">Daftar</button></div></div>
                <div id="camera-grid" class="camera-grid"></div>
            </section>

            <section id="recordings-page" class="page" hidden>
                <div class="panel">
                    <div class="filters"><label>Kamera<select id="record-camera"><option value="all">Semua kamera</option></select></label><label>Tanggal<input id="record-date" type="date"></label><button class="secondary" id="reset-records">Reset</button></div>
                    <div class="table-wrap" tabindex="0" role="region" aria-label="Daftar rekaman, dapat digeser horizontal"><table><thead><tr><th>Kamera</th><th>Tanggal</th><th>Waktu rekam</th><th>Durasi</th><th>Catatan</th><th><span class="sr-only">Tindakan</span></th></tr></thead><tbody id="record-rows"></tbody></table></div>
                    <p class="help-text">Waktu MEVA mengikuti sumber; zona waktunya tidak diketahui. Rekaman lainnya memakai waktu contoh WIB.</p>
                </div>
            </section>

            <section id="incidents-page" class="page" hidden>
                <div class="panel"><div class="section-top"><h2>Daftar kejadian</h2><button id="new-incident" class="primary">Catat kejadian</button></div><div id="incident-list"></div></div>
            </section>

            <section id="backup-page" class="page" hidden>
                <p class="page-note">Backup belum terhubung. Pengaturan di bawah hanya menyimpan rencana kebijakan.</p>
                <div class="split-panels">
                    <section class="panel"><h2>Kebijakan penyimpanan</h2><form id="policy-form"><label>Simpan rekaman utama<select name="retention"><option value="7">7 hari</option><option value="14">14 hari</option><option value="30" selected>30 hari</option><option value="60">60 hari</option><option value="90">90 hari</option></select></label><label>Simpan salinan backup<select name="backupRetention"><option value="30">30 hari</option><option value="60">60 hari</option><option value="90" selected>90 hari</option></select></label><label>Tujuan backup<input name="destination" value="NAS sekunder" required maxlength="100"></label><label>Jadwal harian (WIB)<input name="schedule" type="time" value="02:00" required></label><button class="primary">Simpan kebijakan</button></form></section>
                    <section class="panel"><h2>Uji alur backup</h2><p class="help-text">Simulasi ini tidak menyalin atau melindungi file video.</p><div id="backup-list"></div><button id="simulate-backup" class="secondary">Jalankan simulasi</button><p id="backup-result" class="help-text" aria-live="polite"></p></section>
                </div>
            </section>

            <section id="settings-page" class="page" hidden>
                <div class="panel settings-panel"><h2>Koneksi CCTV</h2><p class="help-text">Server belum terhubung. Pengaturan ini menyimpan rencana koneksi dan belum mengaktifkan streaming.</p><form id="settings-form"><label>Alamat server Frigate (HTTPS)<input name="server" type="url" placeholder="https://cctv.perusahaan.id" pattern="https://.*" required></label><label>Nama lokasi<input name="location" placeholder="Kantor pusat" maxlength="80"></label><button class="primary">Simpan pengaturan</button></form><details class="connection-help"><summary>Kebutuhan koneksi</summary><p>Kamera atau NVR, server Frigate, akses HTTPS, dan penyimpanan backup terpisah.</p><a href="https://docs.frigate.video/" target="_blank" rel="noopener">Dokumentasi Frigate ↗</a><p><button id="add-camera" class="secondary">Informasi penambahan kamera</button></p></details></div>
            </section>
        </main>
    </div>
    <dialog id="modal" aria-labelledby="modal-title"><div class="modal-top"><h2 id="modal-title"></h2><button id="close-modal" class="icon-btn" aria-label="Tutup dialog">✕</button></div><div id="modal-body"></div></dialog>
    <div id="toast" role="status"></div>
    <script>window.SENTRA_BOOT = {{ Illuminate\Support\Js::from($boot) }};</script>
    <script src="{{ asset('assets/app.js') }}?v=minimal-20261016"></script>
</body>
</html>
