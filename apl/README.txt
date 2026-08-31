====================================================================
 MATERI FUZZY LOGIC - IMPLEMENTASI PHP NATIVE
 (Tsukamoto, Mamdani, Sugeno + turunan/variannya)
====================================================================

SUMBER MATERI
- FUZZY HYBRID.pdf
  Perbandingan konsep 3 metode Fuzzy Inference System.
- Magic Book AR Fuzzy Fun Polije.pdf
  Teori himpunan fuzzy, fungsi keanggotaan, fuzzifikasi-inferensi-
  defuzzifikasi, dan studi kasus lengkap Mamdani (penentuan beasiswa).

Semua rumus & studi kasus di kode PHP ini merujuk langsung ke kedua
dokumen tersebut (nomor halaman dicantumkan di komentar tiap file),
supaya bisa dicocokkan dengan perhitungan manual di buku.

--------------------------------------------------------------------
STRUKTUR FOLDER
--------------------------------------------------------------------
apl/
 |- index.php                  Menu utama (CLI & browser)
 |- generate_excel.php         Pembuat file Excel penjelasan hitungan
 |- README.txt                 File ini
 |
 |- includes/
 |   |- membership.php         Fungsi keanggotaan dasar (dipakai bersama
 |   |                         oleh ketiga metode): linear naik/turun,
 |   |                         segitiga, trapesium, bahu kiri/kanan,
 |   |                         operator AND (min) & OR (max).
 |   |- storage.php            Fungsi baca file CSV (rule base) & tulis
 |                              file log TXT (penyimpanan hasil).
 |
 |- tsukamoto/
 |   |- tsukamoto.php          Studi kasus: kelayakan kredit
 |   |- data/rules.csv         Basis aturan (4 rule)
 |   |- data/hasil.txt         Log hasil tiap kali skrip dijalankan
 |                              (dibuat otomatis, boleh dihapus)
 |
 |- mamdani/
 |   |- mamdani.php            Studi kasus: nominal beasiswa prestasi
 |   |- data/rules.csv         Basis aturan (30 rule, identik dgn buku)
 |   |- data/hasil.txt         Log hasil
 |
 |- sugeno/
 |   |- sugeno.php             Orde-0 (konstanta) & Orde-1 (linear):
 |                              prediksi harga rumah
 |   |- data/rules.csv         Basis aturan Sugeno orde-1 (2 rule)
 |   |- data/hasil.txt         Log hasil
 |
 |- output/
     |- perhitungan_fuzzy.xlsx File Excel berisi tabel langkah hitung
                                setiap metode + rule base Mamdani

--------------------------------------------------------------------
CARA MENJALANKAN
--------------------------------------------------------------------
1) Lewat terminal (CLI), dari dalam folder apl/:

     php tsukamoto/tsukamoto.php [gaji_juta] [usia]
     php mamdani/mamdani.php [gaji_ribu] [tanggungan] [nilai_rapor]
     php sugeno/sugeno.php [x] [y] [luas_m2] [skor_lokasi]

   Contoh (memakai nilai contoh dari dokumen sumber):
     php tsukamoto/tsukamoto.php
     php mamdani/mamdani.php
     php sugeno/sugeno.php

2) Lewat browser:
     php -S localhost:8000
   lalu buka http://localhost:8000/  dan isi form pada masing-masing
   kartu metode.

3) Membuat ulang file Excel penjelasan perhitungan:
     php generate_excel.php
   Hasilnya tersimpan di output/perhitungan_fuzzy.xlsx

--------------------------------------------------------------------
PEMANFAATAN FILE SEBAGAI MEDIA PENYIMPANAN (ALUR DATA)
--------------------------------------------------------------------
Setiap algoritma punya 2 arah aliran file: MASUK (dibaca sebelum
hitung) dan KELUAR (ditulis setelah hitung). Tidak ada database,
semuanya murni file CSV/TXT di dalam folder masing-masing metode.

Alur umum (berlaku utk Tsukamoto, Mamdani, Sugeno):

   data/rules.csv                                     data/hasil.txt
   (basis aturan)                                      (log hasil)
        |                                                    ^
        |  1) dibaca oleh                                    |
        v     baca_csv()                                     |  3) ditulis oleh
   [ array PHP rule base ]                                    |     tulis_log()
        |                                                     |
        |  2) diproses oleh algoritma fuzzy                   |
        |     (fuzzyfication -> inferensi -> defuzzyfikasi)   |
        +-----------------------------------------------------+
                     hasil akhir + rincian tiap langkah

  Fungsi baca_csv() & tulis_log() ada satu tempat saja, yaitu di
  includes/storage.php, dipakai bersama oleh ketiga metode.

