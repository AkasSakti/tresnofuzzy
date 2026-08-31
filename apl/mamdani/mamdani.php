<?php
/**
 * mamdani.php
 * ---------------------------------------------------------------------
 * STUDI KASUS: Penentuan Nominal Beasiswa Prestasi (Metode Mamdani)
 * Sumber: "Magic Book AR Fuzzy Fun Polije" hal. 18-31.
 * Angka pada contoh di bawah (gaji Rp4.200.000, tanggungan 3 orang,
 * nilai rapor 2,88) SENGAJA disamakan dengan contoh pada buku tersebut,
 * sehingga hasil akhir program ini bisa dicocokkan dengan perhitungan
 * manual pada halaman 27-31 buku (hasil buku: Rp571.000).
 *
 * CATATAN PRESISI: buku membulatkan derajat keanggotaan gaji menjadi
 * 0,53 dan 0,47 sebelum dipakai pada langkah berikutnya, sedangkan
 * program ini memakai nilai penuh (0,5333... dan 0,4667...). Akibatnya
 * hasil program (~Rp572.177) sedikit berbeda dari hasil manual buku
 * (Rp571.000) walau metodenya identik -- ini murni efek pembulatan
 * bertingkat pada perhitungan manual, bukan perbedaan rumus.
 *
 * 4 tahap Metode Mamdani (Magic Book hal. 16):
 *   1. Pembentukan himpunan fuzzy (fuzzyfication)
 *   2. Aplikasi fungsi implikasi -> MIN
 *   3. Komposisi aturan -> MAX (menggabungkan semua aturan yang aktif)
 *   4. Penegasan / defuzzifikasi -> metode CENTROID
 *
 * Variabel & Himpunan fuzzy (Magic Book hal. 19-23), satuan gaji &
 * beasiswa dalam ribuan/ratus-ribuan rupiah sesuai tabel buku:
 *   GAJI ORTU     : sangat_sedikit, sedikit, cukup, banyak, sangat_banyak
 *   TANGGUNGAN    : sedikit, cukup, banyak
 *   NILAI RAPOR   : B, A
 *   BEASISWA(out) : sedikit, cukup, banyak   (x Rp100.000)
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/storage.php';

/* =====================================================================
 * 1. FUZZYFICATION
 *    Rumus persis mengikuti Magic Book hal. 20-23 (fungsi trapesium
 *    dan bentuk "bahu" di kedua ujung semesta pembicaraan).
 * =================================================================== */

/** Derajat keanggotaan variabel GAJI ORANG TUA (ribu rupiah/bulan) */
function mu_gaji(float $x): array
{
    return [
        'sangat_sedikit' => bahu_kiri($x, 500, 800),          // hal.20
        'sedikit'        => trapesium($x, 500, 800, 1200, 1500),
        'cukup'          => trapesium($x, 1200, 1500, 2700, 3000), // hal.21
        'banyak'         => trapesium($x, 2700, 3000, 3500, 5000),
        'sangat_banyak'  => bahu_kanan($x, 3500, 5000),
    ];
}

/** Derajat keanggotaan variabel JUMLAH TANGGUNGAN ORANG TUA (orang) */
function mu_tanggungan(float $x): array
{
    return [
        'sedikit' => bahu_kiri($x, 2, 4),          // hal.21
        'cukup'   => trapesium($x, 2, 4, 6, 8),
        'banyak'  => bahu_kanan($x, 6, 8),
    ];
}

/** Derajat keanggotaan variabel RATA-RATA NILAI RAPOR */
function mu_nilai(float $x): array
{
    return [
        'B' => bahu_kiri($x, 3.17, 3.51),   // hal.22
        'A' => bahu_kanan($x, 3.17, 3.51),
    ];
}

/**
 * Bentuk kurva himpunan fuzzy OUTPUT (nominal beasiswa, x Rp100.000),
 * dipakai untuk metode defuzzifikasi centroid kontinu (fungsi bonus).
 */
function bentuk_beasiswa(string $label, float $y): float
{
    return match ($label) {
        'sedikit' => bahu_kiri($y, 3, 6),        // hal.23
        'cukup'   => trapesium($y, 3, 6, 9, 12),
        'banyak'  => bahu_kanan($y, 9, 12),
    };
}

/** Batas dukungan (support) tiap label output, dipakai metode diskrit */
const SUPPORT_BEASISWA = [
    'sedikit' => [0, 6],
    'cukup'   => [3, 12],
    'banyak'  => [9, 15],
];

/* =====================================================================
 * 2 & 3. APLIKASI FUNGSI IMPLIKASI (MIN) & KOMPOSISI ATURAN (MAX)
 * =================================================================== */

