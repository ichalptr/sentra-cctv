<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f6f8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sentra — Pemantauan keamanan</title>
    <link rel="stylesheet" href="{{ asset('assets/security.css') }}?v=sidebar-20261019-1">
</head>
<body>
    <aside>
        <p class="workspace-name">Technolife</p>
        <nav aria-label="Menu utama">
            <button data-page="live" class="active">Kamera</button>
            <button data-page="recordings">Rekaman</button>
            <button data-page="incidents">Kejadian <span id="incident-count" class="nav-count">0</span></button>
            <button data-page="backup">Backup</button>
            <button data-page="settings">Pengaturan</button>
        </nav>
        <div class="profile"><strong>{{ auth()->user()->name }}</strong><small>Administrator</small></div>
    </aside>
    <div class="app">
        <header>
            <span class="header-context">Workspace keamanan<span id="breadcrumb-title" class="sr-only"></span></span>
            <div class="header-actions"><time id="clock"></time><form method="post" action="{{ route('logout') }}">@csrf<button class="text-button" type="submit">Keluar</button></form></div>
        </header>
        <main>
            <div class="page-heading"><div><h1 id="page-title">Kamera</h1><p id="page-subtitle">Pilih kamera untuk melihat rekaman atau menandai kejadian.</p></div></div>
            <div class="demo-notice"><span>{{ ($boot['cameraSetup']['enabled'] ?? false) ? 'Player kamera dikonfigurasi · periksa tayangan dan backup secara terpisah.' : ($boot['publicCamera'] ? 'Uji live publik tersedia · kamera perusahaan belum terhubung.' : 'Video contoh · bukan siaran langsung.') }}</span><button id="notification" class="text-button">Status koneksi</button></div>

            <section id="live-page" class="page" aria-label="Pemantauan kamera">
                <div class="camera-toolbar">
                    <div class="tabs" id="camera-tabs"><button data-filter="all" class="selected">Semua ({{ count($boot['cameras']) + ($boot['publicCamera'] ? 1 : 0) + (($boot['cameraSetup']['enabled'] ?? false) ? 1 : 0) }})</button>@if($boot['cameraSetup']['enabled'] ?? false)<button data-filter="gateway">Live perusahaan (1)</button>@endif
                    @if($boot['publicCamera'])<button data-filter="public">Live publik (1)</button>@endif<button data-filter="available">Rekaman ({{ count(array_filter($boot['cameras'], fn ($c) => $c['online'])) }})</button><button data-filter="offline">Offline ({{ count(array_filter($boot['cameras'], fn ($c) => ! $c['online'])) }})</button></div>
                    <label class="search"><span class="sr-only">Cari kamera</span><input id="camera-search" placeholder="Cari kamera…" aria-label="Cari kamera"></label>
                    <button class="primary" id="quick-incident">Catat kejadian</button>
                </div>
                <div class="camera-view-options"><span>Live tampil otomatis. Klik kamera untuk memperbesar.</span><div class="view-switch" aria-label="Tampilan kamera"><button class="selected" id="grid-view" aria-label="Tampilan kotak">Kotak</button><button id="list-view" aria-label="Tampilan daftar">Daftar</button></div></div>
                <div id="camera-grid" class="camera-grid"></div>
            </section>

            <section id="recordings-page" class="page" hidden>
                <div class="panel">
                    <div class="filters"><label>Kamera<select id="record-camera"><option value="all">Semua kamera</option></select></label><label>Tanggal<input id="record-date" type="date"></label><button class="secondary" id="reset-records">Reset</button></div>
                    <div class="table-wrap" tabindex="0" role="region" aria-label="Daftar rekaman, dapat digeser horizontal"><table><thead><tr><th>Kamera</th><th>Tanggal</th><th>Waktu rekam</th><th>Durasi</th><th>Catatan</th><th><span class="sr-only">Tindakan</span></th></tr></thead><tbody id="record-rows"></tbody></table></div>
                    <p class="help-text">Buka rekaman → Uji deteksi gerakan untuk mencoba analisis lokal. Waktu MEVA mengikuti sumber; zona waktunya tidak diketahui. Rekaman lainnya memakai waktu contoh WIB.</p>
                </div>
            </section>

            <section id="incidents-page" class="page" hidden>
                <div class="panel"><div class="section-top"><h2>Daftar kejadian</h2><button id="new-incident" class="primary">Catat kejadian</button></div><div id="incident-list"></div></div>
            </section>

            <section id="backup-page" class="page" hidden>
                <div class="panel"><div class="section-top"><div><h2>Backup rekaman</h2><p class="help-text" id="storage-summary">Memuat status penyimpanan…</p></div><button class="secondary" data-page="settings">Atur penyimpanan</button></div>
                <div class="storage-actions"><button class="primary" id="run-backup">Backup sekarang</button><button class="secondary" id="refresh-backup">Perbarui status</button></div>
                <p id="backup-result" class="form-note" role="status"></p><p id="scheduler-state" class="help-text"></p>
                <div id="archive-list"></div>
                <p class="help-text">File yang dicatat sebagai kejadian dikecualikan dari penghapusan backup otomatis. File asli di kamera/NVR/folder sumber tidak dihapus oleh Sentra.</p></div>
            </section>

            <section id="settings-page" class="page" hidden>
                <div class="setup-intro"><h2>Siapkan Sentra</h2><p>Mulai dari video uji, lalu ganti sumber dan tujuan ketika perangkat perusahaan siap.</p></div>
                <section class="panel setup-step"><h2><span class="step-number">1</span> Hubungkan kamera</h2>
                    <p class="help-text">Belum memiliki akses kamera? Lewati langkah ini dan gunakan video uji pada langkah 2.</p>
                    <form id="camera-setup-form"><label>Sumber tayangan<select name="enabled"><option value="0">Belum ada akses kamera perusahaan</option><option value="1">Sudah ada halaman player dari gateway</option></select></label>
                    <div id="camera-address-fields" hidden><div class="setup-fields"><label>Nama kamera<input name="name" value="Kamera 1" maxlength="80" required></label><label>Lokasi<input name="location" maxlength="80" placeholder="Contoh: Pintu masuk"></label></div>
                    <label>Alamat halaman player<input name="player_url" type="url" placeholder="http://192.168.1.20:8889/kamera1" maxlength="500"></label>
                    <p class="help-text">Gunakan halaman player WebRTC/HLS dari gateway, bukan IP kamera saja, RTSP, atau file .m3u8. Browser harus bisa mengakses alamat tersebut. Jangan masukkan password/token ke URL.</p><button type="button" class="secondary" id="preview-gateway">Uji tayangan</button></div>
                    <button class="primary">Simpan sumber kamera</button><p id="camera-setup-result" class="help-text" role="status"></p></form>
                    <details class="connection-help"><summary>Kamera saya memakai RTSP atau DVR/NVR</summary><p>Kamera/DVR → gateway video (misalnya Frigate/MediaMTX) → halaman player → Sentra. Teknisi menyiapkan koneksi dan login di gateway terlebih dahulu. Mengisi halaman ini tidak memasang gateway atau merekam siaran secara otomatis.</p><a href="https://mediamtx.org/docs/read/web-browsers" target="_blank" rel="noopener">Contoh halaman player gateway ↗</a></details>
                </section>
                <form id="storage-setup-form">
                    <section class="panel setup-step"><h2><span class="step-number">2</span> Pilih folder rekaman</h2>
                        <label>Sumber file<select name="source"><option value="demo">Video uji bawaan — langsung bisa dicoba</option><option value="folder">Folder MP4 dari kamera/NVR/gateway</option></select></label>
                        <div id="source-folder-fields" hidden><label>Folder rekaman di komputer server<input name="source_path" placeholder="D:\Rekaman\Kamera1" maxlength="500"></label><label>Nama kamera untuk arsip<input name="camera_name" value="Kamera 1" required maxlength="80"></label><p class="help-text">Pilih folder untuk satu kamera. Folder jaringan harus sudah dipasang dan dapat dibaca komputer Laragon. Gunakan segmen MP4 yang sudah ditutup oleh perekam. File yang berubah dalam 60 detik terakhir ditunda. Waktu yang ditampilkan mengikuti waktu perubahan file, bukan waktu kejadian kamera.</p></div>
                        <p class="help-text">Perekaman dilakukan kamera/NVR/gateway. Sentra mengambil file yang tersedia, bukan merekam dari halaman player. MP4 H.264 paling mudah diputar browser.</p>
                    </section>
                    <section class="panel setup-step"><h2><span class="step-number">3</span> Tentukan backup</h2>
                        <label>Tujuan salinan<select name="destination"><option value="test">Folder uji lokal — untuk mencoba alur</option><option value="folder">Folder disk lain / NAS yang sudah terpasang</option></select></label>
                        <label id="destination-folder-field" hidden>Folder tujuan di komputer server<input name="destination_path" placeholder="E:\BackupCCTV" maxlength="500"></label>
                        <p class="form-note">Folder uji lokal adalah salinan pada komputer yang sama dan belum melindungi dari kerusakan disk. Untuk operasional, pilih media penyimpanan terpisah. Sentra tidak dapat memastikan dua folder berada pada perangkat fisik yang berbeda.</p>
                        <div class="setup-fields"><label>Simpan backup selama<select name="retention"><option value="7">7 hari</option><option value="14">14 hari</option><option value="30" selected>30 hari</option><option value="60">60 hari</option><option value="90">90 hari</option></select></label><label>Frekuensi backup<select name="interval"><option value="5">Setiap 5 menit</option><option value="15" selected>Setiap 15 menit</option><option value="60">Setiap jam</option><option value="1440">Sekali sehari</option></select></label><label id="daily-schedule-field" hidden>Jadwal harian (WIB)<input name="schedule" type="time" value="02:00" required></label></div>
                        <label class="checkbox-label"><input name="automatic" type="checkbox"> Jalankan backup otomatis sesuai jadwal</label>
                        <p class="help-text">Jadwal hanya bekerja saat komputer menyala, folder tersedia, dan penjadwal Sentra aktif. Retensi hanya menghapus salinan milik Sentra yang tidak memiliki catatan kejadian.</p>
                        <button class="primary">Periksa folder &amp; simpan</button><p id="storage-setup-result" class="help-text" role="status"></p>
                    </section>
                </form>
            </section>
        </main>
    </div>
    <dialog id="modal" aria-labelledby="modal-title"><div class="modal-top"><h2 id="modal-title"></h2><button id="close-modal" class="icon-btn" aria-label="Tutup dialog">✕</button></div><div id="modal-body"></div></dialog>
    <div id="toast" role="status"></div>
    <script>window.SENTRA_BOOT = {{ Illuminate\Support\Js::from($boot) }};</script>
    <script type="module" src="{{ asset('assets/app.js') }}?v=sidebar-20261019-1"></script>
</body>
</html>
