# PROGRESS: Sistem Pelayanan Administrasi Desa Bojong

> File serah-terima antar sesi Claude. Kirim file ini di awal percakapan baru, lalu minta
> Claude melanjutkan dari bagian "Langkah Berikutnya". Perbarui setiap satu tahap selesai.
> Terakhir diperbarui: 2026-11-23

## 1. Ringkasan Proyek
- Website pengajuan dan pemantauan layanan administrasi Balai Desa Bojong, Kab. Tangerang (tugas kelompok PPL).
- Sistem TIDAK menerbitkan surat. Surat diambil langsung di balai desa dengan tanda tangan dan stempel basah.
- Empat peran: Masyarakat, Petugas Desa, Administrator, Super Admin.
- Stack (dari boilerplate `~/boilerplate/start.sh`): Docker, Nginx HTTPS (`https://desa-bojong.test`),
  PHP 8.3, Laravel 12, Filament 3.3 (Shield + Logger), MariaDB 10.11, Pest 3.
- Lokasi proyek: `~/perkuliahan/desa-bojong` (kode Laravel di `src/`).
- Pola kerja: Claude memberi kode lengkap + lokasi file + perintah uji. Pengguna menjalankan di terminal WSL dan menempel hasilnya.
  Dokumen proyek (BRD/PRD) diberikan sebagai teks salin-tempel ke Word, bukan file unduhan.

## 2. Dokumen Acuan
| Dokumen | Status | Catatan |
|---|---|---|
| SRS (101 hlm) | Final | Bila beda dengan BRD, BRD jadi patokan |
| BRD v2 | Selesai | `docs/BRD SISTEM PELAYANAN ADMINISTRASI DESA BOJONG.pdf` (FR-01..51, BR, NFR) |
| PRD | Selesai (67 hlm) | `docs/PRD SISTEM PELAYANAN ADMINISTRASI DESA BOJONG.pdf` |
| Desain Figma | Ada (29 hlm PDF) | Hlm 23 kosong. Selisih dengan BRD: lihat bagian 8 |

## 3. Aturan Kerja Koding
- Alur: `dcm Model` -> isi migration -> `dca migrate` -> `dcf Model`. `dcf` hanya setelah migration DAN seeder final.
- Nama tabel tidak jamak dan ditulis eksplisit di model (`protected $table`).
- Alias terminal: `dcu`, `dcd`, `dca <artisan>` (artisan di kontainer PHP, contoh `dca tinker --execute="..."`), `dci`, `dcp "pesan"`, `dcm`, `dcf`, `dcr`.
- BACKEND DULU, tampilan (Blade) belakangan. Aturan bisnis ada di service dan dibuktikan dulu lewat skrip/test.
- Status [x] hanya bila ada bukti (keluaran uji). Kode yang baru diberikan = [~].
- Filament: resource di `src/app/Filament/Admin/Resources`, halaman list memakai "s" (`ListJenisLayanans`), relation manager di `.../NamaResource/RelationManagers`, halaman khusus di `src/app/Filament/Admin/Pages`, widget di `.../Widgets`.
- Panel: `app/Providers/Filament/AdminPanelProvider.php` (id `admin`, path `/admin`, SPA, plugin Shield, Themes, AuthUIEnhancer, Overlook, EditProfile, LightSwitch). Grup menu: Pelayanan, Master Data, Pengaturan, Administration (nama grup harus sama persis dengan string di resource).
- Uji skrip (rollback otomatis): `dca tinker --execute="require base_path('database/uji_pengajuan.php');"` (15 skenario) dan `.../uji_audit.php` (12 skenario). Pest: `dca test` (SQLite memori; baru 2 contoh bawaan).
- Test Pest: `dca test` (SQLite memori, tiap test menjalankan `$this->seed()`; env `testing` melewati `PermohonanSeeder`). Helper: `tests/Support/Skenario.php`.
- Akun demo (seeder): superadmin/admin/petugas/warga `@demo.test`, kata sandi `password` (hanya lokal).

