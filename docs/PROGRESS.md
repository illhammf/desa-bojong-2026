# PROGRESS: Sistem Pelayanan Administrasi Desa Bojong

> File serah-terima antar sesi Claude. Kirim file ini di awal percakapan baru, lalu minta
> Claude melanjutkan dari bagian "Langkah Berikutnya". Perbarui file ini setiap satu tahap selesai.
> Terakhir diperbarui: 2026-11-22

## 1. Ringkasan Proyek
- Website pengajuan dan pemantauan layanan administrasi Balai Desa Bojong, Kab. Tangerang (tugas kelompok PPL).
- Sistem tidak menerbitkan surat. Surat diambil langsung di balai desa dengan tanda tangan dan stempel basah.
- Empat peran: Masyarakat, Petugas Desa, Administrator, Super Admin.
- Stack (dari boilerplate `~/boilerplate/start.sh`): Docker, Nginx HTTPS (`https://desa-bojong.test`),
  PHP 8.3, Laravel 12, Filament 3.3 (Shield + Logger), MariaDB 10.11.
- Lokasi proyek: `~/perkuliahan/desa-bojong` (kode Laravel di `src/`).
- Pola kerja: Claude memberi kode lengkap dan lokasi file. Pengguna menjalankan di terminal WSL, lalu menempel hasilnya.
  Dokumen proyek (BRD/PRD) diberikan sebagai teks salin-tempel ke Word, bukan file unduhan.

## 2. Dokumen Acuan
| Dokumen | Status | Catatan |
|---|---|---|
| SRS (101 hlm) | Final | Tidak diubah lagi, BRD menjadi patokan bila ada beda |
| BRD v2 | Selesai | `docs/BRD SISTEM PELAYANAN ADMINISTRASI DESA BOJONG.pdf` |
| PRD | Selesai (67 hlm) | `docs/PRD SISTEM PELAYANAN ADMINISTRASI DESA BOJONG.pdf` |
| Desain Figma | Ada (29 hlm PDF) | Hlm 23 kosong. Selisih dengan BRD lihat bagian 4 |

## 3. Aturan Kerja Koding
- Alur: `dcm Model` -> isi migration -> `dca migrate` -> `dcf Model`.
- `dcf` baru dijalankan setelah migration DAN seeder final (dcf membaca kolom tabel).
- Nama tabel tidak jamak dan ditulis eksplisit di model (`protected $table`).
- Alias terminal: `dcu`, `dcd`, `dca <artisan>`, `dci`, `dcp "pesan"`, `dcm`, `dcf`, `dcr`.
- Struktur Filament: resource di `src/app/Filament/Admin/Resources`, nama halaman list memakai "s" (contoh `ListJenisLayanans`), relation manager di `.../NamaResource/RelationManagers`. Menu dikelompokkan: Master Data, Pelayanan, Pengelolaan.
- Panel: `app/Providers/Filament/AdminPanelProvider.php` (id `admin`, path `/admin`, SPA, plugin Shield, Themes, AuthUIEnhancer, Overlook, EditProfile, LightSwitch). Grup menu terdaftar: Pelayanan, Master Data, Pengaturan, Administration (grup "Administration" milik resource bawaan boilerplate, nama string harus sama persis). Pengaturan situs diambil lewat `PengaturanSitus::ambil()`.

## 4. Keputusan yang Dipertahankan
**Bisnis**
- Status permohonan hanya 5: Diajukan -> Dalam Pemeriksaan -> Perlu Perbaikan (kembali ke Dalam Pemeriksaan) -> Diproses -> Siap Diambil.
- Tidak ada Selesai, Ditolak, Dibatalkan, pembatalan, notifikasi WA/SMS/email, integrasi Dukcapil.
- Admin dan Super Admin TIDAK memverifikasi permohonan (BR-05, BR-33). Hanya Petugas.
- Nomor pengajuan: `REG-[KODE]-[TAHUN]-[URUT]`, contoh `REG-SKU-2025-0856`. Urut global per tahun, unik.
- NIK 16 digit, unik. Berkas maksimal 5 MB (PDF/JPG/JPEG/PNG), disimpan di disk privat dan dibuka lewat controller yang cek hak akses.
- Login satu halaman untuk semua peran (email + kata sandi). Masyarakat tidak boleh masuk panel.
- Lupa Kata Sandi serta Profil/Ubah Kata Sandi: ada di desain, tapi masuk Versi 1.1 (di luar BRD).
- Layanan mengikuti 20 layanan BRD (bukan 12 contoh di Figma). Persyaratan kondisional (SKTM, Beda Nama, Ahli Waris, Ijin Keramaian, Surat Keterangan Umum) menunggu konfirmasi desa.

