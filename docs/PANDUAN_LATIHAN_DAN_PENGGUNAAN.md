# Panduan Latihan dan Penggunaan Aplikasi

## Sistem Rekomendasi Pemesanan Stok Toko Barokah

Dokumen ini ditujukan untuk latihan operator, demonstrasi aplikasi, pengujian skripsi, dan penggunaan harian. Alur yang dipakai adalah:

```text
Siapkan data stok -> Validasi/import -> Atur parameter -> Jalankan proses
-> Periksa hasil -> Tentukan pemesanan -> Ekspor laporan
```

Perhitungan model dilakukan langsung oleh Laravel menggunakan PHP. Aplikasi tidak membutuhkan FastAPI, layanan HTTP machine learning, atau runtime Python.

## 1. Tujuan latihan

Setelah menyelesaikan panduan ini, pengguna diharapkan mampu:

1. masuk ke panel administrasi;
2. memeriksa kesiapan data dan artifact model;
3. mengimpor histori stok dari CSV;
4. mengatur parameter persediaan global atau per produk;
5. menjalankan rekomendasi melalui web atau terminal;
6. membaca probabilitas model, trigger persediaan, dan jumlah pesan;
7. mengenali data yang belum cukup atau proses yang gagal; dan
8. mengekspor hasil rekomendasi ke CSV.

## 2. Peran pengguna

| Peran | Kemampuan utama |
| --- | --- |
| Administrator | Mengelola pengguna, kategori, produk, histori stok, parameter, import CSV, proses rekomendasi, hasil, dan ekspor. |
| Pemilik | Melihat data operasional dan hasil, menjalankan atau mengulangi rekomendasi, tetapi tidak mengubah data master atau melakukan import. |

Menu dan tombol hanya ditampilkan bila peran pengguna memiliki izin yang sesuai.

## 3. Persiapan aplikasi lokal

Pastikan `.env` telah berisi koneksi MySQL, `APP_KEY`, dan password admin. Untuk instalasi pertama:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan ml:model-info
php artisan ml:verify-parity
php artisan serve
```

Buka `http://127.0.0.1:8000/admin` kemudian masuk menggunakan akun yang dikonfigurasi melalui:

```dotenv
SEED_ADMIN_NAME="Administrator Toko Barokah"
SEED_ADMIN_EMAIL=admin@tokobarokah.test
SEED_ADMIN_PASSWORD=isi-password-yang-aman
```

Jangan memakai password fallback pada server yang dapat diakses orang lain.

### Menyiapkan database latihan

Perintah berikut menghapus seluruh tabel dan data pada database yang sedang aktif. Gunakan hanya pada database lokal/latihan:

```powershell
php artisan migrate:fresh --seed
```

Hasil seed canonical yang benar:

| Pemeriksaan | Nilai |
| --- | ---: |
| Kategori | 21 |
| Produk aktif | 78 |
| Histori stok | 27.507 |
| Tanggal awal | 1 Agustus 2024 |
| Tanggal akhir | 31 Agustus 2025 |

Apabila tanggal aplikasi jauh lebih baru daripada 31 Agustus 2025, kartu **Kesegaran Data** akan berwarna merah. Itu adalah peringatan yang benar, bukan kegagalan import.

## 4. Mengenal dashboard

Setelah login, halaman **Dashboard Persediaan** menampilkan:

- **Produk Aktif**: produk yang ikut dihitung pada proses berikutnya;
- **Perlu Dipesan**: jumlah produk berlabel final `Perlu Pesan` pada proses berhasil terbaru;
- **Total Unit Disarankan**: total kuantitas pemesanan dari hasil terbaru;
- **Kesegaran Data**: tanggal histori paling baru dan status usianya;
- **Status Proses Rekomendasi**: antre, berjalan, berhasil, gagal, atau belum pernah;
- **Kesehatan Model**: validitas artifact, versi, threshold, dan metrik test-set; serta
- **Prioritas Pemesanan Teratas**: maksimal sepuluh produk yang perlu dipesan dari proses berhasil terbaru.