## 4. Keputusan yang Dipertahankan
**Bisnis**
- Status permohonan hanya 5: Diajukan -> Dalam Pemeriksaan -> Perlu Perbaikan (kembali ke Dalam Pemeriksaan) -> Diproses -> Siap Diambil.
- Tidak ada Selesai, Ditolak, Dibatalkan, pembatalan, notifikasi WA/SMS/email, integrasi Dukcapil.
- Admin dan Super Admin TIDAK memverifikasi permohonan (BR-05, BR-33). Hanya Petugas.
- Nomor pengajuan `REG-[KODE]-[TAHUN]-[URUT]` (contoh `REG-SIU-2026-0001`), urut global per tahun, unik (indeks tahun+urut).
- NIK 16 digit, unik. Berkas PDF/JPG/JPEG/PNG maks 5 MB per berkas, disk privat, nama acak, jenis dicek dari isi berkas.
- Login satu halaman untuk semua peran (email + kata sandi). Masyarakat tidak boleh masuk panel.
- Lupa Kata Sandi serta Profil/Ubah Kata Sandi: ada di desain, masuk Versi 1.1 (di luar BRD).
- 20 layanan mengikuti BRD (bukan 12 contoh Figma). Persyaratan kondisional (SKTM, Beda Nama, Ahli Waris, Ijin Keramaian, Surat Keterangan Umum, Surat Pindah pada KK) belum wajib, menunggu konfirmasi desa.
- Dokumen hanya boleh dibuka pemilik permohonan dan Petugas (PRD diam soal ini; dipilih akses tersempit). Admin/Super Admin ditolak.
- Aturan turunan (tidak tertulis eksplisit di BRD): permohonan baru boleh Diproses setelah SEMUA dokumennya ditandai Sesuai.

**Teknis**
- Role lewat Spatie/Shield: `masyarakat`, `petugas`, `admin`, `super_admin`. Tidak ada kolom `role` di `users`. Satu akun = satu role. Super Admin tanpa tabel profil.
- Empat role inti tidak boleh dihapus/diganti nama (event model di `AppServiceProvider`).
- Aturan kewenangan TERKUNCI DI KODE (bukan permission yang bisa diubah lewat UI): BR-05/BR-33 (`AlurStatusService`, `PermohonanResource::bolehKelola`), BR-30/31/32 (`PenggunaService`, `UserPolicy`). Permission Shield hanya untuk akun, role, log aktivitas (`HakAksesSeeder`, bersifat menambah). Pengaturan Situs, Laporan, Daftar Permohonan memakai pengecekan role.
- Kebijakan akun = `App\Policies\AkunPolicy` yang DIDAFTARKAN EKSPLISIT (`Gate::policy`) agar tidak tertimpa generator Shield. Jangan mengandalkan `UserPolicy.php`.
- Shield: `define_via_gate=false` (tidak ada bypass Gate otomatis untuk super_admin).
- Perubahan status HANYA lewat `AlurStatusService::ubah()`; status awal lewat `catatAwal()`. Tanpa petugas (sistem) hanya boleh PerluPerbaikan -> DalamPemeriksaan.
- Master data (JenisLayanan, Persyaratan) hanya untuk admin/super_admin lewat `JenisLayananPolicy`/`PersyaratanPolicy` (berbasis role, tidak bisa dilonggarkan lewat UI).
- Pengajuan dan perbaikan HANYA lewat `PengajuanService` (halaman Blade cukup memanggilnya).
- Log aktivitas (FR-51): logger otomatis Filament Logger DIMATIKAN (`config/filament-logger.php`). Pencatatan eksplisit: trait `LogsActivity` (User, JenisLayanan, Persyaratan, PengaturanSitus), listener `CatatAutentikasi`, `AlurStatusService`, `PenggunaService`. Log name: Autentikasi (login/logout/login_gagal), Akun (+event `peran`), Layanan, Persyaratan, Pengaturan, Permohonan (event `diajukan`, `status` dengan dari/ke). Kata sandi tidak dicatat. Hanya Super Admin yang bisa melihat (permission `view_any_activity`), tanpa permission ubah/hapus. Seeding tidak dicatat.
- `APP_LOCALE=id`, `APP_TIMEZONE=Asia/Jakarta`.
- Batas unggah: PHP 128M (`php/local.ini`), Nginx `client_max_body_size 64M` (`nginx/default.conf`).
- Disk `local` (privat) tidak memasang rute `/storage/...` (`serve => false` di `config/filesystems.php`); dokumen hanya lewat `DokumenController`.

