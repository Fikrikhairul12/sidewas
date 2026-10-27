**Pedoman R7 untuk pengelola SIDEWAS: pemeriksaan, rekonsiliasi, aktivasi, dan pemulihan**

Alur revisi SNP/DJSN tetap nonaktif sampai pemeriksaan data dan penerimaan pada lingkungan yang akan diaktifkan lulus. Lulus pengujian kode tidak menyatakan seluruh data lama sudah terbukti. Dokumen ini tidak menjalankan deployment atau mengubah production.

1. Pasang kode R7 dengan konfigurasi yang sedang berjalan.

   ```dotenv
   SIDEWAS_REVIU_PUTARAN_ENABLED=true
   SIDEWAS_ALUR_REVISI_ENABLED=false
   ```

   R7 tidak menambah migrasi baru. Deploy cPanel tetap menjalankan migrasi yang belum tercatat, membersihkan cache, dan membangun cache kembali. Jangan mengubah atau menghapus riwayat migrasi di database utama.

   Pastikan konfigurasi yang benar-benar dibaca aplikasi melalui `php artisan config:show sidewas`. `bootstrap/app.php` memilih `.env` pada folder induk aplikasi jika file tersebut tersedia. Mengubah `.env` yang tidak dipakai tidak mengubah konfigurasi web. Perintah konfigurasi di atas hanya menampilkan flag, bukan password database.

2. Periksa data dan siapkan bukti sebelum memilih waktu maintenance.

   Dari direktori aplikasi:

   ```sh
   php artisan workflow:check-revisi --json
   php artisan workflow:check-revisi --module=snp --record=123 --json
   php artisan workflow:check-revisi --module=djsn --record=456 --json
   ```

   Ganti contoh ID dengan ID numerik pada `tb_record`, bukan nomor surat. Pemeriksaan ini tidak membuat putaran, tanggal, salinan, atau keputusan. Exit code 1 berarti ada penghalang, bukan berarti database sudah diubah. `--output=/lokasi/privat/nama-baru.json` menyimpan laporan; file yang sudah ada tidak ditimpa. Simpan laporan di luar `public_html` dan `storage/app/public`.

   Pemeriksaan `--with-main` juga memeriksa keberadaan master unit/komite dan petugas aktif sesuai kewenangan lama. Pemeriksaan hanya mengambil ID/status akun dan hubungan role; tidak mencetak email atau password. Database akun tidak perlu dikirim kepada pengembang.

   ```sh
   php artisan workflow:check-revisi --with-main --json
   ```

   Siapkan bukti penerbitan pertama untuk tanggal awal, keputusan reviewer untuk tenggat TL, bukti unit pengisi, serta arsip bahan pada waktu kompilasi. Tanggal surat, waktu pembuatan sumber, atau keanggotaan unit pengguna saat ini tidak menjadi pengganti bukti tersebut.

   Pemeriksaan juga memeriksa keberadaan dokumen dan gambar yang masih dirujuk sumber lama, serta hash berkas salinan resmi. Temuan pada salinan storage lokal harus dibandingkan dengan storage server terbaru. Jangan menghapus referensi atau membuat berkas pengganti kosong agar pemeriksaan lulus. Catatan pada slot kompilasi yang masih kosong ditampilkan sebagai peringatan, karena slot tersebut belum menghasilkan bahan resmi.

3. Cadangkan sebelum penerapan rekonsiliasi atau aktivasi.

   Pilih waktu tanpa pengisian berjalan. Hentikan pekerjaan latar belakang yang menulis data terkait dan aktifkan maintenance:

   ```sh
   php artisan down --retry=15
   ```

   Jika Cron hosting hanya menjalankan `artisan schedule:run`, periksa definisi tugasnya sebelum mengubah Cron. Tiga tugas yang saat ini ada di `routes/console.php` tidak menggunakan `evenInMaintenanceMode`, sehingga Laravel tidak memulai tugas tersebut selama maintenance; Cron scheduler dapat dipertahankan. Maintenance tidak membatalkan pekerjaan yang sudah berjalan. Tunggu pekerjaan tersebut selesai sebelum mengambil cadangan, dan hentikan terpisah bila ada worker atau Cron lain yang langsung menulis data.

   Buat satu paket cadangan dengan label waktu dan commit yang sama:

   - Ekspor database utama dan seluruh database modul yang dipakai aplikasi. Database utama yang berisi akun disimpan sendiri oleh pengelola hosting, tidak perlu dibagikan dalam chat.
   - Salin seluruh `storage/app/public` dan `storage/app/private`. Salinan kompilasi, gambar editor, serta bukti rekonsiliasi ada di penyimpanan ini.
   - Simpan kode/rilis sebelum perubahan, `composer.lock`, `public/build`, konfigurasi `.env` yang dipakai, dan commit Git yang sedang terpasang.
   - Simpan manifest berisi nama database, jumlah tabel/baris, ukuran berkas, SHA-256 berkas cadangan, commit, dan waktu maintenance. Letakkan cadangan di luar direktori web.

   cPanel phpMyAdmin dapat digunakan untuk ekspor SQL dan File Manager untuk arsip berkas. Jika memakai `mysqldump`, gunakan transaksi yang konsisten, sertakan trigger/routine/event yang dipakai, serta pertahankan pengaturan zona waktu dari dump. Jangan mengedit waktu dalam SQL. Bandingkan hasil restore dengan zona waktu sesi MySQL yang sama, misalnya `+00:00`.

   Uji pemulihan terlebih dahulu pada database kosong dengan nama berbeda dan direktori aplikasi terpisah. Periksa jumlah dan hash nilai sumber, waktu, identitas, dokumen, gambar, serta kemampuan aplikasi membaca hasilnya. Jangan memakai `migrate:fresh` atau `migrate:rollback` sebagai langkah pemulihan production.

