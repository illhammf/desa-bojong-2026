## ⚠️ Noted:
### Indonesia:
Readme ini dibuat secara otomatis menggunakan skrip `start.sh` dengan template yang sudah disiapkan.
Anda bisa mengeditnya sesuai kebutuhan setelah proyek dibuat.

### in English:
This README was automatically generated using the `start.sh` script and a pre-configured template.
You can edit it as needed after the project has been created.

<div align="center">

# 🚀 desa-bojong

### Laravel Boilerplate Project 2026

Built with ❤️ using Laravel, Docker, WSL & Filament

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-10.11-003545?style=for-the-badge&logo=mariadb&logoColor=white)
![Filament](https://img.shields.io/badge/Filament-v3-F59E0B?style=for-the-badge)

[![GitHub](https://img.shields.io/badge/Open-GitHub-black?style=for-the-badge&logo=github)](https://github.com/illhammf/desa-bojong-2026)

</div>

---

## 📖 Tentang Project

**desa-bojong** merupakan aplikasi berbasis Laravel yang dibuat menggunakan **Ilham Boilerplate 2026**.

Project ini sudah dikonfigurasi dengan berbagai kebutuhan modern untuk pengembangan aplikasi web.

---

## ✨ Fitur Bawaan

- ✅ Laravel 12
- ✅ Docker Environment
- ✅ Nginx Web Server
- ✅ PHP 8.3
- ✅ MariaDB 10.11
- ✅ Filament Admin Panel (v3)
- ✅ GitHub Integration
- ✅ WSL Ubuntu Support
- ✅ HTTPS Local Domain
- ✅ Seeder & Migration Ready

---

## 👥 Setup untuk Anggota Tim (setelah clone)

Jalankan dari WSL Ubuntu. Pastikan Docker, mkcert, dan git sudah terpasang.

```bash
# 1. Clone dan masuk ke folder proyek
git clone https://github.com/illhammf/desa-bojong-2026.git
cd desa-bojong-2026

# 2. Buat file .env untuk Docker Compose
cp .env.example .env

# 3. Buat sertifikat SSL lokal
mkdir -p nginx/ssl
mkcert -install
mkcert -cert-file nginx/ssl/desa-bojong.test.crt -key-file nginx/ssl/desa-bojong.test.key desa-bojong.test localhost 127.0.0.1

# 4. Daftarkan domain ke hosts WSL
echo "127.0.0.1 desa-bojong.test" | sudo tee -a /etc/hosts

# 5. Jalankan kontainer (composer install, src/.env, APP_KEY, dan migrate dibuat otomatis)
docker compose up -d --build
docker compose logs -f php   # tunggu sampai muncul "Ready to serve!"
```

Tambahkan juga baris `127.0.0.1 desa-bojong.test` ke file hosts Windows
(`C:\Windows\System32\drivers\etc\hosts`, buka Notepad sebagai Administrator).

---

## 🚀 Quick Start

### Menjalankan Docker

```bash
docker compose up -d
```

atau

```bash
dcu
```

### Masuk Container PHP

```bash
docker compose exec php bash
```

### Menjalankan Migration

```bash
dca migrate
```

### Menjalankan Seeder

```bash
dca db:seed
```

### Mematikan Docker

```bash
docker compose down
```

atau

```bash
dcd
```

---

## 🌐 URL Aplikasi

### Website Proyek

```text
https://desa-bojong.test
```

### Admin Panel

```text
https://desa-bojong.test/admin
```

---

## 🐳 Docker Services

| Service | Port |
|----------|----------|
| Nginx | 80 / 443 |
| PHP-FPM | Internal |
| MariaDB | 13306 (host) |

Koneksi Navicat: host `127.0.0.1`, port `13306`, user `root`, password `p455w0rd`.

---

## 📂 Struktur Project

```text
.
├── db/            # Konfigurasi & data MariaDB
├── nginx/         # Konfigurasi Nginx & sertifikat SSL lokal
├── php/           # Dockerfile, entrypoint, konfigurasi PHP
└── src/           # Aplikasi Laravel
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── public/
    ├── resources/
    ├── routes/
    ├── storage/
    └── tests/
```

---

## 🛠️ Tech Stack

| Teknologi | Digunakan Untuk |
|------------|------------|
| Laravel | Backend Framework |
| PHP | Server Side Language |
| MariaDB | Database |
| Docker | Containerization |
| Nginx | Web Server |
| Filament | Admin Panel |
| WSL Ubuntu | Development Environment |
| GitHub | Version Control |

---

## 📋 Checklist Pengembangan

- [ ] Setup Database
- [ ] Setup Authentication
- [ ] Membuat Migration
- [ ] Membuat Seeder
- [ ] Membuat Admin Panel
- [ ] Deploy Production
- [ ] Tambahkan Fitur Lainnya

---

## Command Line Tools

| Command | Deskripsi |
|---------|-----------|
| `dcu` | Jalankan Docker Compose |
| `dcd` | Matikan Docker Compose proyek ini |
| `dca <perintah>` | Jalankan perintah Artisan di kontainer PHP (contoh: `dca migrate`) |
| `dci` | Jalankan `php artisan project:init` |
| `dcp "pesan"` | Git add, commit, pull --rebase, dan push dengan pesan commit |
| `dcm ModelName` | Buat model + migration + seeder + controller |
| `dcf ModelName` | Buat Filament resource dari kolom tabel (jalankan setelah `dca migrate`) |
| `dcr ModelName` | Hapus model, migration, seeder, controller, policy, dan Filament resource |
| `code .` | Buka proyek di Visual Studio Code |

Alur membuat fitur CRUD baru: `dcm Nama` → isi file migration → `dca migrate` → `dcf Nama`.

---

## 👨‍💻 Developer

**illhammf**

Generated automatically using:

```text
Ilham Boilerplate 2026
```

---

## 🎯 Quote of The Project

> "Konsistensi Mengalahkan Bakat Saat Bakat Tidak Muncul."

🔥 Happy Coding Yaa!!!
