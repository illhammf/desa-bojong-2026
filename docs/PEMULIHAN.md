# Pencadangan dan Pemulihan (NFR-18)

Skrip ada di `scripts/` (jalankan dari root repo, kontainer harus hidup: `dcu`).

| Perintah | Fungsi |
|---|---|
| `scripts/cadangkan.sh` | Cadangan basis data (dump MariaDB) + berkas (`storage/app/private` dan `public`) ke `cadangan/<tanggal-jam>/`, lengkap dengan SHA-256. Menyimpan 14 cadangan terbaru (`SIMPAN=n` untuk mengubah). |
| `scripts/uji-pemulihan.sh` | Membuktikan cadangan bisa dipulihkan: memulihkan ke basis data sementara, membandingkan setiap tabel (jumlah baris + checksum) dan setiap berkas (SHA-256) dengan data aktif, lalu menjalankan `app:periksa-konsistensi` pada data pulihan. Data aktif tidak diubah. |
| `scripts/pulihkan.sh <cadangan> <db_tujuan> <folder_berkas>` | Memulihkan. Tanpa nilai bawaan. Menimpa data AKTIF hanya dengan `PULIHKAN_TIMPA=ya`. |

## Memulihkan data aktif (darurat)
1. Hentikan akses pengguna (maintenance: `dca down`).
2. Pilih cadangan terbaru yang sehat. Skrip memeriksa SHA-256 lebih dulu dan menolak cadangan yang rusak.
3. `PULIHKAN_TIMPA=ya scripts/pulihkan.sh cadangan/<tanggal-jam> <nama_db_aktif> src/storage/app`
4. `dca app:periksa-konsistensi` lalu `dca up`.
Pemulihan berkas hanya menimpa berkas bernama sama dan tidak menghapus berkas yang lebih baru.

## Catatan keamanan
- Cadangan memuat NIK, KTP, dan KK warga. Folder `cadangan/` ada di `.gitignore`: jangan pernah di-commit.
- Simpan salinan terenkripsi di perangkat lain (aturan 3-2-1: tiga salinan, dua media, satu di luar lokasi).
- Jalankan `scripts/uji-pemulihan.sh` secara berkala. Cadangan yang belum pernah diuji pulih belum bisa disebut cadangan.

## Penjadwalan harian (opsional, di WSL)
`crontab -e` lalu tambahkan:
`30 1 * * * cd /root/perkuliahan/desa-bojong && scripts/cadangkan.sh >> cadangan/cron.log 2>&1`