**Teknis**
- Role lewat Spatie/Shield: `masyarakat`, `petugas`, `admin`, `super_admin`. Tidak ada kolom `role` di `users`.
- Super Admin hanya punya baris di `users` + role (tanpa tabel profil).
- Web dinamis: persyaratan isian bisa ditambah dari Filament (`tipe_input`, `opsi`), konten situs lewat tabel `pengaturan_situs` (1 baris).
- `APP_LOCALE=id`. `canAccessPanel()` harus menolak masyarakat.

## 5. Struktur Tabel
| Tabel | Kolom utama |
|---|---|
| `users` (+ no_hp, status_akun) | bawaan + `no_hp` string(15), `status_akun` default `aktif` |
| `admin`, `petugas` | `user_id` (PK+FK users), `nip` |
| `masyarakat` | `user_id` (PK+FK users), `nik` unik(16), `alamat` |
| `jenis_layanan` | `kode` unik, `nama_layanan`, `deskripsi`, `estimasi_waktu`, `aktif` |
| `persyaratan` | `jenis_layanan_id`, `nama_persyaratan`, `tipe_persyaratan` (dokumen/isian), `tipe_input`, `opsi` json, `wajib`, `keterangan`, `urutan`, `aktif` |
| `permohonan` | `user_id` (-> masyarakat), `jenis_layanan_id`, `nomor_permohonan` unik, `tahun`, `urut` (unik gabungan), `tanggal_pengajuan`, `alasan_pengajuan`, `status`, `catatan_petugas` |
| `isian_permohonan` | `permohonan_id`, `persyaratan_id` (unik gabungan), `nilai_isian` |
| `dokumen` | `permohonan_id`, `persyaratan_id` null, `nama_file`, `lokasi_file`, `tipe_file`, `ukuran_file` (byte), `status_verifikasi`, `tanggal_upload` |
| `riwayat_status` | `permohonan_id`, `petugas_id` null (-> petugas), `status`, `keterangan`, `waktu_perubahan` |
| `pengaturan_situs` | nama_situs, tagline, logo, foto_beranda, nama_instansi, alamat_kantor, kode_pos, telepon, whatsapp, email, jam_layanan, judul_hero, deskripsi_hero, pengumuman, judul_informasi, isi_informasi, langkah_alur json, catatan_pengambilan, teks_footer |

## 6. Status Pengerjaan
Legenda: [x] terkonfirmasi berhasil, [~] kode sudah diberikan tapi belum dikonfirmasi, [ ] belum dikerjakan.

