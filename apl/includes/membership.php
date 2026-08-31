<?php
/**
 * membership.php
 * ---------------------------------------------------------------------
 * Kumpulan fungsi keanggotaan (membership function) fuzzy dasar.
 * Semua rumus diambil dari materi "Magic Book Fuzzy Logic" (hal. 5-8)
 * dan "FUZZY HYBRID" (hal. 1-2), yaitu:
 *   - Representasi Linear Naik & Linear Turun
 *   - Representasi Kurva Segitiga
 *   - Representasi Kurva Trapesium
 *   - Bentuk "bahu" (shoulder) kiri & kanan, yaitu kasus khusus
 *     trapesium yang salah satu sisinya rata (dipakai untuk himpunan
 *     paling ujung, misal "Sangat Sedikit" / "Sangat Banyak").
 *
 * Semua fungsi mengembalikan derajat keanggotaan (mu) pada rentang 0..1
 * sesuai definisi: 0 <= mu(x) <= 1
 * ---------------------------------------------------------------------
 */

/**
 * Representasi Linier Naik.
 * Derajat keanggotaan mulai dari 0 di titik "a" naik menuju 1 di titik "b".
 * Rumus (Magic Book hal. 6):
 *   mu(x) = 0            ; x <= a
 *   mu(x) = (x-a)/(b-a)  ; a <= x <= b
 *   mu(x) = 1            ; x >= b
 */
function linear_naik(float $x, float $a, float $b): float
{
    if ($x <= $a) return 0.0;
    if ($x >= $b) return 1.0;
    return ($x - $a) / ($b - $a);
}

/**
 * Representasi Linier Turun.
 * Kebalikan dari linear naik: derajat keanggotaan mulai dari 1 di titik
 * "a" turun menuju 0 di titik "b".
 * Rumus (Magic Book hal. 6):
 *   mu(x) = 1            ; x <= a
 *   mu(x) = (b-x)/(b-a)  ; a <= x <= b
 *   mu(x) = 0            ; x >= b
 */
function linear_turun(float $x, float $a, float $b): float
{
    if ($x <= $a) return 1.0;
    if ($x >= $b) return 0.0;
    return ($b - $x) / ($b - $a);
}

/**
 * Representasi Kurva Segitiga (triangular).
 * Titik "b" adalah puncak (mu = 1), "a" dan "c" adalah kaki kiri/kanan
 * (mu = 0). Rumus (Magic Book hal. 7):
 *   mu(x) = 0           ; x <= a atau x >= c
 *   mu(x) = (x-a)/(b-a) ; a <= x <= b
 *   mu(x) = (c-x)/(c-b) ; b <= x <= c
 */
function segitiga(float $x, float $a, float $b, float $c): float
{
    if ($x <= $a || $x >= $c) return 0.0;
    if ($x <= $b) return ($x - $a) / ($b - $a);
    return ($c - $x) / ($c - $b);
}

/**
 * Representasi Kurva Trapesium.
 * Ada "plateau" (mu = 1) antara titik "b" dan "c".
 * Rumus (Magic Book hal. 8):
 *   mu(x) = 0            ; x <= a atau x >= d
 *   mu(x) = (x-a)/(b-a)  ; a <= x <= b
 *   mu(x) = 1            ; b <= x <= c
 *   mu(x) = (d-x)/(d-c)  ; c <= x <= d
 */
function trapesium(float $x, float $a, float $b, float $c, float $d): float
{
    if ($x <= $a || $x >= $d) return 0.0;
    if ($x <= $b) return ($x - $a) / ($b - $a);
    if ($x <= $c) return 1.0;
    return ($d - $x) / ($d - $c);
}

/**
 * Bahu kiri (left shoulder).
 * Dipakai untuk himpunan fuzzy paling kiri, misal "Sangat Sedikit".
 * Nilainya rata (mu = 1) mulai dari awal semesta pembicaraan sampai
 * titik "b", lalu turun linier sampai 0 di titik "c".
 * Ini adalah kasus khusus trapesium tanpa sisi naik di kiri.
 */
function bahu_kiri(float $x, float $b, float $c): float
{
    if ($x <= $b) return 1.0;
    if ($x >= $c) return 0.0;
    return ($c - $x) / ($c - $b);
}

/**
 * Bahu kanan (right shoulder).
 * Dipakai untuk himpunan fuzzy paling kanan, misal "Sangat Banyak".
 * Nilainya 0 sampai titik "a", naik linier sampai titik "b", lalu
 * rata (mu = 1) sampai akhir semesta pembicaraan.
 * Ini adalah kasus khusus trapesium tanpa sisi turun di kanan.
 */
function bahu_kanan(float $x, float $a, float $b): float
{
    if ($x <= $a) return 0.0;
    if ($x >= $b) return 1.0;
    return ($x - $a) / ($b - $a);
}

/**
 * Operasi himpunan fuzzy (Magic Book hal. 9).
 * AND (irisan/intersection) -> MIN
 * OR  (gabungan/union)      -> MAX
 */
function fuzzy_and(float ...$mu): float
{
    return min($mu);
}

function fuzzy_or(float ...$mu): float
{
    return max($mu);
}