4. Lengkapi pemetaan deterministik pada salinan terlebih dahulu.

   Saat flag revisi masih `false`:

   ```sh
   php artisan workflow:backfill-putaran
   php artisan workflow:backfill-tahap
   php artisan workflow:check-revisi --with-main --json
   ```

   Backfill mempertahankan isi sumber dan menandai ketidakjelasan. Jalankan urutan yang sama pada production hanya setelah paket cadangan dan percobaan pada salinan berhasil. Backfill bukan persetujuan atas seluruh masalah historis. Bila ada sumber tanpa pemilik, referensi berbeda, unit tidak jelas, atau nomor bertentangan, pemeriksaan harus diselesaikan dahulu; jangan menghapus sumber untuk membuat pemeriksaan lulus.

5. Tinjau rencana rekonsiliasi yang eksplisit.

   Rencana berupa JSON versi 1. Contoh struktur untuk mengonfirmasi tahap awal:

   ```json
   {
     "version": 1,
     "module": "snp",
     "record_id": 123,
     "expected_fingerprint": "GANTI_DENGAN_64_KARAKTER_FINGERPRINT_HASIL_PEMERIKSAAN",
     "action": "konfirmasi_tahap",
     "reason": "Alasan pemeriksaan beserta rujukan halaman arsip yang membuktikannya.",
     "evidence": {
       "disk": "local",
       "path": "arsip-r7/bukti-surat-123.pdf",
       "sha256": "GANTI_DENGAN_SHA256_BERKAS_BUKTI"
     },
     "details": { "stage_id": 321, "nomor_putaran": 0, "aktif": true }
   }
   ```

   Contoh di atas sengaja belum dapat diterapkan sampai ID, fingerprint, dan bukti sebenarnya diisi. Disk `local` menunjuk ke `storage/app/private`; disk `public` menunjuk ke `storage/app/public`. Bukti harus sudah tersedia pada penyimpanan tersebut. Setelah diterapkan, aplikasi menyimpan salinan bukti tersendiri.

   Pilihan tindakan:

   | Tindakan | Isi `details` dan batasannya |
   | --- | --- |
   | `konfirmasi_tahap` | `stage_id`, `nomor_putaran`, `aktif`. Nomor yang sudah ada tidak diganti; nomor yang belum jelas dapat diisi sekali berdasarkan bukti. Tahap aktif harus cocok dengan putaran asal dan bukan tahap yang sudah dilewati. Registrasi satu isian per unit dibentuk hanya dari identitas sumber/PIC yang terbukti. |
   | `konfirmasi_tanggal` | `stage_id`, `ditetapkan_at`; tahap awal juga memerlukan `mulai_at`, sedangkan TL memerlukan `jatuh_tempo`. Gunakan zona waktu eksplisit, misalnya `2026-11-01T09:30:00+07:00`. Batas awal dihitung otomatis +14 hari kalender. Tanggal yang sudah ditetapkan tidak ditimpa. Bukti harus menunjukkan penerbitan/keputusan yang benar-benar terjadi. |
   | `konfirmasi_unit` | `stage_id`, `table` (`tb_tanggapan` atau `tb_tindak_lanjut`), `source_id`, `unit_kerja_id`. Hanya melengkapi unit yang sebelumnya kosong. Unit yang sudah tercatat, isi, pengisi, dan waktu asli dipertahankan. Identitas tidak boleh bertentangan dengan PIC sumber atau menghasilkan duplikasi. |
   | `salinan_historis` | `compilation_id`, `context`, `materials`, dan bila diperlukan `review_ids`. `context` dan `materials` memuat salinan arsip lengkap dalam format versi 1 yang diperiksa `KompilasiBahanReader`; referensi butir/tahap/putaran/unit, jumlah bahan, kelengkapan saat itu, pengisi, waktu, format, dokumen, dan hash setiap gambar/berkas wajib konsisten. Pengelola menyiapkan manifest dari arsip yang diperiksa, bukan menyalin tabel sumber terkini. Hubungan reviu yang berbeda tidak ditimpa dan salinan resmi yang sudah tersimpan tidak boleh diganti. |
   | `arsip_historis` | `{}` untuk seluruh surat lama yang sudah selesai, atau `stage_id` untuk tahap lama yang sudah diarsipkan. Keputusan ini mempertahankan data sebagai arsip baca saja dan tidak menyatakannya sebagai bahan resmi terkompilasi. Tidak dapat dipakai untuk melewati masalah pada tahap aktif. |

   Bahan dengan unit atau versi yang belum dapat dibuktikan tetap terbuka. Tidak ada tindakan umum “selesaikan semua masalah”, penghapusan sumber, atau penetapan Close baru dari perkiraan.

   Pada rekonsiliasi tenggat TL historis, tanggal dibaca sampai akhir hari WIB. Tenggat pada hari yang sama dengan keputusan dapat dipertahankan bila akhir harinya masih sesudah waktu keputusan yang dibuktikan. Ketentuan ini menjaga tanggal arsip asli; pengisian tenggat pada reviu baru tetap mengikuti validasi R5.

   Validasi tanpa menulis:

   ```sh
   php artisan workflow:rekonsiliasi-revisi /lokasi/privat/rencana.json --user=ID_SUPER_ADMIN
   ```

   Setelah rencana dan bukti diperiksa, penerapan eksplisit:

   ```sh
   php artisan workflow:rekonsiliasi-revisi /lokasi/privat/rencana.json --user=ID_SUPER_ADMIN --apply
   ```

   Perintah hanya menerima pemeriksa Super Admin aktif. Simpan hasil validasi dan audit. Fingerprint harus diperbarui setelah setiap perubahan yang memang telah diperiksa; rencana lama ditolak jika surat berubah. Pengiriman rencana yang persis sama tidak menggandakan keputusan. Jangan mengisi tanggal hari ini untuk menutup ketidakjelasan tanggal historis.

   Siapkan dan uji seluruh rencana pada salinan terlebih dahulu. Penerapan production dilakukan dalam satu jendela maintenance setelah bukti tersedia, bukan sedikit demi sedikit lalu membiarkan pengguna kembali ke alur lama yang sudah berbeda konteksnya.