## 5. Struktur Tabel
| Tabel | Kolom utama |
|---|---|
| `users` (+ no_hp, status_akun) | bawaan + `no_hp` string(15), `status_akun` default `aktif` |
| `admin`, `petugas` | `user_id` (PK+FK users), `nip` |
| `masyarakat` | `user_id` (PK+FK users), `nik` unik(16), `alamat` |
| `jenis_layanan` | `kode` unik, `nama_layanan`, `deskripsi`, `estimasi_waktu`, `aktif` |
| `persyaratan` | `jenis_layanan_id`, `nama_persyaratan`, `tipe_persyaratan` (dokumen/isian), `tipe_input` (teks/area_teks/angka/tanggal/pilihan), `opsi` json, `wajib`, `keterangan`, `urutan` (otomatis bila kosong), `aktif` |
| `permohonan` | `user_id` (-> masyarakat), `jenis_layanan_id`, `nomor_permohonan` unik, `tahun`, `urut` (unik gabungan), `tanggal_pengajuan`, `alasan_pengajuan`, `status`, `catatan_petugas` |
| `isian_permohonan` | `permohonan_id`, `persyaratan_id` (unik gabungan), `nilai_isian` |
| `dokumen` | `permohonan_id`, `persyaratan_id` null, `nama_file`, `lokasi_file`, `tipe_file`, `ukuran_file` (byte), `status_verifikasi` (belum_diperiksa/sesuai/tidak_sesuai), `tanggal_upload` |
| `riwayat_status` | `permohonan_id`, `petugas_id` null (-> petugas), `status`, `keterangan`, `waktu_perubahan` |
| `pengaturan_situs` | nama_situs, tagline, logo, foto_beranda, nama_instansi, alamat_kantor, kode_pos, telepon, whatsapp, email, jam_layanan, judul_hero, deskripsi_hero, pengumuman, judul_informasi, isi_informasi, langkah_alur json, catatan_pengambilan, teks_footer |

## 6. Status Pengerjaan
Legenda: [x] terbukti (ada keluaran uji/konfirmasi pengguna), [~] kode sudah diberikan tapi belum terbukti, [ ] belum dikerjakan.

**Fondasi**
- [x] Boilerplate, kontainer, BRD, PRD, diagram 6.6 dan 6.7
- [x] 11 migration, 2 enum status (+ `StatusVerifikasiDokumen`), 10 model + relasi
- [x] `User.php` (relasi profil, `canAccessPanel()` berbasis role aktif), 4 role, `HakAksesSeeder`
- [x] Seeder: 20 layanan, 51 persyaratan (SRS 6.4), pengaturan situs, 5 permohonan contoh + 10 dokumen PDF (env local)

**Panel pengelola (Filament)**
- [x] `JenisLayananResource` + `PersyaratanRelationManager` (form dinamis, aturan hapus/nonaktif)
- [x] `PengaturanSitusPage` + branding panel dinamis dari `pengaturan_situs`
- [x] `PermohonanResource` (hanya petugas) + aksi status + `DokumenRelationManager` + `RiwayatRelationManager` + `DokumenController`
- [x] Kelola Akun: `UserResource` + `PenggunaService` + `UserPolicy` (BR-30/31/32)
- [x] Dashboard per peran (widget statistik petugas/pengelola, 2 grafik, permohonan terbaru; admin tanpa tombol Periksa)
- [~] `LaporanPage`: route dan query terbukti, tampilan di browser belum dikonfirmasi. Ekspor = Versi 1.1.

**Logika bisnis (service)**
- [x] `AlurStatusService` (teruji tinker: admin ditolak, transisi tidak sah ditolak, Diproses diblokir bila dokumen belum Sesuai)
- [x] `PengajuanService` ajukan + perbaiki: 15 skenario lulus (`uji_pengajuan.php`). Catatan: berkas masih palsu (`UploadedFile::fake`), unggah sungguhan baru teruji saat ada halamannya.
- [x] Pencatatan aktivitas FR-51: 12 skenario lulus (`uji_audit.php`)

**Infrastruktur dan keamanan**
- [x] Batas unggah Nginx `client_max_body_size 64M` (teruji: halaman 200, unggah 25 MB lolos Nginx (405 dari Laravel), 70 MB ditolak 413)
- [x] `DatabaseSeeder` mematikan log saat seeding (teruji: 0 baris log setelah `migrate:fresh --seed`)
- [x] Proteksi 4 role inti (event `Role::deleting/updating` di `AppServiceProvider`; teruji tinker: hapus dan ganti nama ditolak, peran non-inti bebas). Uji lewat halaman Role di browser belum dikonfirmasi.

