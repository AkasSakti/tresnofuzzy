<?php
/**
 * generate_excel.php
 * ---------------------------------------------------------------------
 * Membuat file Excel (.xlsx) murni dengan PHP native — memakai ekstensi
 * bawaan PHP "ZipArchive" saja, TANPA library pihak ketiga/Composer
 * (mis. PhpSpreadsheet) — untuk menjelaskan langkah perhitungan tiap
 * metode fuzzy dalam bentuk tabel, sehingga bisa dibuka & diperiksa
 * langsung di Microsoft Excel / LibreOffice Calc / Google Sheets.
 *
 * KENAPA BISA TANPA LIBRARY?
 * File .xlsx sebenarnya hanyalah sebuah arsip ZIP yang berisi beberapa
 * file XML dengan format "Office Open XML" (SpreadsheetML). Skrip ini
 * menyusun XML tersebut secara manual (workbook.xml, sheet1.xml, dst),
 * lalu memampatkannya jadi satu file .xlsx memakai ZipArchive.
 *
 * Jalankan : php generate_excel.php
 * Output   : output/perhitungan_fuzzy.xlsx
 * ---------------------------------------------------------------------
 */

/** Mengubah nomor kolom (1,2,3,...) menjadi huruf kolom Excel (A,B,C,...) */
function kolom_excel(int $n): string
{
    $huruf = '';
    while ($n > 0) {
        $sisa = ($n - 1) % 26;
        $huruf = chr(65 + $sisa) . $huruf;
        $n = intdiv($n - 1, 26);
    }
    return $huruf;
}

