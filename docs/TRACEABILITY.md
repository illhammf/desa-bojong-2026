# TRACEABILITY: Kebutuhan BRD terhadap Kode dan Bukti

> Terakhir diperbarui: 2026-11-23. Acuan: BRD v2 (FR-01..51, BR-01..33, NFR).
> ✅ terbukti tes otomatis (`dca test`) | 🟡 ada tapi belum teruji otomatis atau baru sebagian | 🧩 logika siap, menunggu halaman Blade | ❌ belum ada

## Fungsional (FR)
| Kode | Kebutuhan | Status | Bukti / Catatan |
|---|---|---|---|
| FR-01 | Registrasi masyarakat (NIK 16 digit unik, data wajib) | 🧩 | `RegistrasiTest` (NIK, email, nomor seluler, kata sandi, atomik, peran terkunci). Halaman Blade belum. |
| FR-02 | Login email + kata sandi, tolak akun nonaktif | 🧩 | `AutentikasiTest` (pesan seragam, nonaktif ditolak, batas percobaan), `AksesPermohonanTest` (nonaktif di tengah sesi). Halaman login Blade belum. |
| FR-03 | Logout, halaman terproteksi tertutup | 🧩 | `AutentikasiTest` (keluar, tercatat log), `DokumenAksesTest` (tamu diarahkan ke login). Tombol logout Blade belum. |
| FR-04 | Daftar 20 layanan | 🧩 | Data: `SeederTest`. Tampilan Blade belum. |
| FR-05 | Tampilkan persyaratan (isian/dokumen) | 🧩 | `PengajuanService::persyaratanAktif()`. Blade belum. |
| FR-06 | Pilih layanan lalu ke formulir | 🧩 | Blade belum. |
| FR-07 | Formulir sesuai layanan (dinamis) | 🧩 | Aturan/validasi: `PengajuanTest`. Render form belum. |
| FR-08 | Isi data termasuk alasan pengajuan | ✅ | `PengajuanTest` |
| FR-09 | Unggah dokumen (jpg/jpeg/png/pdf, maks 5 MB) | ✅ | `PengajuanTest` (tepat 5 MB lolos, 5121 KB dan .exe ditolak). Indikator unggah = Blade. |
| FR-10 | Kirim permohonan setelah lengkap | ✅ | `PengajuanTest` |
| FR-11 | Daftar permohonan milik sendiri | 🧩 | `Permohonan::milik()` + `AksesPermohonanPolicy`: `AksesPermohonanTest`. Halaman Blade belum. |
| FR-12 | Nomor pengajuan dan info dasar | 🧩 | Nomor: `PengajuanTest`. Tampilan belum. |
| FR-13 | Status terkini dan riwayat | 🧩 | Data: `AlurStatusTest`. Tampilan belum. |
| FR-14 | Keterangan perbaikan dari petugas | 🧩 | Kolom `catatan_petugas`: `AlurStatusTest`. Tampilan belum. |
| FR-15 | Perbaikan oleh pemohon | ✅ | `PengajuanTest` (7 skenario perbaikan) |
| FR-16 | Info pengambilan saat Siap Diambil | 🧩 | `pengaturan_situs.catatan_pengambilan`. Tampilan belum. |
| FR-17 | Daftar permohonan masuk (petugas) | ✅ | `PanelAksesTest` (akses), uji browser (filter dan tab) |
| FR-18 | Rincian permohonan, isian, dokumen | ✅ | `PanelAksesTest` (detail, relation manager) |
| FR-19 | Status Dalam Pemeriksaan | ✅ | `AlurStatusTest` |
| FR-20 | Catat hasil verifikasi | 🟡 | Hanya dokumen yang dicatat (Sesuai/Tidak Sesuai). Kesesuaian data isian diputuskan lewat tombol Proses atau Minta Perbaikan. Perlu keputusan. |
| FR-21 | Perlu Perbaikan + keterangan | ✅ | `AlurStatusTest` (catatan wajib) |
| FR-22 | Periksa ulang hasil perbaikan | ✅ | `PengajuanTest` (dokumen pengganti kembali Belum Diperiksa) |
| FR-23 | Status Diproses setelah sesuai | ✅ | `AlurStatusTest` (diblokir bila dokumen belum Sesuai) |
| FR-24 | Status Siap Diambil | ✅ | `AlurStatusTest` |
| FR-25 | Petugas melihat riwayat status | 🟡 | `RiwayatRelationManager` ada, isinya belum dites. |
| FR-26 | Rekap untuk petugas | ✅ | `PanelAksesTest` (laporan tampil) |
| FR-27 | Admin kelola akun masyarakat dan petugas | 🟡 | Aturan: `HakAksesTest`. Form Filament: uji browser saja. |
| FR-28 | Admin tetapkan peran (masyarakat/petugas) | ✅ | `HakAksesTest` |
| FR-29 | Kelola jenis layanan | 🟡 | Policy: `HakAksesTest`, `PanelAksesTest`. Form dan aturan hapus: uji browser saja. |
| FR-30 | Kelola persyaratan | 🟡 | Idem. Bentuk isian dinamis teruji di `PengajuanTest`. |
| FR-31 | Rekap untuk admin | ✅ | `PanelAksesTest` |
| FR-32 | Identifikasi peran, batasi akses | 🧩 | `PanelAksesTest`, `AutentikasiTest` (tujuan per peran). Pengalihan di halaman Blade belum. |
| FR-33 | Validasi isian wajib | ✅ | `PengajuanTest` |
| FR-34 | Cek dokumen wajib | ✅ | `PengajuanTest` |
| FR-35 | Tolak pengajuan tidak lengkap, sebut bagiannya | ✅ | `PengajuanTest` |
| FR-36 | Simpan permohonan setelah lolos | ✅ | `PengajuanTest` |
| FR-37 | Nomor pengajuan unik | ✅ | `PengajuanTest` (berurutan, tanpa duplikat) |
| FR-38 | Status awal Diajukan | ✅ | `PengajuanTest` |
| FR-39 | Ubah data hanya saat Perlu Perbaikan | ✅ | `PengajuanTest` |
| FR-40 | Validasi ulang, kembali ke Dalam Pemeriksaan, nomor tetap | ✅ | `PengajuanTest` |
| FR-41 | Batasi alur status dan kewenangan | ✅ | `AlurStatusTest` (8 transisi tidak sah) |
| FR-42 | Catat riwayat status | ✅ | `AlurStatusTest`, `PengajuanTest` |
| FR-43 | Batasi akses data dan dokumen | ✅ | `AksesPermohonanTest`, `DokumenAksesTest` (dokumen mengikuti kebijakan permohonan). |
| FR-44 | Rekap per layanan atau status | ✅ | `PanelAksesTest` |
| FR-45 | Super Admin menjalankan fungsi Administrator | ✅ | `HakAksesTest`, `PanelAksesTest` |
| FR-46 | Super Admin kelola akun Administrator | 🟡 | Aturan: `HakAksesTest`. Form: uji browser. |
| FR-47 | Kelola peran dan permission | 🟡 | Halaman Peran Shield, uji browser. Peran inti: `PeranIntiTest`. |
| FR-48 | Beri dan cabut peran Admin/Super Admin | ✅ | `HakAksesTest` |
| FR-49 | Super Admin melihat log aktivitas | 🟡 | `LogAktivitasPolicy` (role-locked): `AuditTest`, `KondisiStartupTest`. Halaman log belum dites HTTP. |
| FR-50 | Minimal satu Super Admin aktif | ✅ | `HakAksesTest` |
| FR-51 | Catat aktivitas penting | ✅ | `AuditTest`. Log tidak dapat diubah/dihapus siapa pun, walau Super Admin memegang seluruh permission: `KondisiStartupTest`. |

