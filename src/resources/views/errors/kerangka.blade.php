<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $kode }} | {{ $judul }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f3fa;color:#1f2430;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;padding:1.5rem}
        main{max-width:30rem;width:100%;background:#fff;border-radius:.75rem;padding:2.5rem 2rem;text-align:center;box-shadow:0 4px 24px rgba(60,40,120,.10)}
        .kode{font-size:3.5rem;font-weight:800;line-height:1;color:#5b3a9c}
        h1{font-size:1.35rem;margin:1rem 0 .5rem}
        p{margin:0 0 1.5rem;color:#4a5160;line-height:1.6}
        a{display:inline-block;background:#5b3a9c;color:#fff;text-decoration:none;padding:.65rem 1.25rem;border-radius:.5rem;font-weight:600}
        small{display:block;margin-top:1.5rem;color:#7b8190}
    </style>
</head>
<body>
    <main>
        <div class="kode">{{ $kode }}</div>
        <h1>{{ $judul }}</h1>
        <p>{{ $pesan }}</p>
        <a href="{{ url('/') }}">Kembali ke Beranda</a>
        <small>Pelayanan Administrasi Desa Bojong</small>
    </main>
</body>
</html>