- [x] Boilerplate dan kontainer jalan (`desa-bojong_php`, `_nginx`, `_db` sehat)
- [x] BRD, PRD, diagram 6.6 dan 6.7 selesai dan ter-commit
- [x] Migration: 11 file (batch 2) sukses `dca migrate`
- [x] Enum `StatusPermohonan` dan `TipePersyaratan` (`app/Enums`), terbukti lewat tinker
- [x] 10 model + relasi (`PengaturanSitus`, `Admin`, `Petugas`, `Masyarakat`, `JenisLayanan`, `Persyaratan`, `Permohonan`, `IsianPermohonan`, `Dokumen`, `RiwayatStatus`), diuji lewat tinker
- [x] `User.php` (fillable, relasi profil, `canAccessPanel()` berbasis role). Teruji: petugas masuk, warga ditolak.
- [x] `RoleSeeder` (4 role), `UserSeeder` (akun demo: superadmin/admin/petugas/warga @demo.test, kata sandi `password`), `DatabaseSeeder`
- [x] Seeder final dan teruji: `JenisLayananSeeder` (20 layanan), `PersyaratanSeeder` (51 persyaratan), `PengaturanSitusSeeder` (1 baris), `PermohonanSeeder` (3 data contoh, hanya env local)
- [x] Shield dicek: super_admin.enabled=true, define_via_gate=false (tidak ada bypass Gate otomatis). Permission verifikasi tetap tidak diberikan ke super_admin. Perlu diuji nanti.
- [x] `https://desa-bojong.test/admin/login` terbuka (login Filament tampil)
- [x] `dcf JenisLayanan` (resource dasar di `app/Filament/Admin/Resources`)
- [x] `JenisLayananResource` + `PersyaratanRelationManager` (teruji di browser). Layanan yang punya permohonan tidak bisa dihapus, aktif/nonaktif lewat tombol berkonfirmasi, kode terkunci bila sudah dipakai, form persyaratan dinamis (tipe_input, opsi).
- [x] `PengaturanSitusPage` + branding panel dinamis (teruji di browser: nama/logo/favicon dari `pengaturan_situs`, petugas tidak bisa akses). Akses masih berbasis role admin/super_admin, nanti dialihkan ke permission Shield.
- [ ] `PermohonanResource` (verifikasi petugas) dan penyesuaian `UserResource` bawaan boilerplate (BUKAN `dcf User`, sudah ada)
Stub controller kosong (JenisLayanan, Permohonan, Dokumen) belum diisi.
- [ ] Halaman Blade Masyarakat, alur pengajuan, verifikasi petugas, laporan


## 7. Rencana Filament (setelah migration + seeder final)
- Resource: JenisLayanan (Persyaratan sebagai relation manager), Permohonan (isian, dokumen, riwayat di halaman detail), User.
- Halaman khusus: Pengaturan Situs (hanya Admin dan Super Admin).
- Permission verifikasi permohonan TIDAK diberikan ke `admin` maupun `super_admin`.

## 8. Hal yang Belum Diputuskan
- Kode 20 layanan sudah diusulkan di `JenisLayananSeeder` (DOMY, DOMU, KK, KTP, PND, SKTM, BDN, MNS, MNB, WLI, BMN, AKT, KLR, KMT, WRS, BMR, KRM, DOM, SIU, UMM), bisa diubah dari Filament.
- Pengaturan situs belum tercantum di BRD/PRD, perlu ditambahkan singkat ke dokumen.
- Konfirmasi desa soal persyaratan kondisional.
- Desain Figma perlu disesuaikan: batas 2 MB -> 5 MB, hapus teks Dukcapil/notifikasi, tambah kolom alamat di registrasi, ganti "SIPADES".

## 9. Langkah Berikutnya
1. Konfirmasi uji Pengaturan Situs (nama/logo tampil di panel, petugas tidak bisa mengakses).
2. `dcf Permohonan` lalu rapikan: tabel antrean petugas, halaman detail verifikasi (isian, dokumen, riwayat), aksi ubah status sesuai `StatusPermohonan::transisi()`, catatan wajib saat Perlu Perbaikan.
3. Sesuaikan `UserResource` bawaan (baca dulu isinya): role 4 peran, profil per peran, status akun, grup menu.
4. Shield: `shield:generate`, atur permission per role lewat seeder (verifikasi hanya `petugas`), alihkan `PengaturanSitusPage::canAccess()` ke permission, uji BR-05 dan BR-33.
5. Halaman Blade masyarakat (registrasi, login, beranda, layanan, formulir dinamis, status, perbaikan).

## 10. Prompt Lanjut untuk Claude Baru
"Lanjutkan proyek Desa Bojong dari file PROGRESS.md ini. Jangan mulai dari awal dan jangan ubah keputusan di bagian 4.
Bedakan kode yang sudah diberikan, sudah diterapkan, dan sudah teruji. Minta file atau output terminal bila perlu.
Bahasa Indonesia santai tapi teknis, kode lengkap dengan lokasi file dan perintah terminal."