Dashboard tidak mengambil hasil dari proses yang gagal atau masih berjalan. Tabel prioritas hanya membaca proses berstatus berhasil yang paling baru.

## 5. Latihan 1 - Menghasilkan rekomendasi dari dataset canonical

### Langkah 1: pastikan artifact valid

Jalankan:

```powershell
php artisan ml:model-info
php artisan ml:verify-parity
```

Hasil yang diharapkan:

- `ml:model-info` menampilkan model versi `1.0.0`, sembilan fitur, dan threshold `0.99`;
- `ml:verify-parity` menampilkan `100 vector model valid` dan `78 rekomendasi valid`.

Jangan mengubah JSON artifact, checksum, threshold, atau fixture parity untuk memaksa proses lulus.

### Langkah 2: pastikan data tersedia

Pada dashboard, periksa:

- **Produk Aktif** berisi `78`;
- **Kesegaran Data** berisi `31 Agustus 2025`; dan
- kartu rekomendasi dapat berisi `Belum dihitung` bila proses belum pernah dijalankan.

### Langkah 3: jalankan perhitungan langsung

Untuk latihan paling sederhana, gunakan mode sinkron agar tidak membutuhkan queue worker:

```powershell
php artisan recommendations:run --sync
```

Untuk mencegah proses ganda saat perintah yang sama diulang, tambahkan kunci idempotensi yang tetap:

```powershell
php artisan recommendations:run --sync --idempotency-key=latihan-canonical-01
```

Menjalankan kembali perintah dengan kunci yang sama akan mengembalikan run yang sudah ada, bukan membuat run baru.

### Langkah 4: periksa hasil

Muat ulang dashboard, lalu periksa:

1. status proses menjadi **Berhasil**;
2. jumlah produk diproses adalah `78`;
3. menu **Hasil Rekomendasi** berisi satu hasil untuk setiap produk aktif; dan
4. tabel prioritas menampilkan produk dengan rekomendasi final `Perlu Pesan`.

Pada artifact, dataset, dan parameter default yang saat ini disertakan di repository, baseline yang telah diverifikasi menghasilkan:

| Pemeriksaan | Nilai baseline |
| --- | ---: |
| Produk diproses | 78 |
| Produk perlu pesan | 7 |
| Total unit disarankan | 177 |

Nilai tersebut dapat berubah bila histori, barang dalam pemesanan, parameter persediaan, atau artifact model berubah secara sah.

## 6. Latihan 2 - Menjalankan rekomendasi melalui dashboard

Tombol **Jalankan Rekomendasi** pada dashboard dan tombol **Proses Rekomendasi** pada menu proses menggunakan queue. Jalankan worker pada terminal kedua:

```powershell
php artisan queue:work --queue=recommendations,default --tries=3 --timeout=300
```

Kemudian:

1. buka **Dashboard Persediaan**;
2. klik **Jalankan Rekomendasi**;
3. konfirmasi aksi;
4. tunggu status berubah dari **Antre**, **Berjalan**, lalu **Berhasil**; dan
5. klik **Lihat Detail** atau buka menu **Hasil Rekomendasi**.

Untuk latihan sekali jalan, worker dapat dibuat berhenti setelah antrean kosong:

```powershell
php artisan queue:work --queue=recommendations,default --stop-when-empty --tries=3 --timeout=300
```

Perbedaan dua cara menjalankan proses:

| Cara | Perilaku | Cocok untuk |
| --- | --- | --- |
| Tombol web / perintah tanpa `--sync` | Masuk queue dan memerlukan worker | Penggunaan operasional |
| `recommendations:run --sync` | Dihitung langsung pada terminal | Latihan, verifikasi, dan diagnosis |

## 7. Latihan 3 - Import data CSV

### Format wajib

CSV harus memakai urutan header berikut:

