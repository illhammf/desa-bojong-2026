#!/usr/bin/env bash
# Pencadangan basis data dan berkas (BRD NFR-18).
#
# Pemakaian : scripts/cadangkan.sh [folder_tujuan]
# Bawaan    : cadangan/<tanggal-jam>
# Variabel  : SIMPAN=14  jumlah cadangan terbaru yang dipertahankan (hanya untuk folder bawaan)
#
# Isi cadangan: basis-data.sql.gz, berkas.tar.gz (storage/app/private dan public), SHA256SUMS, CATATAN.txt.
# PERHATIAN: cadangan memuat data pribadi (NIK, KTP, KK). Jangan di-commit dan simpan terenkripsi di luar mesin ini.
set -euo pipefail

cd "$(dirname "$0")/.."

gagal() { echo "GAGAL: $*" >&2; exit 1; }

SIMPAN="${SIMPAN:-14}"
[[ "$SIMPAN" =~ ^[0-9]+$ ]] || gagal "SIMPAN harus berupa angka"

[ -f src/.env ] || gagal "src/.env tidak ditemukan"
DB_NAMA="$(grep -E '^DB_DATABASE=' src/.env | head -n1 | cut -d= -f2- | tr -d "\"' \r")"
[ -n "$DB_NAMA" ] || gagal "DB_DATABASE tidak ditemukan di src/.env"

docker compose ps --status running --services 2>/dev/null | grep -x db >/dev/null || gagal "kontainer db tidak berjalan (jalankan: dcu)"

STAMP="$(date +%Y%m%d-%H%M%S)"
TUJUAN="${1:-cadangan/$STAMP}"
[ ! -e "$TUJUAN" ] || gagal "folder tujuan sudah ada: $TUJUAN"

umask 077
mkdir -p "$TUJUAN"

echo "[1/3] Mencadangkan basis data '$DB_NAMA' ..."
docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --default-character-set=utf8mb4 "$1"' _ "$DB_NAMA" \
  | gzip -9 > "$TUJUAN/basis-data.sql.gz"

# Pemeriksaan integritas: dump yang lengkap diakhiri baris "Dump completed".
if ! gunzip -c "$TUJUAN/basis-data.sql.gz" | tail -n 1 | grep 'Dump completed' >/dev/null; then
  rm -rf "$TUJUAN"
  gagal "dump basis data tidak lengkap, cadangan dibatalkan"
fi

echo "[2/3] Mencadangkan berkas (dokumen privat dan unggahan publik) ..."
BERKAS=()
for d in private public; do
  if [ -d "src/storage/app/$d" ]; then
    BERKAS+=("$d")
  fi
done
[ "${#BERKAS[@]}" -gt 0 ] || { rm -rf "$TUJUAN"; gagal "folder src/storage/app/private dan public tidak ditemukan"; }

tar -czf "$TUJUAN/berkas.tar.gz" \
  --exclude='private/livewire-tmp' \
  --exclude='*/.gitignore' \
  -C src/storage/app "${BERKAS[@]}"

echo "[3/3] Membuat daftar sidik (SHA-256) ..."
(cd "$TUJUAN" && sha256sum basis-data.sql.gz berkas.tar.gz > SHA256SUMS)

cat > "$TUJUAN/CATATAN.txt" <<CATATAN
Cadangan Sistem Pelayanan Administrasi Desa Bojong
Dibuat      : $(date '+%Y-%m-%d %H:%M:%S %z')
Basis data  : $DB_NAMA
Isi         : basis-data.sql.gz (dump MariaDB), berkas.tar.gz (storage/app/private dan public)
PERHATIAN   : berisi data pribadi (NIK, KTP, KK). Jangan di-commit ke Git; simpan terenkripsi di luar mesin ini.
Pemulihan   : scripts/pulihkan.sh <folder ini> <nama_db_tujuan> <folder_berkas_tujuan>
CATATAN

# Pemangkasan: hanya folder berpola tanggal-jam di cadangan/, folder lain tidak disentuh.
if [ "$SIMPAN" -gt 0 ]; then
  { ls -1d cadangan/20[0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9] 2>/dev/null || true; } \
    | sort | head -n "-$SIMPAN" | xargs -r rm -rf
fi

echo "Selesai: $TUJUAN ($(du -sh "$TUJUAN" | cut -f1))"
