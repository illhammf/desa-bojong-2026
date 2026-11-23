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
- Kebiasaan terminal (zsh + oh-my-zsh): JANGAN menempel blok besar langsung ke terminal. `url-quote-magic` menyisipkan backslash setelah URL (merusak kode, terbukti: `\]\)\;`), dan `#!` atau `<!` memicu history expansion sehingga baris sisa dieksekusi dan terminal bisa tertutup. Jalur aman: tempel ke berkas lewat editor (`touch x.sh; code x.sh`), lalu `bash x.sh; rm x.sh`, dan cek md5 bila diberikan. Perbaikan permanen: `DISABLE_MAGIC_FUNCTIONS="true"` di `~/.zshrc` (berlaku di terminal BARU).
- Skrip bash: (1) jangan memakai `grep -q` di pipeline ber-`pipefail` (SIGPIPE membuat pipeline dianggap gagal secara acak), pakai `grep ... >/dev/null`; (2) `docker compose exec` meneruskan stdin dan MENELAN masukan loop `while read`: selalu beri `</dev/null` atau baca loop lewat file descriptor terpisah, dan cocokkan jumlah item yang diproses dengan jumlah yang seharusnya; (3) docker tiruan di sandbox harus meniru perilaku asli (termasuk menghabiskan stdin), kalau tidak bug seperti ini lolos.
- BACKEND DULU, tampilan (Blade) belakangan. Aturan bisnis ada di service dan dibuktikan dulu lewat skrip/test.
- Status [x] hanya bila ada bukti (keluaran uji). Kode yang baru diberikan = [~].
- Filament: resource di `src/app/Filament/Admin/Resources`, halaman list memakai "s" (`ListJenisLayanans`), relation manager di `.../NamaResource/RelationManagers`, halaman khusus di `src/app/Filament/Admin/Pages`, widget di `.../Widgets`.
- Panel: `app/Providers/Filament/AdminPanelProvider.php` (id `admin`, path `/admin`, SPA, plugin Shield, Themes, AuthUIEnhancer, Overlook, EditProfile, LightSwitch). Grup menu: Pelayanan, Master Data, Pengaturan, Administration (nama grup harus sama persis dengan string di resource).
- Test Pest: `dca test` (SQLite memori, tiap test menjalankan `$this->seed()`; env `testing` melewati `PermohonanSeeder`). Helper: `tests/Support/Skenario.php`. Skrip uji lama (`uji_pengajuan.php`, `uji_audit.php`) sudah digantikan suite Pest dan dihapus.
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
- Startup kontainer (`ProjectUpdate`/`ProjectInitialize`) menjalankan `shield:generate` dan memberi role `super_admin` SELURUH permission (terbukti di DB sungguhan; tes memori tidak melihatnya). Karena itu: (1) berkas `*Policy.php` milik resource yang tidak dikecualikan ditimpa tiap start, jadi kebijakan penting bernama non-standar dan didaftarkan eksplisit di `AppServiceProvider` (`AkunPolicy`, `KelolaLayananPolicy`, `PersyaratanPolicy`, `LogAktivitasPolicy`); (2) `PermohonanResource`, `JenisLayananResource`, `ActivityResource` dikecualikan lewat `filament-shield.exclude.resources`; (3) izin halaman/widget dimatikan (`entities.pages/widgets = false`); (4) aturan inti berbasis ROLE di kode, bukan permission (BR-05/33, log hanya dilihat Super Admin dan tidak bisa diubah/dihapus siapa pun). `UserPolicy.php` dan `RolePolicy.php` dikelola Shield: JANGAN diedit. Tes `KondisiStartupTest` meniru kondisi startup.
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
- [x] Konsistensi status-riwayat (NFR-29): pengaman model `Permohonan::denganIzinUbahStatus()` (hanya `AlurStatusService` boleh ubah status, selainnya `LogicException`), perintah `dca app:periksa-konsistensi`, `KonsistensiTest`. Menyiapkan status di tes: `Skenario::paksaStatus()` (query builder).
- [x] `AlurStatusService` (teruji tinker: admin ditolak, transisi tidak sah ditolak, Diproses diblokir bila dokumen belum Sesuai)
- [x] `PengajuanService` ajukan + perbaiki: 15 skenario lulus (`uji_pengajuan.php`). Catatan: berkas masih palsu (`UploadedFile::fake`), unggah sungguhan baru teruji saat ada halamannya.
- [x] Pencatatan aktivitas FR-51: 12 skenario lulus (`uji_audit.php`)