/**
 * Menjalankan seluruh rule base (30 aturan dari data/rules.csv) untuk
 * satu set input, lalu mengembalikan:
 *   - detail tiap aturan yang alpha-nya > 0 (aturan "menyala")
 *   - alpha_predikat gabungan tiap label output (hasil MAX / disjungsi)
 */
function inferensi_mamdani(array $mg, array $mt, array $mn): array
{
    $rules = baca_csv(__DIR__ . '/data/rules.csv');

    $alpha_output = ['sedikit' => 0.0, 'cukup' => 0.0, 'banyak' => 0.0];
    $aktif = [];

    foreach ($rules as $r) {
        // Fungsi implikasi Mamdani = MIN (irisan antar anteseden)
        $alpha = fuzzy_and($mg[$r['gaji']], $mt[$r['tanggungan']], $mn[$r['nilai']]);

        if ($alpha > 0) {
            $aktif[] = [
                'id'    => $r['id'],
                'gaji'  => $r['gaji'],
                'tanggungan' => $r['tanggungan'],
                'nilai' => $r['nilai'],
                'beasiswa' => $r['beasiswa'],
                'alpha' => $alpha,
            ];
            // Komposisi aturan Mamdani = MAX (gabungan antar aturan
            // yang menuju label output yang sama)
            $alpha_output[$r['beasiswa']] = fuzzy_or($alpha_output[$r['beasiswa']], $alpha);
        }
    }

    return ['aktif' => $aktif, 'alpha_output' => $alpha_output];
}

/* =====================================================================
 * 4. DEFUZZYFICATION
 * =================================================================== */

/**
 * Metode CENTROID versi DISKRIT, persis seperti dicontohkan di Magic
 * Book hal. 31: setiap label output "dipukul rata" setinggi alpha-nya
 * di sepanjang domain dukungannya, lalu dijumlahkan per titik bulat
 * (integer). Rumus:  z* = sum(y * mu(y)) / sum(mu(y))
 * Ini pendekatan pengajaran (bukan integral penuh) sehingga hasilnya
 * bisa dicocokkan langsung dengan perhitungan manual di buku.
 */
function defuzzy_centroid_diskrit(array $alpha_output): float
{
    $num = 0.0;
    $den = 0.0;

    foreach ($alpha_output as $label => $alpha) {
        if ($alpha <= 0) continue;
        [$awal, $akhir] = SUPPORT_BEASISWA[$label];
        $titik = range($awal, $akhir); // titik bulat 0,1,2,...,n
        $num += $alpha * array_sum($titik);
        $den += $alpha * count($titik);
    }

    return $den > 0 ? $num / $den : 0.0;
}

/**
 * TURUNAN/VARIAN 1: Metode CENTROID kontinu (sampling rapat, step
 * kecil) yang memperhitungkan bentuk kurva sebenarnya (trapesium/bahu)
 * yang "dipotong" (clipping) pada ketinggian alpha, bukan disamaratakan
 * seperti versi diskrit di atas. Hasilnya sedikit berbeda dari versi
 * buku karena versi ini lebih presisi secara geometris.
 */
function defuzzy_centroid_kontinu(array $alpha_output, float $step = 0.01): float
{
    $num = 0.0;
    $den = 0.0;

    for ($y = 0.0; $y <= 15.0; $y += $step) {
        $mu_agregat = 0.0;
        foreach ($alpha_output as $label => $alpha) {
            if ($alpha <= 0) continue;
            // clipping: nilai kurva dipotong maksimal setinggi alpha
            $terpotong = min($alpha, bentuk_beasiswa($label, $y));
            $mu_agregat = max($mu_agregat, $terpotong); // komposisi MAX
        }
        $num += $y * $mu_agregat;
        $den += $mu_agregat;
    }

    return $den > 0 ? $num / $den : 0.0;
}

/**
 * TURUNAN/VARIAN 2: Metode Mean of Maximum (MOM) - Magic Book hal. 18.
 * Solusi crisp = rata-rata domain yang memiliki nilai keanggotaan
 * (hasil agregasi/komposisi) MAKSIMUM.
 */
function defuzzy_mom(array $alpha_output, float $step = 0.01): float
{
    $terbaik = 0.0;
    $titik_maks = [];

    for ($y = 0.0; $y <= 15.0; $y += $step) {
        $mu_agregat = 0.0;
        foreach ($alpha_output as $label => $alpha) {
            if ($alpha <= 0) continue;
            $terpotong = min($alpha, bentuk_beasiswa($label, $y));
            $mu_agregat = max($mu_agregat, $terpotong);
        }

        if ($mu_agregat > $terbaik + 1e-9) {
            $terbaik = $mu_agregat;
            $titik_maks = [$y];
        } elseif (abs($mu_agregat - $terbaik) < 1e-9 && $mu_agregat > 0) {
            $titik_maks[] = $y;
        }
    }

    return count($titik_maks) ? array_sum($titik_maks) / count($titik_maks) : 0.0;
}