Rincian per algoritma (path relatif terhadap folder apl/):

  ALGORITMA   | FILE DIBACA (input)        | FILE DITULIS (output)
  ------------|-----------------------------|----------------------------
  Tsukamoto   | tsukamoto/data/rules.csv    | tsukamoto/data/hasil.txt
  (tsukamoto. |  -> 4 baris aturan gaji &   |  -> log alpha, z tiap
   php)       |     usia -> kelayakan       |     aturan + hasil akhir
  ------------|-----------------------------|----------------------------
  Mamdani     | mamdani/data/rules.csv      | mamdani/data/hasil.txt
  (mamdani.   |  -> 30 baris aturan gaji,   |  -> log fuzzyfication,
   php)       |     tanggungan, nilai ->    |     aturan aktif, 3 hasil
              |     beasiswa                |     defuzzifikasi
  ------------|-----------------------------|----------------------------
  Sugeno      | sugeno/data/rules.csv       | sugeno/data/hasil.txt
  (sugeno.    |  -> 2 baris aturan lokasi   |  -> log hasil orde-0 &
   php)       |     & luas -> formula harga |     orde-1 + rincian alpha
  ------------|-----------------------------|----------------------------
  Excel       | mamdani/data/rules.csv      | output/perhitungan_fuzzy
  (generate_  |  -> disalin ulang jadi      |   .xlsx
   excel.php) |     sheet "Rule Mamdani"    |  -> 4 sheet: Tsukamoto,
              |     (angka lain di-tulis    |     Mamdani, Rule Mamdani,
              |     langsung di kode)       |     Sugeno

Detail fungsi yang dipakai:
- baca_csv($path)  [includes/storage.php]
  Membuka file .csv, baris pertama dijadikan nama kolom (header),
  tiap baris berikutnya diubah jadi array asosiatif. TIDAK ada aturan
  fuzzy yang di-hardcode di dalam logika program -- kalau mau menambah
  atau mengubah aturan, cukup edit file rules.csv, kode PHP tidak
  perlu disentuh.
- tulis_log($path, $isi)  [includes/storage.php]
  Menambahkan (append, bukan menimpa) teks ke file .txt, dibubuhi
  stempel waktu. Folder tujuan dibuat otomatis kalau belum ada. Karena
  mode-nya append, file hasil.txt akan terus bertambah tiap kali
  skrip dijalankan -- jadi berfungsi sekaligus sebagai RIWAYAT/HISTORY
  perhitungan, bukan cuma hasil terakhir. Boleh dihapus isinya kapan
  saja tanpa memengaruhi jalannya program (akan dibuat ulang otomatis).
- ZipArchive (bawaan PHP)  [generate_excel.php]
  Menyusun file .xlsx dari nol (bukan membaca file lama), memuat 3
  sheet hasil hitung manual (angka ditulis langsung di kode mengikuti
  contoh default tiap skrip) + 1 sheet salinan rules.csv Mamdani.

--------------------------------------------------------------------
RINGKASAN METODE & VARIAN ("TURUNAN") - VERSI SANTAI ala GEN-Z
--------------------------------------------------------------------
Bayangin logika fuzzy ini kayak proses PDKT. Input-nya = "sinyal-sinyal"
yang serba abu-abu (gak 100% jelas, gak 0% jelas juga), terus tiap
metode punya gaya beda buat mutusin "worth it gak nih lanjut?".