**Belum dikerjakan**
- [~] Test Pest permanen (`tests/Feature/*Test.php` + `tests/Support/Skenario.php`): 111 dari 113 lulus. Dua yang gagal = BR-30 dan BR-31 pada policy akun (UserPolicy ternyata berperilaku seperti stub bawaan Shield). Perbaikan: `AkunPolicy` didaftarkan eksplisit di `AppServiceProvider` + tes `KebijakanAkunTest`. Menunggu `dca test` hijau.
- [~] Rapikan menu: Role dan Log Aktivitas masuk grup "Pengaturan" (override `src/lang/vendor/*/{en,id}`), Overlook dan `LatestAccessLogs` dibuang, `JenisLayananPolicy` + `PersyaratanPolicy` (master data hanya admin/super admin; sebelumnya petugas bisa mengaksesnya). Belum dikonfirmasi.
- [ ] Audit kelengkapan FR-01..51 dan BR terhadap kode (matriks `docs/TRACEABILITY.md`)
- [ ] Stub controller kosong (`JenisLayananController`, `PermohonanController`) dipakai atau dihapus (tergantung rancangan Blade)
- [ ] Halaman Blade masyarakat (registrasi, login satu halaman, beranda, layanan, formulir dinamis, status, perbaikan)

## 7. Peta Kode Penting (backend)
- `app/Services/AlurStatusService.php`: satu-satunya pintu ubah status (cek petugas, transisi, catatan wajib, dokumen Sesuai, riwayat + log).
- `app/Services/PengajuanService.php`: `ajukan()`, `perbaiki()`, `persyaratanAktif()`. Validasi FR-33/34/35, token anti kirim ganda, nomor dengan retry.
- `app/Services/PenggunaService.php`: aturan akun (peran yang boleh dipilih, BR-30/31/32), `terapkan()` menyelaraskan role dan tabel profil.
- `app/Http/Controllers/DokumenController.php` + rute `dokumen.tampil` (`/dokumen/{dokumen}`); rute sementara `login` mengarah ke `/admin/login`.
- `app/Policies/UserPolicy.php` (menerima record target). `app/Listeners/CatatAutentikasi.php`.
- `database/seeders/*`, `database/uji_pengajuan.php`, `database/uji_audit.php`.

## 8. Hal yang Belum Diputuskan
- Pengaturan situs (dan aturan dinamis persyaratan) belum tercantum di BRD/PRD, perlu ditambahkan singkat ke dokumen.
- Konfirmasi desa soal persyaratan kondisional dan estimasi waktu tiap layanan (kolom `estimasi_waktu` masih kosong).
- Kode 20 layanan (DOMY, DOMU, KK, KTP, PND, SKTM, BDN, MNS, MNB, WLI, BMN, AKT, KLR, KMT, WRS, BMR, KRM, DOM, SIU, UMM) bisa diubah dari Filament.
- Desain Figma perlu disesuaikan: batas 2 MB -> 5 MB, hapus teks Dukcapil/notifikasi, tambah kolom alamat di registrasi, ganti "SIPADES", halaman Super Admin belum ada desain.

## 9. Langkah Berikutnya
1. Jalankan `dca test` dan perbaiki sampai hijau (tempel keluaran bila ada yang gagal).
2. Konfirmasi di browser: grup menu "Pengaturan" (Kelola Akun, Peran, Log Aktivitas, Pengaturan Situs), petugas tidak melihat menu Master Data, halaman Laporan tampil.
3. Matriks FR/BR -> kode -> bukti (`docs/TRACEABILITY.md`) untuk mencari yang masih bolong.
4. Keputusan sisa backend: stub controller kosong, pembatasan percobaan login, kebijakan kata sandi, persiapan produksi (APP_DEBUG, akun demo, pencadangan).
5. Setelah backend mantap: halaman Blade masyarakat yang memanggil `PengajuanService` dan `AlurStatusService`.

## 10. Prompt Lanjut untuk Claude Baru
"Lanjutkan proyek Desa Bojong dari file PROGRESS.md ini. Jangan mulai dari awal dan jangan ubah keputusan di bagian 4.
Bedakan kode yang sudah diberikan, sudah diterapkan, dan sudah teruji. Minta file atau output terminal bila perlu.
Bahasa Indonesia santai tapi teknis, kode lengkap dengan lokasi file dan perintah terminal."