6. Periksa seluruh penerimaan sebelum membuka alur baru.

   - Status awal: Draft tanpa butir; tersedia 1 November memberi batas 15 November pukul 23.59 WIB; nol respons terlambat mulai 16 November; respons pertama menjadi Proses tanpa menunggu semua unit.
   - Satu isian per unit/butir/tahap, akun seunit, edit melalui Detail, penguncian putaran lama, serta pengisian yang bersamaan dengan kompilasi.
   - Kompilasi nol/satu/sebagian isian; salinan tidak berubah sesudah sumber diedit atau dokumen diganti.
   - Empat pilihan reviu dan kewenangan lama; dua kali looping melalui TL; tenggat tiap putaran, pengajuan Pending/disetujui/ditolak, status perbaikan tetap, serta kedua keputusan Close.
   - Kelengkapan setelah Close mendapat penanda, tidak membuka putaran, dan tidak mengubah keputusan maupun PDF.
   - Preview sesuai tahap/putaran; navigasi tidak menghapus formulir; PDF biasa/custom sesuai salinan, termasuk unit belum mengisi pada saat itu, format/gambar/isi panjang, filter, dan halaman lanjutan.
   - Daftar, Detail, filter, statistik, serta modul Ragab, Rawas, Rapat Eksternal, Kunjungan Kerja, Produk Hukum, dan administrasi yang memakai komponen bersama.

   Simpan hasil uji pada salinan, laporan rekonsiliasi, dan hasil pemeriksaan berkas. Jalankan di server agar master/role asli diperiksa tanpa mengekspor data akun:

   ```sh
   php artisan workflow:check-revisi --with-main --json
   ```

   Semua penghalang harus nol. Peringatan arsip historis bukan bukti bahwa bahan arsip tersebut dapat dipakai sebagai bahan resmi. Pengisian susulan tidak dimasukkan untuk “melengkapi” salinan lama.

   Untuk mengulang pengujian dump lokal, gunakan server MySQL khusus pengujian dengan port terpisah dan salinan SQL tanpa `CREATE DATABASE`/`USE` ke nama production. `tests/Unit/SuratPutaranDumpMigrationTest.php` menyediakan pengujian migrasi, konkurensi, dan backup/restore. Pengujian terbaru tujuh modul memakai `SIDEWAS_R7_DUMP_DIR`, `SIDEWAS_PUTARAN_TEST_MYSQL_PORT`, `SIDEWAS_R7_MYSQLDUMP`, dan opsional `SIDEWAS_R7_STORAGE_DIR`. Jangan mengarahkan koneksi pengujian ke hosting. Hasil lokal disimpan di `tmp/r7`, yang diabaikan Git dan tidak boleh dipublikasikan karena memuat salinan data modul.