1. TSUKAMOTO -> tipe "to the point, gak ada php-php-an"
   Begitu ada sinyal (gaji tinggi, masih muda), dia LANGSUNG kasih
   skor pasti per "kriteria" -- gak ditumpuk dulu jadi awang-awang,
   gak ada drama. Kayak circle pertemanan yang tiap kali ditanya
   "worth it gak gebetan lo?" langsung jawab pake ANGKA ("gue kasih
   85/100"), bukan jawaban ngambang "lumayan sih". Semua angka pasti
   dari tiap "temen yang dimintain pendapat" itu baru di-rata-rata
   sesuai seberapa yakin mereka (rata-rata terbobot).
   (teknis: konsekuen berupa fungsi MONOTON naik/turun, nilai z per
   aturan didapat dari invers fungsi itu pada derajat alpha, lalu
   z* = sum(alpha*z) / sum(alpha))

2. MAMDANI -> tipe "galauan dulu, baru mutusin mantep"
   Semua sinyal (gaji ortu, tanggungan, nilai rapor) dikumpulin dulu
   jadi satu "vibe" yang masih fuzzy/ngambang (belum ada angka pasti,
   masih perasaan doang) -- baru di ujung, di-"tegasin" jadi satu
   keputusan crisp. Persis kayak overthinking sebelum nembak gebetan:
   nanya-nanya dulu ke banyak sumber, baru diambil kesimpulan akhir.
   Di sini disediakan 3 gaya "cara mutusin" yang bisa dibandingin:
     a. Centroid diskrit  : rata-rata pendapat "circle" secara kasar
        (persis cara manual di buku, titik-titik bulat aja).
     b. Centroid kontinu  : lebih teliti & detail, gak asal rata-rata
        kasar -- feeling-nya lebih presisi.
     c. Mean of Maximum   : ikut yang paling DOMINAN aja / yang paling
        banyak disaranin ("mayoritas circle bilang A, ya udah A").

3. SUGENO -> tipe "template jawaban, gak pake baper"
   Begitu syaratnya kecocok, jawabannya LANGSUNG berupa rumus/angka,
   gak ada acara "diomongin dulu jadi fuzzy" kayak Mamdani.
     - Orde-0 : jawabannya konstan/template, kayak auto-reply chat
       ("gue anaknya gini doang" -- gak peduli situasinya gimana).
     - Orde-1 : jawabannya proporsional & nyesuaiin kondisi, kayak
       "makin strategis lokasi & makin luas rumahnya, makin mahal
       harganya" -- naik/turun mengikuti rumus linear, bukan angka
       tetap.

--------------------------------------------------------------------
VERSI TEKNIS (untuk yang butuh rumus persisnya)
--------------------------------------------------------------------
1. TSUKAMOTO (tsukamoto/tsukamoto.php)
   - Konsekuen (THEN) berupa fungsi MONOTON (linear naik/turun), bukan
     himpunan fuzzy biasa.
   - Nilai z tiap aturan dicari dengan MEMBALIK (invers) fungsi
     tersebut pada derajat alpha.
   - Hasil akhir = rata-rata terbobot: sum(alpha*z) / sum(alpha).

2. MAMDANI (mamdani/mamdani.php)
   - 4 tahap: fuzzyfication -> implikasi (MIN) -> komposisi aturan
     (MAX) -> defuzzyfikasi.
   - 3 varian defuzzyfikasi didemokan sekaligus untuk dibandingkan:
       a. Centroid diskrit  : persis metode manual di buku (sampling
          titik bulat pada domain dukungan tiap label).
       b. Centroid kontinu  : sampling rapat (step 0.01) yang
          memperhitungkan bentuk kurva & clipping sebenarnya -> lebih
          presisi secara geometris.
       c. Mean of Maximum (MOM) : rata-rata domain dengan derajat
          keanggotaan hasil komposisi tertinggi.

3. SUGENO (sugeno/sugeno.php)
   - Orde-0 : konsekuen berupa KONSTANTA (reproduksi contoh dokumen
     FUZZY HYBRID.pdf).
   - Orde-1 : konsekuen berupa PERSAMAAN LINEAR (dibaca dari kolom
     "formula" pada rules.csv, studi kasus prediksi harga rumah).

--------------------------------------------------------------------
CATATAN VALIDASI ANGKA
--------------------------------------------------------------------
- Mamdani: hasil program (~Rp572.177) sedikit berbeda dari hasil
  manual buku (Rp571.000) karena buku membulatkan derajat keanggotaan
  gaji jadi 0,53 & 0,47 sebelum dipakai pada langkah berikutnya,
  sedangkan program memakai nilai penuh (0,5333.../0,4667...). Rumus
  dan metodenya identik.
- Sugeno Orde-0: dengan x=0,3 & y=0,7, rumus yang tertulis di dokumen
  sumber (mu1=min(1-x,1-y), mu2=max(x,y)) secara matematis menghasilkan
  17,0 -- bukan 16,923076923076923 seperti tercetak di dokumen (diduga
  salah cetak). Kode di sini mengikuti rumus apa adanya.