```text
tanggal,nama_barang,kategori,stok_awal,barang_masuk,barang_keluar,stok_akhir,label
```

Aturan data:

- tanggal memakai format `YYYY-MM-DD`;
- angka stok harus bilangan bulat nonnegatif;
- `stok_akhir = stok_awal + barang_masuk - barang_keluar`;
- stok awal pada tanggal berikutnya harus sama dengan stok akhir sebelumnya;
- satu produk hanya boleh memiliki satu baris per tanggal; dan
- kolom `label` wajib ada untuk kompatibilitas dataset, tetapi nilainya tidak dipakai sebagai fitur atau keputusan produksi.

### Contoh CSV minimal untuk satu produk

Minimal delapan histori diperlukan agar satu produk dapat dihitung:

```csv
tanggal,nama_barang,kategori,stok_awal,barang_masuk,barang_keluar,stok_akhir,label
2026-01-01,Produk Latihan,Minuman,30,0,2,28,Tidak
2026-01-02,Produk Latihan,Minuman,28,5,3,30,Tidak
2026-01-03,Produk Latihan,Minuman,30,0,4,26,Tidak
2026-01-04,Produk Latihan,Minuman,26,10,2,34,Tidak
2026-01-05,Produk Latihan,Minuman,34,0,5,29,Tidak
2026-01-06,Produk Latihan,Minuman,29,0,3,26,Tidak
2026-01-07,Produk Latihan,Minuman,26,6,4,28,Tidak
2026-01-08,Produk Latihan,Minuman,28,0,5,23,Tidak
```

### Import melalui web

1. Masuk sebagai administrator.
2. Klik **Import Data** atau buka **Import Data Stok**.
3. Klik **Import CSV**.
4. Pilih file CSV.
5. Biarkan **Pratinjau saja** aktif untuk validasi pertama.
6. Jika pratinjau valid, ulangi dan nonaktifkan **Pratinjau saja** untuk menyimpan data.
7. Periksa notifikasi jumlah valid, ditambahkan, diperbarui, dan dilewati.

### Import melalui terminal

Validasi tanpa menyimpan:

```powershell
php artisan stock:import "C:\data\stok-latihan.csv" --dry-run
```

Simpan data:

```powershell
php artisan stock:import "C:\data\stok-latihan.csv"
```

Import bersifat idempoten. Baris produk dan tanggal yang sama dengan nilai yang sama akan dilewati. Bila nilainya berubah secara valid, baris diperbarui tanpa membuat duplikasi.

## 8. Latihan 4 - Input histori stok manual

Administrator dapat menambah histori melalui menu **Histori Stok**.

1. Pilih produk.
2. Pilih tanggal setelah histori terbaru produk tersebut.
3. Isi stok awal hanya untuk histori pertama. Untuk histori berikutnya, aplikasi memakai stok akhir sebelumnya.
4. Isi barang masuk dan barang keluar.
5. Aplikasi menghitung stok akhir dengan rumus:

```text
stok akhir = stok awal + barang masuk - barang keluar
```

Aplikasi menolak kondisi berikut:

- tanggal tidak lebih baru dari histori terakhir;
- barang keluar menghasilkan stok negatif;
- histori produk dan tanggal yang sama sudah ada; atau
- pengguna mencoba mengubah histori lama. Hanya histori terbaru yang dapat diedit.

Setelah menambah data, jalankan proses rekomendasi baru. Hasil proses lama tetap tersimpan sebagai riwayat audit dan tidak dihitung ulang otomatis.

## 9. Mengatur parameter persediaan

Buka menu **Parameter Persediaan**. Seeder membuat konfigurasi global berikut:

| Parameter | Default | Arti |
| --- | ---: | --- |
| Lead time | 3 hari | Waktu tunggu barang sejak dipesan sampai tersedia. |
| Periode tinjau | 7 hari | Jarak waktu antar evaluasi/pemesanan. |
| Service level | 0,95 | Tingkat layanan 95% untuk safety stock. |
| Horizon prediksi | 1 hari | Jarak proyeksi stok dari tanggal data terbaru. |

