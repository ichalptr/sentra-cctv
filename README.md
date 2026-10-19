# Sentra — percobaan Laravel

Migrasi terpisah dari versi HTML. Folder asli `C:\laragon\www\sentra-cctv` tidak diubah.

## Menjalankan di Laragon dan VS Code

1. Buka folder `C:\laragon\www\sentra-cctv-laravel` di VS Code.
2. Melalui terminal Laragon pada folder tersebut, jalankan:

```powershell
php artisan serve --host=127.0.0.1 --port=8010
```

3. Buka `http://127.0.0.1:8010`.
4. Saat pertama kali dibuka, buat akun administrator sendiri. Tidak ada email/password bawaan. Setup hanya diizinkan melalui localhost dan ditutup setelah akun pertama dibuat.

VS Code juga menyediakan task **Sentra Laravel: jalankan**. Tidak perlu npm build: frontend menggunakan Blade dengan aset CSS/JavaScript yang sudah ada. Composer dan dependensi PHP sudah dipasang.

Cache konfigurasi, route, dan view telah diaktifkan untuk mempercepat percobaan. Setelah mengubah `.env`, konfigurasi, atau route, jalankan `php artisan optimize:clear`, kemudian `php artisan optimize` bila ingin mengaktifkan cache kembali. Untuk menjalankan automated test setelah cache aktif, jalankan `php artisan config:clear` terlebih dahulu agar database pengujian di memori digunakan.

Untuk Apache Laragon, virtual host harus mengarah ke **`C:\laragon\www\sentra-cctv-laravel\public`**, bukan root proyek. Jangan menyajikan root Laravel menggunakan web server generik karena `.env`, database, dan source harus berada di luar document root. Root `.htaccess` memblokir akses source pada Apache dengan mod_rewrite; untuk tes sementara, cara yang disarankan tetap `artisan serve` di atas.

## Fitur yang sudah dimigrasikan

- Laravel 13, Blade, dan JavaScript pemutar yang digunakan sebelumnya.
- Setup administrator, login, logout, session, CSRF, dan pembatasan percobaan login.
- Data kamera di database.
- Catatan insiden, posisi klip, kebijakan retensi, dan rencana koneksi server tersimpan di database.
- Video dilayani melalui route yang memerlukan login dan mendukung byte-range untuk seek.
- Validasi kamera, record, timestamp, URL, dan kebijakan penyimpanan di server.

SQLite digunakan untuk percobaan mandiri pada `database/database.sqlite`. `.env` memiliki kunci aplikasi unik; jangan membagikannya atau memasukkannya ke Git. Data SQLite juga tidak masuk Git. Untuk memakai MySQL, buat database baru khusus percobaan, atur koneksi di `.env`, lalu jalankan `php artisan migrate`. Jangan arahkan ke database proyek lain.

## Uji

```powershell
php artisan test
```

Pengujian memakai database SQLite di memori dan tidak mengubah database aplikasi. Mencakup login, setup sekali, penolakan akses tamu, penyimpanan insiden/pengaturan, validasi, dan range video.

## Rollback

1. Hentikan server Laravel dengan **Ctrl+C** pada terminal yang menjalankan `artisan serve`.
2. Buka kembali folder **`C:\laragon\www\sentra-cctv`** di VS Code.
3. Jalankan web server Laragon, lalu buka **`http://localhost/sentra-cctv/`**.

Tidak perlu menghapus, memindahkan, atau mengganti file versi lama. Proyek Laravel tetap tersedia jika ingin melanjutkan percobaan. Simpan salinan `database/database.sqlite` saat server berhenti jika ada data percobaan yang ingin dipertahankan. Jangan memakai `migrate:fresh` untuk rollback karena perintah itu menghapus tabel.

## Batas percobaan

Video menggunakan stock footage dan satu klip dataset CCTV MEVA untuk uji playback, belum terhubung ke Frigate. Backup file MP4 kini menyalin file nyata, memverifikasi SHA-256, menyediakan playback salinan, dan menguji pemulihan. Folder uji lokal belum melindungi dari kerusakan disk; gunakan media terpisah untuk operasional. Status salinan dan penjadwal tersedia di menu Backup. Aplikasi masih memakai satu akun administrator; manajemen peran, reset password, dan audit log belum tersedia.

Data localStorage versi HTML tidak otomatis masuk database Laravel. Data dan halaman versi lama tetap utuh untuk perbandingan.

## Pilot playback CCTV MEVA