7. Aktifkan hanya setelah langkah sebelumnya lulus.

   Dalam maintenance, ubah `.env` yang dipakai aplikasi menjadi:

   ```dotenv
   SIDEWAS_REVIU_PUTARAN_ENABLED=true
   SIDEWAS_ALUR_REVISI_ENABLED=true
   ```

   Jalankan Deploy HEAD Commit. Skrip membersihkan cache, menjalankan migrasi, membangun cache, lalu memeriksa `workflow:check-revisi --if-enabled` sebelum menyinkronkan berkas publik. Pemeriksaan ini juga mencakup database utama. Jika gagal, aplikasi tetap maintenance dan alur tidak boleh dibuka untuk pengguna.

   Jika kode yang sama sudah terpasang dan diperiksa, perubahan hanya pada flag `.env` dapat diaktifkan tanpa deploy/migrasi ulang: jalankan `config:cache`, bersihkan view melalui `view:clear`, periksa `config:show sidewas`, lalu jalankan kembali `workflow:check-revisi --with-main --json`. Pertahankan maintenance sampai kedua flag terbukti `true` dan laporan data tetap siap. `config/sidewas.php` saat ini hanya berisi dua flag sehingga aman dicatat pada log privat. Pastikan lokasi `.env` runtime sudah diketahui, bukan mengasumsikan salinan pada folder lain.

   Uji login sebagai role yang sebenarnya dipakai, sidebar TL, daftar, Detail, kompilasi/reviu, dan cetak satu contoh yang sesuai. Hindari membuat keputusan pada surat kerja asli hanya untuk mencoba. Buka akses pengguna setelah hasilnya sesuai.

   Bila Terminal cPanel tidak tersedia, minta pengelola hosting menjalankan perintah dari root aplikasi. Cron Jobs satu kali dapat dipakai oleh pengelola untuk perintah yang sudah ditinjau, dengan log di direktori privat dan job segera dihapus setelah selesai. Jangan membuat URL publik untuk menjalankan Artisan atau menampilkan `.env`.

8. Pulihkan sesuai keadaan, dengan aplikasi tetap maintenance.

   Jika hanya kode/config yang dipasang dan belum ada rekonsiliasi atau transaksi alur revisi, kembalikan flag revisi ke `false`, deploy ulang rilis yang diketahui sesuai, bersihkan/bangun cache, lalu periksa sebelum `artisan up`.

   Jika sudah ada rekonsiliasi atau transaksi revisi, menonaktifkan flag saja tidak mengembalikan data. Hentikan penulisan, cadangkan keadaan gagal untuk penelusuran, lalu pulihkan satu paket kode, seluruh database terkait, `.env`, dan `storage/app` dari titik waktu yang sama. Pulihkan terlebih dahulu pada lingkungan terpisah untuk memverifikasi jumlah/hash dan hubungan data. Jangan mencampur database sebelum aktivasi dengan storage atau kode dari sesudah aktivasi.

   Migrasi R1/R2/R3/R5 sengaja menolak rollback yang menghapus riwayat yang sudah dipakai. Jangan menonaktifkan penjagaan tersebut. Bila sudah ada isian baru setelah cadangan dibuat, libatkan pemilik data untuk mempertahankan dan merekonsiliasinya sebelum pemulihan; jangan membuang transaksi tersebut secara diam-diam.

   Setelah pemulihan, jalankan pemeriksaan koneksi, role, daftar/Detail, dokumen/gambar, statistik, dan laporan sebelum membuka akses kembali. Simpan sebab kegagalan serta tindakan pemulihan bersama audit rilis.