Ketentuan pengaturan:

- cakupan `global` berlaku untuk seluruh produk yang tidak mempunyai pengaturan khusus;
- bila **Produk khusus** dipilih, pengaturan tersebut lebih diprioritaskan untuk produk itu;
- service level diisi sebagai desimal, misalnya `0.95`, bukan `95`;
- **Barang Dalam Pemesanan** diubah dari data produk, bukan dari halaman parameter; dan
- perubahan parameter hanya digunakan pada proses rekomendasi berikutnya.

Kolom **Stok Minimum** dan **Kecepatan Pergerakan** pada produk saat ini merupakan metadata operasional. Titik pesan ulang pada rekomendasi dihitung dinamis dari penjualan, lead time, service level, dan variasi penjualan; bukan langsung dari kolom stok minimum.

## 10. Cara aplikasi menghitung rekomendasi

### 10.1 Syarat histori

Setiap produk aktif membutuhkan minimal delapan record histori:

- record paling baru menjadi kondisi saat ini; dan
- record sebelumnya dipakai untuk statistik penjualan bergulir.

Produk dengan kurang dari delapan record tetap disimpan pada hasil dengan status **Data Tidak Cukup**, jumlah pesan `0`, dan peringatan minimal delapan histori.

### 10.2 Sembilan fitur model

Aplikasi menyusun sembilan fitur berikut:

1. stok akhir terbaru;
2. barang keluar terbaru;
3. rata-rata penjualan tujuh record sebelumnya;
4. standar deviasi populasi penjualan tujuh record sebelumnya;
5. rata-rata penjualan maksimal 30 record sebelumnya;
6. standar deviasi populasi penjualan maksimal 30 record sebelumnya;
7. cakupan stok dalam hari;
8. hari dalam minggu dari tanggal data terbaru; dan
9. horizon target dalam hari.

Record terbaru tidak ikut ke dalam rolling history 7/30. Bila rata-rata penjualan 30 record adalah nol, cakupan stok menjadi kosong lalu ditangani oleh median imputation dari artifact.

### 10.3 Tahap machine learning

Urutan perhitungan native PHP:

```text
Median imputation -> Yeo-Johnson -> Standardisasi
-> Gaussian Naive Bayes -> Probabilitas Perlu Pesan
```

Model dinyatakan positif bila:

```text
probabilitas Perlu Pesan >= threshold artifact
```

Artifact saat ini memakai threshold `0.99` atau `99,00%`. Probabilitas adalah tingkat keyakinan model, bukan jumlah barang yang harus dibeli.

### 10.4 Aturan persediaan hybrid

Simbol yang digunakan:

| Simbol | Keterangan |
| --- | --- |
| `avg30` | Rata-rata barang keluar maksimal 30 record sebelumnya. |
| `std30` | Standar deviasi populasi barang keluar maksimal 30 record sebelumnya. |
| `L` | Lead time. |
| `R` | Periode tinjau. |
| `H` | Horizon prediksi. |
| `z` | Nilai kuantil berdasarkan service level. |
| `stok` | Stok akhir terbaru. |
| `onOrder` | Barang yang sudah dipesan tetapi belum diterima. |

Rumusnya:

```text
raw safety stock   = z x std30 x sqrt(L)
safety stock       = ceil(raw safety stock)
reorder point      = ceil((avg30 x L) + raw safety stock)
target stock       = ceil((avg30 x (L + R)) + raw safety stock)
inventory position = stok + onOrder
projected inventory= max(0, inventory position - (avg30 x H))
trigger persediaan = projected inventory <= reorder point
jumlah pesan       = max(0, ceil(target stock - projected inventory))
```

Rekomendasi final **Perlu Pesan** diberikan bila trigger persediaan aktif, atau model positif dan jumlah hasil perhitungan lebih dari nol. Bila jumlah pesan nol, hasil final tidak memerintahkan pemesanan.