/** Escape teks agar aman dimasukkan ke dalam XML */
function escape_xml(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Membangun isi file XML satu sheet dari array baris.
 * Tiap baris = array cell. Tiap cell boleh berupa:
 *   - string / angka biasa
 *   - ['v' => nilai, 'bold' => true]  untuk teks tebal (judul/header)
 */
function bangun_sheet_xml(array $rows): string
{
    $out  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $out .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";
    $out .= '<sheetData>' . "\n";

    foreach ($rows as $ri => $row) {
        $r = $ri + 1;
        $out .= '<row r="' . $r . '">';
        foreach ($row as $ci => $cell) {
            $c   = $ci + 1;
            $ref = kolom_excel($c) . $r;

            $bold  = is_array($cell) && !empty($cell['bold']);
            $value = is_array($cell) ? $cell['v'] : $cell;
            $style = $bold ? ' s="1"' : ' s="0"';

            if (is_int($value) || is_float($value)) {
                $out .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
            } else {
                $out .= '<c r="' . $ref . '" t="inlineStr"' . $style . '>'
                      . '<is><t xml:space="preserve">' . escape_xml((string) $value) . '</t></is></c>';
            }
        }
        $out .= '</row>' . "\n";
    }

    $out .= '</sheetData></worksheet>';
    return $out;
}

/* =====================================================================
 * ISI DATA TIAP SHEET
 * Angka-angka di bawah adalah hasil aktual dari tsukamoto.php,
 * mamdani.php, dan sugeno.php dengan input contoh (default) masing-
 * masing skrip, sehingga file Excel ini bisa dipakai untuk mencocokkan
 * cara hitung manual dengan output program.
 * =================================================================== */

function h(string $t): array { return ['v' => $t, 'bold' => true]; }

$sheets = [];

// ---------- SHEET 1: TSUKAMOTO ----------
$sheets['Tsukamoto'] = [
    [h('METODE TSUKAMOTO - Studi Kasus Kelayakan Kredit')],
    [],
    [h('Input'), 'Gaji (juta Rp/bulan)', 7.5],
    ['', 'Usia (tahun)', 30],
    [],
    [h('1. Fuzzyfication'), h('Himpunan'), h('Rumus'), h('Hasil')],
    ['', 'gaji RENDAH', 'linear_turun(7.5; 2; 6)', 0.0000],
    ['', 'gaji TINGGI', 'linear_naik(7.5; 4; 8)', 0.8750],
    ['', 'usia MUDA',   'linear_turun(30; 25; 45)', 0.7500],
    ['', 'usia TUA',    'linear_naik(30; 35; 55)', 0.0000],
    [],
    [h('2. Inferensi (alpha = MIN anteseden, z = invers konsekuen)')],
    [h('Aturan'), h('IF'), h('alpha'), h('z (invers)')],
    ['R1', 'gaji TINGGI AND usia MUDA -> kelayakan TINGGI', 0.7500, 85.00],
    ['R2', 'gaji TINGGI AND usia TUA  -> kelayakan TINGGI', 0.0000, 40.00],
    ['R3', 'gaji RENDAH AND usia MUDA -> kelayakan RENDAH', 0.0000, 60.00],
    ['R4', 'gaji RENDAH AND usia TUA  -> kelayakan RENDAH', 0.0000, 60.00],
    [],
    [h('3. Defuzzyfication (rata-rata terbobot)')],
    ['z* = sum(alpha*z) / sum(alpha)', '= (0.75*85) / (0.75)', '=', 85.00],
];

// ---------- SHEET 2: MAMDANI ----------
$sheets['Mamdani'] = [
    [h('METODE MAMDANI - Studi Kasus Penentuan Beasiswa Prestasi')],
    [],
    [h('Input'), 'Gaji ortu (ribu Rp/bulan)', 4200],
    ['', 'Tanggungan ortu (orang)', 3],
    ['', 'Rata-rata nilai rapor', 2.88],
    [],
    [h('1. Fuzzyfication'), h('Himpunan'), h('Rumus (trapesium/bahu)'), h('Hasil')],
    ['', 'gaji BANYAK',        'trapesium(4200; 2700;3000;3500;5000)', 0.5333],
    ['', 'gaji SANGAT_BANYAK', 'bahu_kanan(4200; 3500;5000)',          0.4667],
    ['', 'tanggungan SEDIKIT', 'bahu_kiri(3; 2;4)',                    0.5000],
    ['', 'tanggungan CUKUP',   'trapesium(3; 2;4;6;8)',                0.5000],
    ['', 'nilai B',            'bahu_kiri(2.88; 3.17;3.51)',           1.0000],
    [],
    [h('2-3. Aturan Aktif (implikasi MIN, komposisi MAX)')],
    [h('Aturan'), h('IF'), h('alpha (MIN)'), h('THEN beasiswa')],
    ['R19', 'gaji BANYAK AND tanggungan SEDIKIT AND nilai B', 0.5000, 'CUKUP'],
    ['R21', 'gaji BANYAK AND tanggungan CUKUP AND nilai B',   0.5000, 'CUKUP'],
    ['R25', 'gaji SANGAT_BANYAK AND tanggungan SEDIKIT AND nilai B', 0.4667, 'SEDIKIT'],
    ['R27', 'gaji SANGAT_BANYAK AND tanggungan CUKUP AND nilai B',   0.4667, 'SEDIKIT'],
    [],
    [h('alpha_output gabungan (MAX per label)')],
    ['sedikit', 0.4667],
    ['cukup',   0.5000],
    ['banyak',  0.0000],
    [],
    [h('4. Defuzzyfication (metode CENTROID)')],
    [h('Metode'), h('Rumus'), h('Hasil (x100rb)'), h('Nominal (Rp)')],
    ['Centroid diskrit (sesuai buku)', 'z*=sum(y*mu)/sum(mu), y bulat', 5.7218, 572177],
    ['Centroid kontinu (presisi)',     'sampling step 0.01',            5.7232, 572320],
    ['Mean of Maximum (MOM)',          'rata2 y saat mu maksimum',      7.5000, 750000],
    [],
    ['Catatan:', 'Buku sumber membulatkan alpha gaji jadi 0,53 & 0,47 sebelum dihitung,', '', ''],
    ['', 'sehingga hasil manual buku = Rp571.000 (selisih tipis akibat pembulatan).', '', ''],
];

// ---------- SHEET 3: RULE BASE MAMDANI (30 aturan) ----------
$rule_rows = [[h('No'), h('Gaji Ortu'), h('Tanggungan Ortu'), h('Nilai Raport'), h('Nominal Beasiswa')]];
if (file_exists(__DIR__ . '/mamdani/data/rules.csv')) {
    $fh = fopen(__DIR__ . '/mamdani/data/rules.csv', 'r');
    fgetcsv($fh, 0, ',', '"', '\\'); // lewati header
    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        $rule_rows[] = [$row[0], $row[1], $row[2], $row[3], $row[4]];
    }
    fclose($fh);
}
$sheets['Rule Mamdani'] = $rule_rows;

