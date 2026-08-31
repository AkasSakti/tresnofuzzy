<?php
/**
 * storage.php
 * ---------------------------------------------------------------------
 * Pemanfaatan file (txt/csv) sebagai media penyimpanan sederhana.
 * Dipakai oleh tsukamoto.php, mamdani.php, dan sugeno.php untuk:
 *   1. Membaca basis aturan (rule base) dari file .csv, sehingga aturan
 *      fuzzy tidak perlu ditulis ulang (hard-code) di dalam kode PHP.
 *   2. Menulis hasil setiap perhitungan ke file .txt sebagai log/riwayat,
 *      supaya bisa diperiksa ulang tanpa harus menjalankan skrip lagi.
 * ---------------------------------------------------------------------
 */

/**
 * Membaca file CSV (baris pertama = header) menjadi array asosiatif.
 * Contoh isi rules.csv:
 *   gaji,tanggungan,nilai,beasiswa
 *   sangat_sedikit,sedikit,B,banyak
 * akan menjadi:
 *   [ ['gaji'=>'sangat_sedikit','tanggungan'=>'sedikit', ...], ... ]
 */
function baca_csv(string $path): array
{
    if (!file_exists($path)) {
        throw new RuntimeException("File CSV tidak ditemukan: $path");
    }

    $rows = [];
    $handle = fopen($path, 'r');
    // PHP 8.5: parameter $escape wajib disebutkan eksplisit agar tidak
    // memicu peringatan "deprecated" (default lama = backslash "\\").
    $header = fgetcsv($handle, 0, ',', '"', '\\');

    while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        // lewati baris kosong di akhir file
        if ($line === [null] || $line === false) {
            continue;
        }
        $rows[] = array_combine($header, $line);
    }
    fclose($handle);

    return $rows;
}

/**
 * Menulis teks ke file log .txt (mode append), dilengkapi timestamp.
 * Folder tujuan dibuat otomatis apabila belum ada.
 */
function tulis_log(string $path, string $isi): void
{
    $folder = dirname($path);
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $waktu = date('Y-m-d H:i:s');
    $baris = "===== $waktu =====\n" . $isi . "\n\n";

    file_put_contents($path, $baris, FILE_APPEND | LOCK_EX);
}

/**
 * Helper cetak: menampilkan array/nilai secara rapi baik di CLI
 * (php nama_file.php) maupun saat diakses lewat browser.
 */
function cetak(string $teks): void
{
    if (php_sapi_name() === 'cli') {
        echo $teks . "\n";
    } else {
        echo nl2br(htmlspecialchars($teks)) . "<br>\n";
    }
}

function garis(): void
{
    cetak(str_repeat('-', 70));
}