## Aturan Bisnis (BR)
| Kode | Status | Bukti / Catatan |
|---|---|---|
| BR-01 | ✅ | `PengajuanTest` (hanya masyarakat aktif) |
| BR-02 | ✅ | `AksesPermohonanTest`, `DokumenAksesTest` |
| BR-03 | ✅ | `AlurStatusTest` |
| BR-04 | ✅ | `HakAksesTest` (master data, akun, laporan) |
| BR-05 | ✅ | `AlurStatusTest`, `PanelAksesTest` |
| BR-06..11 | ✅ | `PengajuanTest` |
| BR-12..18 | ✅ | `AlurStatusTest`, `PengajuanTest` |
| BR-19..23 | 🟡 | Alur: `AlurStatusTest`. "Tidak ada unduhan surat" belum ada tesnya. |
| BR-24..28 | ✅ | Kunci asing (migration), `AuditTest`, `AlurStatusTest` |
| BR-29 | ✅ | `HakAksesTest` |
| BR-30 | ✅ | `HakAksesTest`, `KebijakanAkunTest` |
| BR-31 | ✅ | `HakAksesTest` |
| BR-32 | ✅ | `HakAksesTest` |
| BR-33 | ✅ | `AlurStatusTest`, `HakAksesTest`, `PanelAksesTest` |

