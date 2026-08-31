<?php
/**
 * sugeno.php
 * ---------------------------------------------------------------------
 * METODE SUGENO
 * Sumber: "FUZZY HYBRID.pdf" bab 3 - Fuzzy Sugeno.
 * Ciri khas: "konsekuen berbentuk persamaan linear atau konstanta ->
 * hasil akhir langsung berupa angka tegas (mirip regresi)."
 *
 * Ada 2 varian ("turunan") Sugeno yang didemokan di sini:
 *   A. Sugeno Orde-0 (konsekuen berupa KONSTANTA)
 *      -> reproduksi persis contoh fungsi sugeno(x,y) di dokumen
 *         (hal. 6-7), x=0.3 & y=0.7 harus menghasilkan 16.923...
 *   B. Sugeno Orde-1 (konsekuen berupa PERSAMAAN LINEAR)
 *      -> studi kasus prediksi harga rumah (hal. 5-6), dengan aturan
 *         dibaca dari data/rules.csv sehingga formula tidak di-hardcode.
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/storage.php';

/* =====================================================================
 * A. SUGENO ORDE-0 (konstanta)
 * =================================================================== */

/**
 * Reproduksi persis contoh dokumen:
 *   Rule 1: IF x kecil AND y kecil THEN z = 10
 *   Rule 2: IF x besar OR  y besar THEN z = 20
 * mu1 = min(1-x, 1-y)   -> derajat "kecil dan kecil"
 * mu2 = max(x, y)       -> derajat "besar atau besar"
 * z   = (mu1*z1 + mu2*z2) / (mu1 + mu2)
 *
 * CATATAN: dengan x=0.3, y=0.7 dokumen sumber mencantumkan hasil
 * 16.923076923076923. Namun jika rumus di atas dihitung manual:
 * mu1=min(0.7,0.3)=0.3, mu2=max(0.3,0.7)=0.7,
 * z=(0.3*10+0.7*20)/(0.3+0.7) = 17/1 = 17.0 (bukan 16.92...).
 * Kemungkinan besar itu salah cetak pada dokumen sumber; kode di
 * bawah ini mengikuti RUMUS yang tertulis apa adanya (bukan angka
 * hasil akhirnya), sehingga nilai yang benar secara matematis adalah
 * 17.0, seperti yang akan tercetak saat program dijalankan.
 */
function sugeno_orde0(float $x, float $y): array
{
    $mu1 = min(1 - $x, 1 - $y);
    $mu2 = max($x, $y);
    $z1 = 10;
    $z2 = 20;

    $z = ($mu1 * $z1 + $mu2 * $z2) / ($mu1 + $mu2);

    return ['mu1' => $mu1, 'mu2' => $mu2, 'z1' => $z1, 'z2' => $z2, 'hasil' => $z];
}

/* =====================================================================
 * B. SUGENO ORDE-1 (linear) - Studi kasus prediksi harga rumah
 * =================================================================== */

/** Derajat keanggotaan variabel LUAS BANGUNAN (meter persegi) */
function mu_luas(float $x): array
{
    return [
        'kecil' => linear_turun($x, 60, 150),
        'besar' => linear_naik($x, 100, 250),
    ];
}

/** Derajat keanggotaan variabel SKOR LOKASI (0-100, makin tinggi makin strategis) */
function mu_lokasi(float $x): array
{
    return [
        'biasa'     => linear_turun($x, 30, 60),
        'strategis' => linear_naik($x, 50, 90),
    ];
}

/**
 * Menghitung z (konsekuen) tiap aturan dari kolom "formula" pada CSV,
 * misal "1.5*luas+500" dievaluasi dengan nilai luas crisp saat ini.
 * Dipakai supaya rumus linear tidak perlu ditulis ulang di kode PHP,
 * cukup diubah lewat file data/rules.csv.
 */
function evaluasi_formula(string $formula, float $luas): float
{
    // formula hanya berisi angka, operator +-*, dan kata "luas" -> aman dievaluasi manual
    $ekspresi = str_replace('luas', (string) $luas, $formula);

    // parser sederhana: pisahkan penjumlahan, lalu kalikan tiap suku
    $suku = explode('+', $ekspresi);
    $total = 0.0;
    foreach ($suku as $s) {
        $faktor = array_map('floatval', explode('*', $s));
        $total += array_product($faktor);
    }
    return $total;
}

