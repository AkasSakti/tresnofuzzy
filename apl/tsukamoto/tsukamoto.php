<?php
/**
 * tsukamoto.php
 * ---------------------------------------------------------------------
 * STUDI KASUS: Kelayakan Kredit (Metode Tsukamoto)
 * Sumber teori: "FUZZY HYBRID.pdf" bab 1 - Fuzzy Tsukamoto.
 *
 * Ciri khas Tsukamoto (sesuai dokumen): "setiap aturan menghasilkan
 * output crisp (tegas), karena fungsi keanggotaan KONSEKUEN harus
 * MONOTON. Hasil akhir diperoleh dengan rata-rata terbobot."
 *
 * Artinya berbeda dengan Mamdani, konsekuen (bagian THEN) tiap aturan
 * di Tsukamoto BUKAN himpunan fuzzy biasa, melainkan fungsi keanggotaan
 * naik/turun (linear_naik / linear_turun). Nilai crisp z per aturan
 * didapat dengan membalik (invers) fungsi tersebut pada derajat alpha
 * hasil komposisi anteseden (bagian IF).
 *
 * Studi kasus:
 *   Input : gaji orang tua (juta rupiah/bulan), usia (tahun)
 *   Output: skor kelayakan kredit (0 - 100)
 *
 * Variabel & himpunan fuzzy:
 *   GAJI       : rendah (turun 2..6), tinggi (naik 4..8)      -> juta
 *   USIA       : muda   (turun 25..45), tua (naik 35..55)     -> tahun
 *   KELAYAKAN  : rendah (turun 0..60, artinya nilai TINGGI di kiri),
 *                tinggi (naik 40..100)
 *
 * Aturan (dibaca dari data/rules.csv, "turunan" konsep rule-base
 * generik seperti pada Mamdani/Sugeno, tetapi konsekuennya monoton):
 *   [R1] IF gaji TINGGI AND usia MUDA THEN kelayakan TINGGI
 *   [R2] IF gaji TINGGI AND usia TUA  THEN kelayakan TINGGI
 *   [R3] IF gaji RENDAH AND usia MUDA THEN kelayakan RENDAH
 *   [R4] IF gaji RENDAH AND usia TUA  THEN kelayakan RENDAH
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/storage.php';

/**
 * Parameter konsekuen (output) untuk tiap label, beserta arah
 * monotonnya. Ini yang membedakan Tsukamoto dari Mamdani: konsekuen
 * bukan bentuk segitiga/trapesium, tapi linear naik/turun yang bisa
 * "dibalik" (inverse) untuk mendapatkan z dari sebuah alpha.
 */
const KELAYAKAN_TINGGI = ['arah' => 'naik', 'a' => 40, 'b' => 100];
const KELAYAKAN_RENDAH = ['arah' => 'turun', 'a' => 0, 'b' => 60];

/**
 * Mencari nilai z (crisp) dari derajat keanggotaan alpha dengan
 * membalik fungsi linear naik/turun.
 *   - Jika naik : alpha = (z-a)/(b-a)  ->  z = a + alpha*(b-a)
 *   - Jika turun: alpha = (b-z)/(b-a)  ->  z = b - alpha*(b-a)
 */
function invers_konsekuen(float $alpha, array $param): float
{
    ['arah' => $arah, 'a' => $a, 'b' => $b] = $param;
    if ($arah === 'naik') {
        return $a + $alpha * ($b - $a);
    }
    return $b - $alpha * ($b - $a);
}

function konsekuen_dari_label(string $label): array
{
    return $label === 'tinggi' ? KELAYAKAN_TINGGI : KELAYAKAN_RENDAH;
}

/**
 * Fungsi utama: menghitung skor kelayakan kredit dengan metode Tsukamoto.
 * Mengembalikan array berisi rincian tiap aturan + hasil akhir, supaya
 * bisa ditampilkan dan sekaligus disimpan ke file log.
 */