Satu kamera tambahan: **Terminal · MEVA G331**, rekaman sumber **7 Maret 2018, 16:50:00–16:55:00**, sekitar 5 menit / 68 MiB / 1080p. Zona waktu sumber tidak diasumsikan WIB. Tidak mengganti tiga rekaman lama maupun mengubah akun dan catatan yang sudah ada.

Uji: buka Riwayat rekaman, pilih Terminal · MEVA G331 dan tanggal 2018-03-07 (atau kosongkan filter tanggal). Putar, geser ke menit 03:00, tandai insiden, lalu buka ulang posisi tersebut dari Catatan insiden. Tanggal penanda dari klip mengikuti tanggal arsip sumber; catatan dibuat saat pengujian, bukan kejadian perusahaan sebenarnya.

Untuk kembali tanpa klip tambahan, jalankan di folder aplikasi:

    php artisan sentra:meva off

Aktifkan kembali dengan:

    php artisan sentra:meva on

Muat ulang browser setelah mengganti mode. Toggle menyembunyikan kamera/arsip dan menutup route medianya. Catatan insiden tetap ada; replay klip pilot tersedia lagi setelah diaktifkan. Jangan gunakan migrate:fresh atau mengganti database untuk rollback.

Sumber: [MEVA](https://mevadata.org/), Kitware Inc. / IARPA, [CC BY 4.0](https://mevadata.org/resources/MEVA-data-license.txt). Klip berisi skenario dataset yang diperagakan, bukan insiden perusahaan. [AVI asli](https://mevadata-public-01.s3.amazonaws.com/drops-123-r13/2018-03-07/16/2018-03-07.16-50-00.16-55-00.bus.G331.r13.avi). Perubahan teknis: video AVI ke MP4 H.264 30 fps dengan FFmpeg; timestamp dinormalisasi untuk playback browser. Kredit, lisensi, dan tautan asli tersedia di pemutar dan ekspor metadata.

Pilot hanya memvalidasi arsip, seek, dan penanda. Kriteria perusahaan yang masih harus diuji dengan sumber CCTV sebenarnya adalah akses real-time dan backup rekaman sesuai retensi. Uji motion detection telah dikeluarkan dari pemutar. File asli tidak dihapus oleh Sentra. Retensi hanya menghapus salinan backup milik Sentra yang tidak memiliki referensi catatan kejadian.


## Pengaturan kamera dan backup

1. Buka **Pengaturan**. Jika akses perangkat belum tersedia, lewati langkah kamera.
2. Untuk tayangan perusahaan, gunakan halaman player WebRTC/HLS dari gateway. IP kamera atau URL RTSP saja belum dapat diputar oleh browser; gateway dan perekaman NVR tetap harus disiapkan oleh teknisi. Form ini tidak memasang atau menghubungkan Frigate secara otomatis.
3. Pilih **Video uji bawaan**, atau folder berisi segmen MP4 yang sudah selesai ditulis untuk satu kamera. File yang berubah dalam 60 detik terakhir ditunda. Waktu arsip folder mengikuti waktu perubahan file, bukan waktu kejadian kamera.
4. Pilih tujuan lokal untuk uji, atau folder disk/NAS yang sudah terpasang. Periksa izin akses pada komputer server, bukan komputer pengguna browser. Media fisik terpisah perlu dipastikan oleh operator.
5. Simpan konfigurasi, lalu buka **Backup → Backup sekarang**. Salinan dapat diputar dan diuji pemulihannya. Maksimal 10 salinan baru diproses per putaran; putaran berikutnya melanjutkan file tertunda. Gunakan folder maksimal 500 MP4 per kamera/periode.
6. Untuk otomatisasi, aktifkan opsi otomatis dan pilih interval 5/15/60 menit atau harian. Laravel `schedule:run` harus dipanggil setiap menit. Penjadwal Windows instalasi Laragon ini bernama `SentraCCTV-Backup`, berjalan tanpa jendela saat pengguna login. Status aktif ditentukan dari heartbeat terbaru, bukan sekadar konfigurasi tersimpan.

Retensi berlaku untuk salinan backup, dihitung dari tanggal salinan pertama. Salinan dengan referensi catatan kejadian tidak dihapus otomatis. Pengaturan retensi rekaman asli tetap berada di NVR/gateway. Kegagalan akses tujuan atau pemeriksaan hash tidak boleh dianggap backup berhasil. Uji pemulihan membuat salinan sementara, mencocokkan hash, lalu membersihkan file uji.