function hitung_sugeno_orde1(float $luas, float $skor_lokasi): array
{
    $ml = mu_luas($luas);
    $mk = mu_lokasi($skor_lokasi);

    $rules = baca_csv(__DIR__ . '/data/rules.csv');

    $detail = [];
    $num = 0.0;
    $den = 0.0;

    foreach ($rules as $r) {
        $alpha = fuzzy_and($mk[$r['lokasi']], $ml[$r['luas']]); // AND -> MIN
        $z = evaluasi_formula($r['formula'], $luas);

        $detail[] = ['id' => $r['id'], 'alpha' => $alpha, 'z' => $z, 'formula' => $r['formula']];

        $num += $alpha * $z;
        $den += $alpha;
    }

    $hasil = $den > 0 ? $num / $den : 0.0;

    return ['mu_luas' => $ml, 'mu_lokasi' => $mk, 'detail' => $detail, 'hasil' => $hasil];
}

/* =====================================================================
 * EKSEKUSI PROGRAM
 * =================================================================== */
if (php_sapi_name() === 'cli') {
    $x = isset($argv[1]) ? (float) $argv[1] : 0.3;
    $y = isset($argv[2]) ? (float) $argv[2] : 0.7;
    $luas   = isset($argv[3]) ? (float) $argv[3] : 180;
    $lokasi = isset($argv[4]) ? (float) $argv[4] : 75;
} else {
    $x = isset($_GET['x']) ? (float) $_GET['x'] : 0.3;
    $y = isset($_GET['y']) ? (float) $_GET['y'] : 0.7;
    $luas   = isset($_GET['luas']) ? (float) $_GET['luas'] : 180;
    $lokasi = isset($_GET['lokasi']) ? (float) $_GET['lokasi'] : 75;
}

cetak('METODE SUGENO');
garis();

cetak('A) Sugeno Orde-0 (konsekuen konstanta) - reproduksi contoh dokumen');
$o0 = sugeno_orde0($x, $y);
cetak("   Input: x = {$x}, y = {$y}");
cetak(sprintf('   mu1 (kecil & kecil) = %.4f  ->  z1 = %d', $o0['mu1'], $o0['z1']));
cetak(sprintf('   mu2 (besar | besar) = %.4f  ->  z2 = %d', $o0['mu2'], $o0['z2']));
cetak(sprintf('   Output Sugeno = %.6f', $o0['hasil']));
garis();

cetak('B) Sugeno Orde-1 (konsekuen linear) - studi kasus prediksi harga rumah');
$o1 = hitung_sugeno_orde1($luas, $lokasi);
cetak("   Input: luas = {$luas} m2, skor lokasi = {$lokasi}");
foreach ($o1['mu_luas'] as $k => $v) cetak(sprintf('   mu_luas.%-6s   = %.4f', $k, $v));
foreach ($o1['mu_lokasi'] as $k => $v) cetak(sprintf('   mu_lokasi.%-4s = %.4f', $k, $v));
cetak('   Aturan & konsekuen (z dihitung dari formula di rules.csv):');
foreach ($o1['detail'] as $d) {
    cetak(sprintf('     [%s] formula=%-16s alpha=%.4f  ->  z=%.2f juta',
        $d['id'], $d['formula'], $d['alpha'], $d['z']));
}
cetak(sprintf('   Prediksi harga rumah = Rp %s juta', number_format($o1['hasil'], 2, ',', '.')));
garis();

// Simpan hasil ke file log .txt
$log  = "Orde-0: x=$x y=$y -> hasil=" . round($o0['hasil'], 6) . "\n";
$log .= "Orde-1: luas={$luas}m2 lokasi={$lokasi} -> prediksi=" . round($o1['hasil'], 2) . " juta\n";
foreach ($o1['detail'] as $d) {
    $log .= sprintf("  [%s] alpha=%.4f z=%.2f\n", $d['id'], $d['alpha'], $d['z']);
}
tulis_log(__DIR__ . '/data/hasil.txt', $log);

cetak('(hasil telah disimpan ke sugeno/data/hasil.txt)');