/* =====================================================================
 * EKSEKUSI PROGRAM
 * CLI  : php mamdani.php 4200 3 2.88
 * Web  : mamdani.php?gaji=4200&tanggungan=3&nilai=2.88
 * (gaji dalam satuan ribu rupiah, sesuai tabel buku)
 * =================================================================== */
if (php_sapi_name() === 'cli') {
    $gaji_x       = isset($argv[1]) ? (float) $argv[1] : 4200;
    $tanggungan_x = isset($argv[2]) ? (float) $argv[2] : 3;
    $nilai_x      = isset($argv[3]) ? (float) $argv[3] : 2.88;
} else {
    $gaji_x       = isset($_GET['gaji']) ? (float) $_GET['gaji'] : 4200;
    $tanggungan_x = isset($_GET['tanggungan']) ? (float) $_GET['tanggungan'] : 3;
    $nilai_x      = isset($_GET['nilai']) ? (float) $_GET['nilai'] : 2.88;
}

$mg = mu_gaji($gaji_x);
$mt = mu_tanggungan($tanggungan_x);
$mn = mu_nilai($nilai_x);

$infer = inferensi_mamdani($mg, $mt, $mn);

$z_diskrit = defuzzy_centroid_diskrit($infer['alpha_output']);
$z_kontinu = defuzzy_centroid_kontinu($infer['alpha_output']);
$z_mom     = defuzzy_mom($infer['alpha_output']);

cetak('METODE MAMDANI - Studi Kasus Penentuan Beasiswa Prestasi');
garis();
cetak("Input: gaji ortu = Rp " . number_format($gaji_x * 1000, 0, ',', '.') .
      "/bulan, tanggungan = {$tanggungan_x} orang, nilai rapor = {$nilai_x}");
garis();

cetak('1) Fuzzyfication (derajat keanggotaan tiap himpunan):');
foreach ($mg as $k => $v) if ($v > 0) cetak(sprintf('   gaji.%-15s = %.4f', $k, $v));
foreach ($mt as $k => $v) if ($v > 0) cetak(sprintf('   tanggungan.%-9s = %.4f', $k, $v));
foreach ($mn as $k => $v) if ($v > 0) cetak(sprintf('   nilai.%-14s = %.4f', $k, $v));
garis();

cetak('2-3) Aturan yang aktif (alpha-predikat via MIN, komposisi via MAX):');
foreach ($infer['aktif'] as $a) {
    cetak(sprintf(
        '   [%s] gaji=%s AND tanggungan=%s AND nilai=%s -> beasiswa %s | alpha = %.4f',
        $a['id'], $a['gaji'], $a['tanggungan'], $a['nilai'], strtoupper($a['beasiswa']), $a['alpha']
    ));
}
garis();
cetak('   alpha_output gabungan (setelah MAX per label):');
foreach ($infer['alpha_output'] as $label => $alpha) {
    cetak(sprintf('     %-8s = %.4f', $label, $alpha));
}
garis();

cetak('4) Defuzzyfication:');
cetak(sprintf('   Centroid (diskrit, sesuai contoh buku) = %.4f x 100.000 = Rp %s',
    $z_diskrit, number_format(round($z_diskrit * 100000), 0, ',', '.')));
cetak(sprintf('   Centroid (kontinu, presisi kurva)      = %.4f x 100.000 = Rp %s',
    $z_kontinu, number_format(round($z_kontinu * 100000), 0, ',', '.')));
cetak(sprintf('   Mean of Maximum (MOM)                  = %.4f x 100.000 = Rp %s',
    $z_mom, number_format(round($z_mom * 100000), 0, ',', '.')));
garis();
cetak(sprintf('>> Nominal beasiswa (metode buku) = Rp %s per semester',
    number_format(round($z_diskrit * 100000), 0, ',', '.')));

// Simpan hasil ke file log .txt
$log  = "Input: gaji={$gaji_x} (ribu), tanggungan={$tanggungan_x}, nilai={$nilai_x}\n";
foreach ($infer['aktif'] as $a) {
    $log .= sprintf("[%s] beasiswa=%s alpha=%.4f\n", $a['id'], $a['beasiswa'], $a['alpha']);
}
$log .= sprintf("Centroid diskrit=%.4f kontinu=%.4f MOM=%.4f\n", $z_diskrit, $z_kontinu, $z_mom);
$log .= sprintf("Hasil akhir: Rp %s\n", number_format(round($z_diskrit * 100000), 0, ',', '.'));
tulis_log(__DIR__ . '/data/hasil.txt', $log);

cetak('');
cetak('(hasil telah disimpan ke mamdani/data/hasil.txt)');