**Infrastruktur dan keamanan**
- [x] Halaman galat berbahasa Indonesia (`resources/views/errors/*`, mandiri tanpa Vite) + `app:periksa-produksi` (APP_ENV/DEBUG/KEY/URL https, cookie sesi aman, kata sandi basis data lemah, akun demo, kata sandi "password", Super Admin aktif). Tes: `HalamanGalatTest`, `ProduksiTest`.
- [x] Pencadangan dan pemulihan (NFR-18) TERBUKTI di MariaDB sungguhan: `scripts/uji-pemulihan.sh` lulus (19 dari 19 tabel identik berikut checksum, 166 baris, 11 berkas identik SHA-256, aplikasi lolos `app:periksa-konsistensi` pada data pulihan). Skrip berjalan di HOST (mariadb-dump hanya ada di kontainer db). Cadangan di `cadangan/` (di `.gitignore`, memuat data pribadi). Panduan: `docs/PEMULIHAN.md`. Skrip uji menolak lulus bila jumlah tabel yang dibandingkan tidak sama dengan jumlah tabel yang ada.
- [x] Kondisi startup kontainer teruji di DB sungguhan setelah restart: generator hanya memproses Role dan User, `izin total 12` (admin 5, super_admin 12), Super Admin dapat MELIHAT log tetapi tidak dapat mengubah/menghapusnya. `LogAktivitasPolicy`, `KondisiStartupTest`, izin ubah/hapus log tidak lagi dibuat. 123 tes lulus. Tabel "Page/Widget Permissions" di log startup hanya tampilan: `shield:generate --all` tidak menyimpan izin page/widget (terbukti: 12 izin sebelum dan sesudah, page/widget = 0).
- [x] Batas unggah Nginx `client_max_body_size 64M` (teruji: halaman 200, unggah 25 MB lolos Nginx (405 dari Laravel), 70 MB ditolak 413)
- [x] `DatabaseSeeder` mematikan log saat seeding (teruji: 0 baris log setelah `migrate:fresh --seed`)
- [x] Proteksi 4 role inti (event `Role::deleting/updating` di `AppServiceProvider`; teruji tinker: hapus dan ganti nama ditolak, peran non-inti bebas). Uji lewat halaman Role di browser belum dikonfirmasi.

**Belum dikerjakan**
- [x] Logika akun masyarakat: `RegistrasiService` (FR-01), `AutentikasiService` (FR-02/03/32; pesan gagal seragam, batas 5 percobaan/menit), `PastikanAkunAktif`, `AksesPermohonanPolicy` + `Permohonan::milik()` (FR-11/43, BR-02). Teruji: `RegistrasiTest`, `AutentikasiTest`, `AksesPermohonanTest`.
- [x] Test Pest permanen: `dca test` hijau, 194 tes lulus (623 assertion): pengajuan/perbaikan, alur status, audit, hak akses BR-05/30..33, peran inti, seeder, akses dokumen, akses panel HTTP, laporan, kebijakan akun, kondisi startup, registrasi, autentikasi, akses permohonan, konsistensi status, halaman galat, pemeriksa produksi. Helper: `tests/Support/Skenario.php`.
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
- Sebelum produksi: kata sandi root basis data (`p455w0rd`) tercantum di README dan docker-compose yang ter-commit ke GitHub, anggap sudah terbuka. Ganti, buat pengguna basis data khusus, hapus pemetaan port `13306:3306` ke luar, hapus akun demo, nyalakan HTTPS dan cookie aman. Verifikasi: `dca app:periksa-produksi` harus SIAP.
- Pengaturan situs (dan aturan dinamis persyaratan) belum tercantum di BRD/PRD, perlu ditambahkan singkat ke dokumen.
- Konfirmasi desa soal persyaratan kondisional dan estimasi waktu tiap layanan (kolom `estimasi_waktu` masih kosong).
- Kode 20 layanan (DOMY, DOMU, KK, KTP, PND, SKTM, BDN, MNS, MNB, WLI, BMN, AKT, KLR, KMT, WRS, BMR, KRM, DOM, SIU, UMM) bisa diubah dari Filament.
- Desain Figma perlu disesuaikan: batas 2 MB -> 5 MB, hapus teks Dukcapil/notifikasi, tambah kolom alamat di registrasi, ganti "SIPADES", halaman Super Admin belum ada desain.

## 9. Langkah Berikutnya
Acuan: `docs/TRACEABILITY.md` (matriks FR/BR/NFR terhadap kode dan bukti). Posisi: 56 terbukti, 12 sebagian, 16 menunggu Blade, 2 belum (NFR-25 dokumentasi, NFR-07/08/10 kinerja).
1. Tes lapisan UI Filament (Livewire) untuk bagian yang baru teruji di browser: FR-25 (riwayat), FR-27/29/30/46 (form akun, layanan, persyaratan), FR-49 (halaman Log Aktivitas), aksi status petugas, kegagalan simpan (NFR-16). Berkas: `tests/Feature/Ui*Test.php`, `KegagalanSimpanTest.php`.
2. Dokumentasi (NFR-25): `docs/PANDUAN.md` + README khusus proyek, bersihkan sisa boilerplate di `.env.example` (butuh `cat .env.example` root dan `ls src/.env.example`).
3. Pengukuran kinerja (NFR-07/08/10). Keputusan FR-20 (verifikasi data isian) dan konfirmasi persyaratan kondisional dengan desa.
4. Setelah backend mantap: halaman Blade masyarakat yang memanggil `RegistrasiService`, `AutentikasiService`, `PengajuanService`, `AlurStatusService`.

## 10. Prompt Lanjut untuk Claude Baru
"Lanjutkan proyek Desa Bojong dari file PROGRESS.md ini. Jangan mulai dari awal dan jangan ubah keputusan di bagian 4.
Bedakan kode yang sudah diberikan, sudah diterapkan, dan sudah teruji. Minta file atau output terminal bila perlu.
Bahasa Indonesia santai tapi teknis, kode lengkap dengan lokasi file dan perintah terminal."