### 10.5 Contoh perhitungan sederhana

Misalkan:

```text
avg30 = 4 unit/hari
std30 = 2
lead time = 3 hari
periode tinjau = 7 hari
horizon = 1 hari
service level = 95%, sehingga z sekitar 1,64485
stok = 10
onOrder = 0
probabilitas model = 70%
```

Perhitungan:

```text
raw safety stock = 1,64485 x 2 x sqrt(3) = sekitar 5,70
safety stock = 6
reorder point = ceil(4 x 3 + 5,70) = 18
target stock = ceil(4 x 10 + 5,70) = 46
projected inventory = max(0, 10 - 4) = 6
jumlah pesan = ceil(46 - 6) = 40
```

Probabilitas 70% berada di bawah threshold 99%, sehingga model tidak aktif. Namun projected inventory `6` berada di bawah reorder point `18`, sehingga **Trigger Stok** aktif dan rekomendasi final tetap **Perlu Pesan 40 unit**. Inilah alasan sistem disebut hybrid.

## 11. Cara membaca hasil rekomendasi

| Kolom | Cara membaca |
| --- | --- |
| Stok Saat Ini | Stok akhir pada histori terbaru. |
| Barang Dalam Pemesanan | Barang yang sudah dipesan tetapi belum datang. |
| Projected Inventory | Perkiraan posisi stok setelah kebutuhan selama horizon. |
| Safety Stock | Cadangan untuk ketidakpastian penjualan selama lead time. |
| Reorder Point | Batas dinamis untuk memicu evaluasi pemesanan. |
| Target Stock | Sasaran stok setelah mempertimbangkan lead time dan periode tinjau. |
| Probabilitas Perlu Pesan | Probabilitas kelas positif dari Gaussian Naive Bayes. |
| Threshold | Batas probabilitas agar klasifikasi model menjadi positif. |
| Klasifikasi Model | Keputusan model sebelum digabung dengan aturan stok. |
| Trigger Persediaan | Apakah projected inventory berada pada/di bawah reorder point. |
| Rekomendasi Final | Keputusan hybrid yang dipakai operator. |
| Jumlah Disarankan | Kuantitas pemesanan hasil target stock dikurangi projected inventory. |

Label trigger pada tabel dashboard:

- **Keduanya**: model dan aturan persediaan sama-sama aktif;
- **Trigger Model**: model melewati threshold;
- **Trigger Stok**: aturan persediaan aktif walaupun model tidak melewati threshold.

Jangan membaca probabilitas sebagai persentase jumlah pembelian. Contoh `99,98%` berarti keyakinan model, sedangkan jumlah pembelian tetap berasal dari aturan target stock.

## 12. Latihan analisis hasil

Pilih satu produk dari **Prioritas Pemesanan Teratas**, lalu isi lembar latihan berikut:

| Pertanyaan | Jawaban peserta |
| --- | --- |
| Nama/SKU produk |  |
| Tanggal data terbaru |  |
| Stok saat ini |  |
| Probabilitas dan threshold |  |
| Klasifikasi model |  |
| Projected inventory |  |
| Reorder point |  |
| Trigger persediaan |  |
| Target stock |  |
| Jumlah disarankan |  |
| Alasan keputusan final |  |

Kemudian lakukan eksperimen berikut:

1. catat hasil awal produk;
2. ubah **Barang Dalam Pemesanan** menjadi nilai lebih besar;
3. jalankan rekomendasi baru;
4. bandingkan inventory position, projected inventory, dan jumlah pesan; dan
5. kembalikan data produk ke nilai awal setelah latihan.

Secara umum, menaikkan barang dalam pemesanan akan menaikkan inventory position dan dapat menurunkan jumlah tambahan yang disarankan.

## 13. Melihat detail dan mengekspor hasil

1. Buka **Proses Rekomendasi**.
2. Pilih proses berstatus berhasil.
3. Periksa jumlah total, berhasil, data kurang, tanggal data, dan versi model.
4. Klik **Ekspor CSV**.