function hitung_tsukamoto(float $gaji, float $usia): array
{
    // 1) FUZZYFICATION - ubah input crisp menjadi derajat keanggotaan
    $mu_gaji_rendah = linear_turun($gaji, 2, 6);
    $mu_gaji_tinggi = linear_naik($gaji, 4, 8);
    $mu_usia_muda   = linear_turun($usia, 25, 45);
    $mu_usia_tua    = linear_naik($usia, 35, 55);

    $mu = [
        'gaji_rendah' => $mu_gaji_rendah,
        'gaji_tinggi' => $mu_gaji_tinggi,
        'usia_muda'   => $mu_usia_muda,
        'usia_tua'    => $mu_usia_tua,
    ];

    // 2) BACA RULE BASE dari file CSV (media penyimpanan aturan)
    $rules = baca_csv(__DIR__ . '/data/rules.csv');

    // 3) INFERENSI - hitung alpha-predikat (AND -> MIN) dan z tiap aturan
    $detail = [];
    $total_alpha_z = 0.0;
    $total_alpha   = 0.0;

    foreach ($rules as $r) {
        $mu_gaji = $mu['gaji_' . $r['gaji']];
        $mu_usia = $mu['usia_' . $r['usia']];

        $alpha = fuzzy_and($mu_gaji, $mu_usia); // operator AND -> MIN

        $param = konsekuen_dari_label($r['beasiswa'] ?? $r['kelayakan']);
        $z = invers_konsekuen($alpha, $param);

        $detail[] = [
            'id'    => $r['id'],
            'alpha' => $alpha,
            'z'     => $z,
        ];

        $total_alpha_z += $alpha * $z;
        $total_alpha   += $alpha;
    }

    // 4) DEFUZZYFICATION Tsukamoto = rata-rata terbobot (weighted average)
    //    z* = sum(alpha_i * z_i) / sum(alpha_i)
    $hasil = $total_alpha > 0 ? $total_alpha_z / $total_alpha : 0.0;

    return [
        'mu'     => $mu,
        'detail' => $detail,
        'hasil'  => $hasil,
    ];
}

// ------------------------------------------------------------------
// EKSEKUSI PROGRAM
// Bisa dijalankan lewat CLI: php tsukamoto.php 7.5 30
// atau lewat browser dengan query string: tsukamoto.php?gaji=7.5&usia=30
// ------------------------------------------------------------------
if (php_sapi_name() === 'cli') {
    $gaji = isset($argv[1]) ? (float) $argv[1] : 7.5; // juta rupiah
    $usia = isset($argv[2]) ? (float) $argv[2] : 30;  // tahun
} else {
    $gaji = isset($_GET['gaji']) ? (float) $_GET['gaji'] : 7.5;
    $usia = isset($_GET['usia']) ? (float) $_GET['usia'] : 30;
}

$out = hitung_tsukamoto($gaji, $usia);

cetak('METODE TSUKAMOTO - Studi Kasus Kelayakan Kredit');
garis();
cetak("Input  : gaji = Rp {$gaji} juta/bulan, usia = {$usia} tahun");
garis();
cetak('Derajat keanggotaan (fuzzyfication):');
foreach ($out['mu'] as $nama => $nilai) {
    cetak(sprintf('  mu_%-14s = %.4f', $nama, $nilai));
}
garis();
cetak('Inferensi tiap aturan (alpha-predikat & z invers):');
foreach ($out['detail'] as $d) {
    cetak(sprintf('  [%s] alpha = %.4f  ->  z = %.2f', $d['id'], $d['alpha'], $d['z']));
}
garis();
cetak(sprintf('Hasil akhir (rata-rata terbobot) = %.2f', $out['hasil']));

// Simpan hasil ke file log .txt (pemanfaatan file sebagai penyimpanan)
$log = "Input: gaji=$gaji juta, usia=$usia tahun\n";
foreach ($out['detail'] as $d) {
    $log .= sprintf("[%s] alpha=%.4f z=%.2f\n", $d['id'], $d['alpha'], $d['z']);
}
$log .= sprintf("Hasil akhir: %.2f\n", $out['hasil']);
tulis_log(__DIR__ . '/data/hasil.txt', $log);

cetak('');
cetak('(hasil telah disimpan ke tsukamoto/data/hasil.txt)');