## Non-Fungsional (NFR)
| Kode | Kebutuhan | Status | Bukti / Catatan |
|---|---|---|---|
| NFR-01/02 | Akses setelah login, pembatasan peran | ✅ | `PanelAksesTest`, `DokumenAksesTest` |
| NFR-03 | Kata sandi di-hash | ✅ | `RegistrasiTest` (hash terverifikasi, tidak masuk log) |
| NFR-04/30 | Isolasi data, dokumen privat | ✅ | `DokumenAksesTest`, `PengajuanTest` |
| NFR-05 | Validasi masukan dan berkas | ✅ | `PengajuanTest` |
| NFR-06 | HTTPS | 🟡 | Lokal HTTPS. Produksi menunggu sertifikat. |
| NFR-07/08/10 | Kinerja (95% ≤ 3 dtk, ≤ 5 dtk, 20 pengguna) | ❌ | Belum diukur. |
| NFR-09 | Umpan balik unggahan | 🧩 | Blade/Livewire. |
| NFR-11..15 | Bahasa Indonesia, navigasi, responsif | 🧩 | Panel berbahasa Indonesia. Halaman masyarakat belum. |
| NFR-16 | Gagal simpan tidak tampil sebagai sukses | 🟡 | Transaksi dan pembersihan berkas ada, belum ada tes kegagalan. |
| NFR-17 | Kirim ganda tidak menggandakan | ✅ | `PengajuanTest` |
| NFR-18 | Pencadangan dan pemulihan | ✅ | `scripts/uji-pemulihan.sh` di MariaDB sungguhan: 19 dari 19 tabel identik (jumlah baris + checksum), 11 berkas identik (SHA-256), aplikasi lolos pada data pulihan. Skrip: `cadangkan.sh`, `pulihkan.sh`. Panduan: `docs/PEMULIHAN.md`. |
| NFR-19 | Pesan gangguan tanpa rincian teknis | ✅ | `HalamanGalatTest` (403/404/419/429/500/503 berbahasa Indonesia; galat sistem tanpa rincian teknis saat debug mati). Di produksi pastikan lewat `dca app:periksa-produksi` (`ProduksiTest`). |
| NFR-20..22 | Kompatibilitas peramban dan layar | 🧩 | Menunggu Blade. |
| NFR-23 | Pemisahan tanggung jawab | 🟡 | Service/policy terpisah. Tidak diuji otomatis. |
| NFR-24 | Layanan dan persyaratan tanpa ubah kode | ✅ | `PengajuanTest` (isian ditambah admin), `SeederTest` |
| NFR-25 | Dokumentasi cara menjalankan | ❌ | Baru `docs/PROGRESS.md`. README belum. |
| NFR-26 | Git | ✅ | Repo GitHub. |
| NFR-27 | Referensi valid | ✅ | Kunci asing + `PengajuanTest` |
| NFR-28 | Nomor unik | ✅ | Indeks unik + `PengajuanTest` |
| NFR-29 | Status konsisten dengan riwayat | ✅ | `KonsistensiTest`; pengaman model menolak ubah status di luar service; `dca app:periksa-konsistensi` untuk data nyata. |
| Tambahan | Pencatatan aktivitas | ✅ | `AuditTest` |

## Celah backend yang tersisa (urut prioritas)
1. Dokumentasi cara menjalankan (NFR-25): README khusus proyek, panduan, bersihkan sisa boilerplate di `.env.example`.
2. Pengukuran kinerja (NFR-07/08/10).
3. Tes tambahan: halaman Log Aktivitas (FR-49), riwayat terlihat (FR-25), kegagalan simpan (NFR-16).
4. Keputusan bersama: cakupan FR-20 (verifikasi data isian).
5. Halaman Blade masyarakat (menutup seluruh status 🧩).
6. Sebelum produksi: jalankan `dca app:periksa-produksi` sampai SIAP (ganti kata sandi root basis data, jangan buka port 13306 keluar, hapus akun demo, HTTPS dan cookie aman).
