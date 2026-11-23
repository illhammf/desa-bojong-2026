#!/usr/bin/env bash
# Uji pemulihan (BRD NFR-18): cadangkan, pulihkan ke basis data sementara, lalu bandingkan dengan data aktif.
# Data aktif hanya DIBACA. Penulisan hanya ke basis data sementara dan folder sementara yang dihapus kembali.
# Jangan ubah data (misalnya mengajukan permohonan) selama uji berjalan (sekitar satu menit).
set -uo pipefail

cd "$(dirname "$0")/.."

[ -f src/.env ] || { echo "GAGAL: src/.env tidak ditemukan" >&2; exit 1; }
DB_ASLI="$(grep -E '^DB_DATABASE=' src/.env | head -n1 | cut -d= -f2- | tr -d "\"' \r")"
[ -n "$DB_ASLI" ] || { echo "GAGAL: DB_DATABASE tidak ditemukan di src/.env" >&2; exit 1; }

DB_UJI="uji_pulih_$(date +%s)"
TMP="$(mktemp -d)"
CADANGAN="$TMP/cadangan"
BERKAS_UJI="$TMP/berkas"
GAGAL=0

sql() { docker compose exec -T db sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -N -B -e "$1"' _ "$1"; }

bersih() {
  sql "DROP DATABASE IF EXISTS \`$DB_UJI\`" >/dev/null 2>&1 || true
  rm -rf "$TMP"
}
trap bersih EXIT

lulus() { echo "  [OK]     $*"; }
cacat() { echo "  [GAGAL]  $*"; GAGAL=$((GAGAL + 1)); }
lewat() { echo "  [LEWAT]  $*"; }

echo "UJI PEMULIHAN (NFR-18)"
echo "Basis data aktif: $DB_ASLI | basis data sementara: $DB_UJI"
echo

echo "1. Membuat cadangan"
scripts/cadangkan.sh "$CADANGAN" >/dev/null || { echo "GAGAL: pencadangan gagal" >&2; exit 1; }
lulus "cadangan dibuat ($(du -sh "$CADANGAN" | cut -f1))"

echo "2. Memulihkan ke basis data sementara"
scripts/pulihkan.sh "$CADANGAN" "$DB_UJI" "$BERKAS_UJI" >/dev/null || { echo "GAGAL: pemulihan gagal" >&2; exit 1; }
lulus "pemulihan selesai"

echo "3. Membandingkan tabel (jumlah baris dan checksum)"
TABEL="$(sql "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB_ASLI' AND table_type='BASE TABLE' AND table_name NOT IN ('sessions','cache','cache_locks','jobs','failed_jobs','job_batches') ORDER BY table_name")"
[ -n "$TABEL" ] || { echo "GAGAL: daftar tabel kosong" >&2; exit 1; }

N_ASLI="$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_ASLI' AND table_type='BASE TABLE'")"
N_UJI="$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_UJI' AND table_type='BASE TABLE'")"
if [ "$N_ASLI" = "$N_UJI" ]; then
  lulus "jumlah tabel sama ($N_ASLI)"
else
  cacat "jumlah tabel berbeda: aktif=$N_ASLI, pulihan=$N_UJI"
fi

SEBELUM="$GAGAL"
SEMUA=0
BARIS=0
while IFS= read -r t; do
  [ -n "$t" ] || continue
  SEMUA=$((SEMUA + 1))
  a="$(sql "SELECT COUNT(*) FROM \`$DB_ASLI\`.\`$t\`" 2>/dev/null)" || a="?"
  b="$(sql "SELECT COUNT(*) FROM \`$DB_UJI\`.\`$t\`" 2>/dev/null)" || b="?"
  ca="$(sql "CHECKSUM TABLE \`$DB_ASLI\`.\`$t\`" 2>/dev/null | awk '{print $NF}')"
  cb="$(sql "CHECKSUM TABLE \`$DB_UJI\`.\`$t\`" 2>/dev/null | awk '{print $NF}')"
  if [ "$a" = "?" ] || [ "$b" = "?" ] || [ "$a" != "$b" ]; then
    cacat "tabel $t: baris aktif=$a, pulihan=$b"
  elif [ -z "$ca" ] || [ "$ca" != "$cb" ]; then
    cacat "tabel $t: checksum berbeda (aktif=$ca, pulihan=$cb)"
  else
    BARIS=$((BARIS + a))
  fi
done <<< "$TABEL"
if [ "$GAGAL" -eq "$SEBELUM" ]; then
  lulus "$SEMUA tabel identik ($BARIS baris)"
fi

echo "4. Membandingkan berkas (SHA-256 setiap berkas)"
manifest() {
  (
    cd "$1" 2>/dev/null || exit 0
    DIRS=()
    for d in private public; do
      if [ -d "$d" ]; then DIRS+=("$d"); fi
    done
    [ "${#DIRS[@]}" -gt 0 ] || exit 0
    find "${DIRS[@]}" -type f ! -path 'private/livewire-tmp/*' ! -name .gitignore -print0 | sort -z | xargs -0 -r sha256sum
  )
}
M_ASLI="$(manifest src/storage/app)"
M_UJI="$(manifest "$BERKAS_UJI")"
if [ -z "$M_ASLI" ]; then
  lewat "tidak ada berkas aktif untuk dibandingkan"
elif [ "$M_ASLI" = "$M_UJI" ]; then
  lulus "$(printf '%s\n' "$M_ASLI" | grep -c . || true) berkas identik (isi dan lokasi)"
else
  cacat "daftar atau isi berkas pulihan berbeda dari yang aktif"
  diff <(printf '%s\n' "$M_ASLI") <(printf '%s\n' "$M_UJI") | head -n 10
fi

echo "5. Memeriksa aplikasi terhadap basis data pulihan"
NAMA_TERBACA="$(docker compose exec -T -e DB_DATABASE="$DB_UJI" php php artisan tinker --execute='echo DB::connection()->getDatabaseName();' 2>/dev/null | tr -d '\r\n ')"
if [ "$NAMA_TERBACA" != "$DB_UJI" ]; then
  cacat "aplikasi tidak memakai basis data pulihan (terbaca: '${NAMA_TERBACA:-kosong}'). Jalankan: dca config:clear, lalu ulangi."
elif docker compose exec -T -e DB_DATABASE="$DB_UJI" php php artisan app:periksa-konsistensi; then
  lulus "app:periksa-konsistensi lolos pada data pulihan"
else
  cacat "app:periksa-konsistensi menemukan masalah pada data pulihan"
fi

echo
if [ "$GAGAL" -eq 0 ]; then
  echo "UJI PEMULIHAN LULUS: cadangan dapat dipulihkan dan identik dengan data aktif."
  exit 0
fi
echo "UJI PEMULIHAN GAGAL: $GAGAL pemeriksaan tidak lolos."
exit 1