// ---------- SHEET 4: SUGENO ----------
$sheets['Sugeno'] = [
    [h('METODE SUGENO')],
    [],
    [h('A. Orde-0 (konsekuen konstanta)')],
    ['Input', 'x', 0.3],
    ['', 'y', 0.7],
    [h('Aturan'), h('Rumus alpha'), h('alpha'), h('z (konstanta)')],
    ['R1: x KECIL AND y KECIL -> z=10', 'mu1=min(1-x,1-y)', 0.3000, 10],
    ['R2: x BESAR OR  y BESAR -> z=20', 'mu2=max(x,y)',     0.7000, 20],
    ['Hasil', 'z*=(mu1*z1+mu2*z2)/(mu1+mu2)', '=', 17.00],
    [],
    [h('B. Orde-1 (konsekuen linear) - Prediksi Harga Rumah')],
    ['Input', 'luas (m2)', 180],
    ['', 'skor lokasi', 75],
    [h('Aturan'), h('Formula'), h('alpha (MIN)'), h('z')],
    ['R1', 'lokasi STRATEGIS AND luas BESAR -> 1.5*luas+500', 0.5333, 770.00],
    ['R2', 'lokasi BIASA AND luas KECIL -> 1.2*luas+200',      0.0000, 416.00],
    ['Hasil', 'z*=sum(alpha*z)/sum(alpha)', '=', 770.00],
];

/* =====================================================================
 * MERAKIT FILE .XLSX (ZIP + XML)
 * =================================================================== */

$outDir = __DIR__ . '/output';
if (!is_dir($outDir)) mkdir($outDir, 0777, true);
$outFile = $outDir . '/perhitungan_fuzzy.xlsx';

if (file_exists($outFile)) unlink($outFile);

$zip = new ZipArchive();
$zip->open($outFile, ZipArchive::CREATE);

// [Content_Types].xml -> mendaftarkan semua bagian file
$overrides = '';
$i = 1;
foreach ($sheets as $nama => $rows) {
    $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" '
                . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    $i++;
}
$zip->addFromString('[Content_Types].xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
    '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
    '<Default Extension="xml" ContentType="application/xml"/>' .
    '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
    '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
    $overrides .
    '</Types>'
);

// _rels/.rels -> penunjuk ke workbook utama
$zip->addFromString('_rels/.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
    '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
    '</Relationships>'
);

// xl/workbook.xml -> daftar nama sheet
$sheetTags = '';
$i = 1;
foreach ($sheets as $nama => $rows) {
    $sheetTags .= '<sheet name="' . escape_xml($nama) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
    $i++;
}
$zip->addFromString('xl/workbook.xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' .
    'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
    '<sheets>' . $sheetTags . '</sheets></workbook>'
);

// xl/_rels/workbook.xml.rels -> penunjuk tiap sheet + styles
$relTags = '';
$i = 1;
foreach ($sheets as $nama => $rows) {
    $relTags .= '<Relationship Id="rId' . $i . '" '
              . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
              . 'Target="worksheets/sheet' . $i . '.xml"/>';
    $i++;
}
$relTags .= '<Relationship Id="rIdStyles" '
          . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" '
          . 'Target="styles.xml"/>';
$zip->addFromString('xl/_rels/workbook.xml.rels',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relTags . '</Relationships>'
);

// xl/styles.xml -> hanya 2 gaya: normal (s=0) dan tebal (s=1) untuk judul
$zip->addFromString('xl/styles.xml',
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
    '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>' .
    '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>' .
    '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>' .
    '<borders count="1"><border/></borders>' .
    '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0"/></cellStyleXfs>' .
    '<cellXfs count="2">' .
    '<xf numFmtId="0" fontId="0" xfId="0"/>' .
    '<xf numFmtId="0" fontId="1" xfId="0" applyFont="1"/>' .
    '</cellXfs></styleSheet>'
);

// xl/worksheets/sheetN.xml -> isi tabel tiap metode
$i = 1;
foreach ($sheets as $nama => $rows) {
    $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', bangun_sheet_xml($rows));
    $i++;
}

$zip->close();

echo "File Excel berhasil dibuat: $outFile\n";
echo "Berisi sheet: " . implode(', ', array_keys($sheets)) . "\n";
