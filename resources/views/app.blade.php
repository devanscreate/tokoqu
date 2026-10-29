<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Tokoqu POS</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-pos-alabaster text-pos-black antialiased selection:bg-pos-gold selection:text-white">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        <div id="app"></div>
    @else
        <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F4F6F9;font-family:system-ui,-apple-system,sans-serif;padding:24px;">
            <div style="max-width:480px;width:100%;background:#FFFFFF;border:2px solid #CBD5E1;border-radius:20px;padding:28px;box-shadow:0 10px 25px rgba(0,0,0,0.08);">
                <div style="display:inline-block;background:#E7F3FF;color:#1877F2;font-weight:700;font-size:12px;padding:4px 12px;border-radius:999px;margin-bottom:12px;">
                    Tokoqu POS — Siap Dijalankan
                </div>
                <h1 style="margin:0 0 8px;font-size:20px;color:#0F172A;">Aset Tampilan Belum Di-build</h1>
                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#475569;">
                    Agar tampilan kasir muncul, jalankan perintah berikut satu kali di terminal proyek Anda, lalu muat ulang (refresh) halaman ini:
                </p>
                <pre style="margin:0;padding:14px 16px;background:#0F172A;color:#F8FAFC;border-radius:12px;font-size:13px;overflow-x:auto;"><code>npm run build</code></pre>
            </div>
        </div>
    @endif
</body>
</html>


