<?php
/**
 * index.php
 * ---------------------------------------------------------------------
 * Menu utama materi Fuzzy Logic (PHP native, tanpa framework/library).
 *
 * Cara menjalankan:
 *  1) Lewat CLI (terminal), langsung panggil skrip yang diinginkan:
 *       php tsukamoto/tsukamoto.php 7.5 30
 *       php mamdani/mamdani.php 4200 3 2.88
 *       php sugeno/sugeno.php 0.3 0.7 180 75
 *
 *  2) Lewat browser, jalankan server bawaan PHP dari folder apl/:
 *       php -S localhost:8000
 *     lalu buka http://localhost:8000/ (halaman ini) dan klik salah
 *     satu tautan metode di bawah.
 * ---------------------------------------------------------------------
 */

$cli = php_sapi_name() === 'cli';

if ($cli) {
    echo "======================================================\n";
    echo " MATERI FUZZY LOGIC - PHP NATIVE (Tsukamoto/Mamdani/Sugeno)\n";
    echo "======================================================\n";
    echo "Jalankan salah satu perintah berikut:\n\n";
    echo "  php tsukamoto/tsukamoto.php [gaji_juta] [usia]\n";
    echo "  php mamdani/mamdani.php [gaji_ribu] [tanggungan] [nilai]\n";
    echo "  php sugeno/sugeno.php [x] [y] [luas_m2] [skor_lokasi]\n\n";
    echo "Contoh (nilai default / contoh dari dokumen):\n";
    echo "  php tsukamoto/tsukamoto.php\n";
    echo "  php mamdani/mamdani.php\n";
    echo "  php sugeno/sugeno.php\n";
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Materi Fuzzy Logic - PHP Native</title>
<style>
  body{font-family:system-ui,Arial,sans-serif;max-width:760px;margin:40px auto;padding:0 16px;color:#222}
  h1{font-size:1.4rem}
  .card{border:1px solid #ddd;border-radius:8px;padding:16px;margin:16px 0}
  code{background:#f4f4f4;padding:2px 6px;border-radius:4px}
  form{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px}
  input{width:110px;padding:4px}
  button{padding:6px 14px}
</style>
</head>
<body>
<h1>Materi Fuzzy Logic (PHP Native)</h1>
<p>Implementasi Tsukamoto, Mamdani, dan Sugeno berdasarkan materi
<em>FUZZY HYBRID.pdf</em> dan <em>Magic Book Fuzzy Logic (Polije)</em>.</p>

<div class="card">
  <h2>1. Tsukamoto - Kelayakan Kredit</h2>
  <form action="tsukamoto/tsukamoto.php" method="get">
    Gaji (juta): <input name="gaji" value="7.5">
    Usia (th): <input name="usia" value="30">
    <button>Hitung</button>
  </form>
</div>

<div class="card">
  <h2>2. Mamdani - Nominal Beasiswa</h2>
  <form action="mamdani/mamdani.php" method="get">
    Gaji (ribu Rp): <input name="gaji" value="4200">
    Tanggungan: <input name="tanggungan" value="3">
    Nilai rapor: <input name="nilai" value="2.88">
    <button>Hitung</button>
  </form>
</div>

<div class="card">
  <h2>3. Sugeno - Orde-0 &amp; Orde-1 (Prediksi Harga Rumah)</h2>
  <form action="sugeno/sugeno.php" method="get">
    x: <input name="x" value="0.3">
    y: <input name="y" value="0.7">
    Luas (m2): <input name="luas" value="180">
    Skor lokasi: <input name="lokasi" value="75">
    <button>Hitung</button>
  </form>
</div>

<p>Setiap hasil perhitungan otomatis dicatat ke file <code>data/hasil.txt</code>
di masing-masing folder metode. Baca <code>README.txt</code> untuk penjelasan lengkap.</p>
</body>
</html>
