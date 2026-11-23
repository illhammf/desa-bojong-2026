#!/usr/bin/env bash
# Pemulihan dari cadangan (BRD NFR-18).
#
# Pemakaian : scripts/pulihkan.sh <folder_cadangan> <nama_db_tujuan> <folder_berkas_tujuan>
# Contoh uji    : scripts/pulihkan.sh cadangan/20261123-100000 desa_bojong_coba /tmp/berkas-coba
# Pemulihan asli: PULIHKAN_TIMPA=ya scripts/pulihkan.sh cadangan/20261123-100000 desa-bojong src/storage/app
#
# Sengaja tanpa nilai bawaan agar tidak menimpa data aktif secara tidak sengaja.
# Menimpa basis data atau folder berkas AKTIF hanya dengan PULIHKAN_TIMPA=ya.
set -euo pipefail

cd "$(dirname "$0")/.."

gagal() { echo "GAGAL: $*" >&2; exit 1; }

[ "$#" -eq 3 ] || gagal "pemakaian: $0 <folder_cadangan> <nama_db_tujuan> <folder_berkas_tujuan>"

SUMBER="${1%/}"
DB_TUJUAN="$2"
BERKAS_TUJUAN="${3%/}"

[[ "$DB_TUJUAN" =~ ^[A-Za-z0-9_-]+$ ]] || gagal "nama basis data tujuan hanya boleh huruf, angka, garis bawah, dan tanda hubung"

for f in basis-data.sql.gz berkas.tar.gz SHA256SUMS; do
  [ -f "$SUMBER/$f" ] || gagal "berkas cadangan tidak lengkap: $SUMBER/$f"
done

[ -f src/.env ] || gagal "src/.env tidak ditemukan"
DB_AKTIF="$(grep -E '^DB_DATABASE=' src/.env | head -n1 | cut -d= -f2- | tr -d "\"' \r")"

BERKAS_AKTIF="$(realpath -m src/storage/app)"
BERKAS_TUJUAN_ABS="$(realpath -m "$BERKAS_TUJUAN")"

if { [ "$DB_TUJUAN" = "$DB_AKTIF" ] || [ "$BERKAS_TUJUAN_ABS" = "$BERKAS_AKTIF" ]; } && [ "${PULIHKAN_TIMPA:-}" != "ya" ]; then
  gagal "tujuan adalah basis data/folder berkas AKTIF. Untuk menimpa, jalankan dengan PULIHKAN_TIMPA=ya"
fi

docker compose ps --status running --services 2>/dev/null | grep -x db >/dev/null || gagal "kontainer db tidak berjalan (jalankan: dcu)"

echo "[1/4] Memeriksa keutuhan cadangan ..."
(cd "$SUMBER" && sha256sum -c SHA256SUMS) || gagal "sidik cadangan tidak cocok, cadangan rusak atau diubah"

echo "[2/4] Menyiapkan basis data '$DB_TUJUAN' ..."
docker compose exec -T db sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "$1"' _ \
  "CREATE DATABASE IF NOT EXISTS \`$DB_TUJUAN\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

echo "[3/4] Memulihkan basis data ..."
gunzip -c "$SUMBER/basis-data.sql.gz" \
  | docker compose exec -T db sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$1"' _ "$DB_TUJUAN"

echo "[4/4] Memulihkan berkas ke '$BERKAS_TUJUAN' ..."
mkdir -p "$BERKAS_TUJUAN"
tar -xzf "$SUMBER/berkas.tar.gz" -C "$BERKAS_TUJUAN"

echo "Selesai: basis data '$DB_TUJUAN' dan berkas di '$BERKAS_TUJUAN' dipulihkan dari $SUMBER."