CSV ekspor berisi SKU, produk, kategori, tanggal data, stok saat ini, barang dalam pemesanan, projected inventory, safety stock, reorder point, target stock, probabilitas, threshold, klasifikasi model, trigger persediaan, rekomendasi final, jumlah disarankan, alasan, dan peringatan.

Gunakan ID proses dan versi model pada laporan ketika hasil dimasukkan ke dokumen evaluasi atau lampiran skripsi.

## 14. Troubleshooting

### Tombol rekomendasi nonaktif

Kemungkinan penyebab:

- belum ada histori stok; atau
- artifact model tidak valid.

Jalankan:

```powershell
php artisan ml:model-info
php artisan ml:verify-parity
```

### Status berhenti di Antre

Queue worker belum berjalan. Jalankan:

```powershell
php artisan queue:work --queue=recommendations,default --tries=3 --timeout=300
```

### Produk berstatus Data Tidak Cukup

Produk memiliki kurang dari delapan histori. Tambahkan histori sampai minimal delapan record berurutan, lalu buat proses baru.

### Import ditolak

Periksa:

- nama dan urutan header;
- format tanggal `YYYY-MM-DD`;
- rumus stok akhir;
- kontinuitas stok antar tanggal;
- nilai negatif;
- duplikasi produk dan tanggal; serta
- ukuran file upload maksimal 20 MB pada form web.

Lakukan dry-run untuk melihat pesan validasi tanpa menyimpan data:

```powershell
php artisan stock:import "C:\data\stok.csv" --dry-run
```

### Proses rekomendasi gagal

Periksa log aplikasi:

```powershell
Get-Content storage\logs\laravel.log -Tail 100
```

Setelah penyebab diperbaiki, buka detail proses gagal dan klik **Ulangi Proses**, atau jalankan perintah sinkron untuk diagnosis:

```powershell
php artisan recommendations:run --sync
```

### Tampilan masih memakai aset lama

```powershell
php artisan optimize:clear
npm run build
```

Kemudian muat ulang browser tanpa cache.

## 15. Checklist penggunaan operasional

Sebelum menjalankan rekomendasi:

- [ ] Seluruh produk yang akan dihitung berstatus aktif.
- [ ] Setiap produk memiliki minimal delapan histori.
- [ ] Histori terbaru mencerminkan transaksi terakhir.
- [ ] Barang dalam pemesanan pada data produk sudah diperbarui.
- [ ] Lead time, periode tinjau, service level, dan horizon sudah sesuai.
- [ ] Artifact model berstatus valid.
- [ ] Queue worker aktif bila proses dijalankan dari web.

Setelah proses selesai:

- [ ] Status proses adalah **Berhasil**.
- [ ] Tanggal data sesuai dengan histori terbaru.
- [ ] Produk **Data Tidak Cukup** ditinjau.
- [ ] Produk **Perlu Pesan** diperiksa berdasarkan trigger dan jumlahnya.
- [ ] Hasil diekspor bila dibutuhkan untuk arsip.
- [ ] Keputusan pembelian aktual tetap dikonfirmasi oleh pemilik toko.

## 16. Batasan interpretasi

- Metrik Accuracy, Precision, Recall, F1, PR-AUC, dan ROC-AUC pada dashboard berasal dari test-set penelitian dalam artifact, bukan dari transaksi produksi terbaru.
- Rekomendasi final bukan keluaran murni Naive Bayes. Keputusan menggabungkan probabilitas model dan aturan persediaan.
- Sistem adalah alat bantu keputusan. Kondisi pemasok, promosi, hari libur, anggaran, masa kedaluwarsa, dan kejadian bisnis lain tetap perlu dipertimbangkan oleh pemilik toko.
- Jangan mengubah artifact, checksum, atau threshold langsung pada server operasional tanpa proses training, validasi parity, dan deployment yang terkontrol.
