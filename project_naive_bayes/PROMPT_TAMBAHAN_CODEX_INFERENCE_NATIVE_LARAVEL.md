# Prompt Tambahan Codex — Override Arsitektur menjadi Inference Native Laravel

> **Cara penggunaan:** tempelkan bagian ini setelah prompt Codex sebelumnya. Seluruh ketentuan di bawah ini **menggantikan setiap instruksi lama yang bertentangan**, terutama bagian Python FastAPI, pembacaan `.pkl`, HTTP ML client, pengujian Python service, status ML service, dan container FastAPI.

Anda tetap bertindak sebagai **Principal Software Engineer, Machine Learning Engineer, dan reviewer arsitektur**. Pada **Mode Plan**, jangan mengubah file. Susun perubahan secara konkret hingga siap dieksekusi. Pada **Mode Execute**, implementasikan seluruh rencana sampai aplikasi dapat dijalankan, diuji, dan digunakan tanpa Python pada lingkungan produksi.

## 1. Keputusan arsitektur baru yang bersifat final

Aplikasi harus menggunakan **single application architecture** berikut:

```text
Pengguna
   ↓
Laravel 13 + Filament 5
   ↓
Feature Engineering PHP
   ↓
Median Imputation PHP
   ↓
Yeo-Johnson + Standardization PHP
   ↓
Gaussian Naive Bayes PHP
   ↓
Aturan Hybrid Persediaan PHP
   ↓
MySQL
```

Ketentuan wajib:

1. Training dan evaluasi model tetap dilakukan melalui notebook/Google Colab.
2. Seluruh inference produksi dijalankan langsung oleh PHP di dalam Laravel.
3. Laravel hanya membaca artifact JSON yang berisi parameter model.
4. Tidak boleh membuat atau mempertahankan FastAPI, Flask, Python service, Python CLI runtime, sidecar ML, atau HTTP call untuk inference.
5. Tidak boleh menggunakan `shell_exec`, `exec`, `proc_open`, atau pemanggilan proses Python dari PHP.
6. File `.pkl` hanya merupakan arsip penelitian dan tidak boleh menjadi dependency aplikasi.
7. Deployment tidak memerlukan Python, pip, scikit-learn, joblib, atau container ML.
8. Queue worker Laravel masih boleh digunakan untuk proses rekomendasi batch, tetapi worker menggunakan image/source Laravel yang sama dengan aplikasi web.
9. Jangan mengubah algoritma menjadi library PHP lain yang perilakunya tidak dapat dibuktikan identik. Implementasikan rumus secara eksplisit dan uji parity.

## 2. File model terbaru yang wajib diaudit dan digunakan

Gunakan hasil notebook terbaru pada project `project_naive_bayes/`:

```text
project_naive_bayes/
├── notebook/
│   └── notebook_naive_bayes_laravel_export_executed.ipynb
├── model/
│   ├── naive_bayes_rekomendasi_stok_laravel.json
│   ├── naive_bayes_rekomendasi_stok_laravel.json.sha256
│   └── naive_bayes_rekomendasi_stok_bundle.pkl   # arsip saja, jangan dipakai Laravel
└── output/
    ├── parity_test_model_laravel.csv
    ├── parity_test_rekomendasi_laravel.csv
    ├── verifikasi_artifact_laravel.json
    ├── metrik_model.csv
    └── rekomendasi_pemesanan_semua_barang.csv
```

Sebelum menyusun rencana, buka dan audit isi artifact JSON serta fixture tersebut. Jangan menyalin parameter numerik secara manual ke source code PHP. Source of truth harus tetap file JSON.

Artifact saat prompt ini dibuat memiliki checksum file SHA-256:

```text
520f43985da4949e148a2c919a628d4c101dadac1f6376f2502530e7469823cc
```

Checksum harus dibaca dari file `.sha256`; jangan hard-code checksum di banyak lokasi. Jika artifact dihasilkan ulang, gunakan checksum terbaru dari manifest.

## 3. Penempatan artifact di repository Laravel

Pada mode eksekusi, salin file deployment ke lokasi version-controlled dan read-only, misalnya:

```text
sistem-rekomendasi-main/
└── resources/
    └── ml/
        ├── naive_bayes_rekomendasi_stok_laravel.json
        └── naive_bayes_rekomendasi_stok_laravel.json.sha256
```

Salin fixture pengujian ke:

```text
sistem-rekomendasi-main/tests/Fixtures/ML/
├── parity_test_model_laravel.csv
└── parity_test_rekomendasi_laravel.csv
```

Jangan meletakkan artifact di `public/`. Artifact tidak menerima upload dari UI. Penggantian model dilakukan melalui proses release terkontrol dan commit yang dapat diaudit.

Tambahkan konfigurasi, misalnya `config/ml.php`:

```php
return [
    'artifact_path' => env(
        'ML_MODEL_ARTIFACT_PATH',
        resource_path('ml/naive_bayes_rekomendasi_stok_laravel.json'),
    ),
    'checksum_path' => env(
        'ML_MODEL_CHECKSUM_PATH',
        resource_path('ml/naive_bayes_rekomendasi_stok_laravel.json.sha256'),
    ),
    'parity_tolerance' => (float) env('ML_PARITY_TOLERANCE', 1.0e-8),
];
```

Hapus atau jangan buat konfigurasi lama berikut karena tidak lagi digunakan:

```text
ML_SERVICE_URL
ML_SERVICE_TOKEN
ML_SERVICE_TIMEOUT
```

## 4. Struktur kelas Laravel yang harus direncanakan

Gunakan pemisahan tanggung jawab yang jelas. Nama dapat disesuaikan apabila audit menemukan struktur domain yang lebih tepat, tetapi jangan menaruh rumus utama di Filament Resource, controller, model observer, atau queued job.

Struktur minimum yang diharapkan:

```text
app/
├── Domain/
│   └── Recommendation/
│       ├── Data/
│       │   ├── FeatureVector.php
│       │   ├── ModelPrediction.php
│       │   └── RecommendationResult.php
│       └── Enums/
│           └── RecommendationLabel.php
├── Services/
│   ├── MachineLearning/
│   │   ├── ModelArtifactLoader.php
│   │   ├── MedianImputer.php
│   │   ├── YeoJohnsonTransformer.php
│   │   ├── GaussianNaiveBayesClassifier.php
│   │   └── NaiveBayesInferenceService.php
│   └── Inventory/
│       ├── InventoryFeatureEngineeringService.php
│       ├── InventoryPolicyService.php
│       └── InventoryRecommendationService.php
├── Exceptions/
│   └── InvalidModelArtifactException.php
└── Providers/
    └── AppServiceProvider.php atau MachineLearningServiceProvider.php
```

Daftarkan loader/inference service sebagai singleton yang immutable. Artifact boleh diparsing satu kali per PHP worker/process. Jangan membaca dan memvalidasi file berulang kali untuk setiap produk dalam satu batch.

## 5. Validasi artifact dan checksum

`ModelArtifactLoader` wajib:

1. Membaca raw bytes file JSON dari path tetap.
2. Membaca hash pertama pada file `.sha256`.
3. Menghitung `hash('sha256', $rawBytes)`.
4. Membandingkan dengan `hash_equals()`.
5. Menolak startup/inference apabila checksum tidak cocok.
6. Melakukan `json_decode(..., true, flags: JSON_THROW_ON_ERROR)`.
7. Memvalidasi minimum:
   - `schema_version` didukung;
   - `artifact_type` sesuai;
   - `feature_order` berisi tepat sembilan fitur yang diharapkan;
   - semua array parameter mempunyai dimensi konsisten;
   - seluruh median, lambda, mean, scale, prior, theta, dan variance numerik serta finite;
   - seluruh `scaler_scale > 0`;
   - seluruh `variance > 0`;
   - jumlah prior sesuai jumlah kelas dan total prior mendekati 1;
   - positive class tersedia;
   - threshold berada pada rentang `[0, 1]`;
   - tidak ada `NaN`, `INF`, atau nilai hilang pada parameter wajib.
8. Menghasilkan exception domain yang aman dan mudah didiagnosis tanpa menampilkan path/stack trace sensitif kepada pengguna akhir.

Field `artifact_sha256` di dalam JSON adalah checksum payload penelitian. Verifikasi deployment utama menggunakan file `.sha256` atas byte JSON final agar tidak bergantung pada perbedaan encoder JSON Python dan PHP.

## 6. Implementasi preprocessing yang wajib identik

### 6.1 Urutan fitur

Urutan tidak boleh berubah:

```text
1. stok_akhir
2. barang_keluar
3. rata_penjualan_7
4. std_penjualan_7
5. rata_penjualan_30
6. std_penjualan_30
7. cakupan_stok_hari
8. hari_dalam_minggu
9. horizon_hari_target
```

Jangan mengandalkan urutan associative array dari request atau database. Bentuk vector berdasarkan `feature_order` dari artifact.

### 6.2 Median imputation

Untuk setiap fitur:

```text
jika null, NaN, atau non-finite → gunakan median pada preprocessing.imputer.statistics[index]
```

Nilai `cakupan_stok_hari` harus menjadi `null` ketika `rata_penjualan_30 == 0`, lalu ditangani oleh imputer. Jangan menggantinya diam-diam dengan nol.

### 6.3 Transformasi Yeo-Johnson

Implementasikan per fitur dengan `lambda` dari artifact.

Untuk `x >= 0`:

```text
lambda ≈ 0 : log1p(x)
selain itu : ((x + 1)^lambda - 1) / lambda
```

Untuk `x < 0`:

```text
lambda ≈ 2 : -log1p(-x)
selain itu : -(((1 - x)^(2 - lambda) - 1) / (2 - lambda))
```

Gunakan toleransi `lambda_zero_tolerance` dan `lambda_two_tolerance` dari artifact. Gunakan operasi double precision PHP (`float` 64-bit). Jangan membulatkan nilai intermediate.

### 6.4 Standardization

Setelah Yeo-Johnson:

```text
z[i] = (transformed[i] - scaler_mean[i]) / scaler_scale[i]
```

Gunakan parameter artifact, bukan menghitung ulang mean/scale dari data aplikasi.

## 7. Implementasi Gaussian Naive Bayes yang wajib identik

Untuk setiap kelas `c`, hitung joint log likelihood:

```text
JLL(c) = log(class_prior[c])
         - 0.5 * Σ log(2π * variance[c][i])
         - 0.5 * Σ ((z[i] - theta[c][i])² / variance[c][i])
```

Konversi JLL menjadi probabilitas dengan stable softmax:

```text
m = max(JLL)
exp_c = exp(JLL(c) - m)
P(c) = exp_c / Σ exp_k
```

Ketentuan:

- Temukan index positive class dari array `classes`; jangan menganggap index positif selalu posisi tertentu tanpa validasi.
- Prediksi model positif apabila `probability_positive >= probability_threshold`.
- Threshold saat ini adalah `0.99`, tetapi baca dari artifact.
- Jangan membulatkan probabilitas sebelum threshold comparison.
- Nilai yang ditampilkan di UI boleh diformat, tetapi nilai tersimpan sebaiknya memakai decimal precision yang memadai.
- Simpan probabilitas kedua kelas atau minimal probabilitas positif, JLL bila diperlukan untuk audit, model version, checksum, threshold, dan feature snapshot.

## 8. Feature engineering persediaan di PHP

Feature engineering tetap harus identik dengan notebook:

1. Ambil histori harian produk dan urutkan tanggal naik.
2. Minimal delapan record; jika kurang, hasil `Data Tidak Cukup` tanpa probabilitas palsu.
3. Baris terbaru adalah kondisi saat ini.
4. Rolling history hanya memakai baris sebelum baris terbaru.
5. Rolling 7 memakai maksimal tujuh record terakhir.
6. Rolling 30 memakai maksimal tiga puluh record terakhir.
7. Mean memakai rata-rata aritmetika.
8. Standard deviation memakai populasi:

```text
sqrt(Σ(x - mean)² / n)
```

9. Hari dalam minggu harus sama dengan Python/Carbon:

```text
Senin = 0 ... Minggu = 6
Carbon::dayOfWeekIso - 1
```

10. Rumus persediaan:

```text
safety_stock = z(service_level) * std_penjualan_30 * sqrt(lead_time_hari)
reorder_point = rata_penjualan_30 * lead_time_hari + safety_stock
target_stock = rata_penjualan_30 * (lead_time_hari + review_period_hari) + safety_stock
inventory_position = stok_saat_ini + barang_dalam_pemesanan
projected_inventory = max(0, inventory_position - rata_penjualan_30 * horizon_hari_target)
jumlah_pesan = max(0, ceil(target_stock - projected_inventory))
```

Untuk konfigurasi default `service_level = 0.95`, gunakan `service_level_z` dari artifact agar parity terjaga. Jika aplikasi mengizinkan service level lain, implementasikan inverse normal CDF yang teruji dan jangan menggunakan pendekatan kasar. Default penelitian harus tetap 0.95.

Safety stock, reorder point, dan target stock dibulatkan ke atas pada nilai operasional, sama seperti notebook.

## 9. Aturan keputusan hybrid

Simpan hasil secara transparan:

```text
model_prediction = probability_positive >= threshold
inventory_trigger = projected_inventory <= reorder_point
inventory_quantity = max(0, ceil(target_stock - projected_inventory))
final_need = inventory_trigger OR (model_prediction AND inventory_quantity > 0)
final_quantity = final_need ? inventory_quantity : 0
```

UI dan database harus membedakan:

- probabilitas kelas;
- klasifikasi model;
- inventory trigger;
- rekomendasi final;
- jumlah pesan;
- alasan keputusan.

Jangan menyebut rekomendasi final sebagai keluaran murni Naive Bayes karena terdapat aturan inventory trigger.

## 10. Perubahan terhadap rencana Laravel sebelumnya

Pertahankan seluruh rencana domain Laravel, MySQL, import CSV, recommendation run, Filament, authorization, audit trail, dan queue yang masih relevan. Ubah bagian yang terkait ML sebagai berikut:

### Hapus dari rencana

- direktori `ml-service/`;
- FastAPI dan Pydantic;
- endpoint `/health`, `/model-info`, `/predict`, `/predict-batch`;
- HTTP bearer token ML;
- Laravel ML HTTP client;
- timeout/retry HTTP inference;
- container Python;
- pytest untuk service produksi;
- dependency lock Python dalam deployment;
- widget “status ML service”.

### Ganti dengan

- local PHP inference service;
- artifact JSON + checksum status;
- queued job yang memanggil `InventoryRecommendationService` secara lokal;
- failure handling untuk artifact invalid atau data tidak cukup;
- dashboard “Status Artifact Model”, model version, checksum singkat, threshold, tanggal training, dan metrics snapshot;
- command diagnostik lokal.

Queued job tetap harus menggunakan lock/idempotency, memuat histori secara efisien, menghindari N+1 query, dan menyimpan hasil secara atomik.

## 11. Artisan commands yang disarankan

Rencanakan dan implementasikan command berikut atau padanan yang setara:

```text
php artisan ml:model-info
php artisan ml:verify-parity
php artisan recommendations:run
```

Perilaku:

- `ml:model-info`: validasi checksum/schema dan tampilkan model version, feature order, threshold, tanggal training, dan metrics.
- `ml:verify-parity`: jalankan fixture model, bandingkan transformasi/JLL/probabilitas/prediksi, keluar dengan non-zero exit code jika gagal.
- `recommendations:run`: membuat recommendation run dan memproses seluruh produk aktif melalui service lokal; dapat memilih sync atau queue secara eksplisit.

Command tidak boleh mencetak data sensitif atau seluruh artifact.

## 12. Pengujian Laravel/Pest yang wajib

Tidak ada test FastAPI. Semua pengujian inference berada di Pest/PHP.

### 12.1 Artifact loader

- file JSON valid dan checksum cocok;
- checksum salah;
- JSON rusak;
- schema version tidak didukung;
- feature count salah;
- parameter non-finite;
- scale/variance nol atau negatif;
- threshold invalid;
- artifact/file tidak ditemukan.

### 12.2 Preprocessing

- imputation setiap fitur;
- Yeo-Johnson cabang `x >= 0, lambda ≈ 0`;
- Yeo-Johnson cabang `x >= 0, lambda != 0`;
- Yeo-Johnson cabang `x < 0, lambda ≈ 2`;
- Yeo-Johnson cabang `x < 0, lambda != 2`;
- standardization;
- urutan fitur tidak berubah;
- input associative array acak tetap menghasilkan vector benar;
- nilai null/non-finite ditangani sesuai median.

### 12.3 GaussianNB

- perhitungan JLL;
- stable softmax untuk nilai log sangat kecil/besar;
- probabilitas berjumlah mendekati satu;
- threshold memakai operator `>=`;
- hasil deterministik;
- tidak ada pembulatan intermediate.

### 12.4 Parity model

Gunakan `tests/Fixtures/ML/parity_test_model_laravel.csv` yang memuat 100 vector seimbang dari test set.

Untuk setiap baris verifikasi:

- transformed feature, tolerance maksimum `1e-8`;
- joint log likelihood, tolerance maksimum `1e-8`;
- probability tiap kelas, tolerance maksimum `1e-8`;
- prediction class dan label harus sama persis.

Jangan mengurangi jumlah fixture hanya agar test lulus. Jika ada selisih, perbaiki rumus PHP.

### 12.5 Parity rekomendasi

Gunakan `parity_test_rekomendasi_laravel.csv` untuk seluruh 78 produk:

- feature input;
- probabilitas model;
- klasifikasi model;
- trigger inventory;
- projected inventory;
- reorder point dan target stock;
- rekomendasi final;
- jumlah stok yang disarankan.

Label dan quantity harus identik. Nilai floating-point memakai tolerance `1e-8` atau tolerance yang lebih longgar hanya jika terdapat bukti numerik yang terdokumentasi dan tidak mengubah keputusan.

### 12.6 Feature engineering database

Tambahkan test terhadap histori buatan yang memverifikasi:

- current row dikeluarkan dari rolling history;
- rolling 7 dan 30 berdasarkan record, bukan hari kalender;
- `ddof=0`;
- weekday mapping;
- penjualan nol menghasilkan coverage `null` sebelum imputasi;
- kurang dari 8 data menghasilkan `Data Tidak Cukup`;
- histori tidak berurutan tetap dihitung setelah sorting;
- tidak ada N+1 saat batch apabila dapat diuji.

### 12.7 Existing domain test

Tetap jalankan seluruh test yang sebelumnya direncanakan untuk stok, import, database constraint, recommendation run, authorization, password edit, dashboard, dan ekspor laporan.

Quality gate minimum:

```text
php artisan test
vendor/bin/pint --test
npm run build
php artisan migrate:fresh --seed
php artisan ml:verify-parity
```

Semua harus lulus sebelum implementasi dinyatakan selesai.

## 13. Dashboard dan Filament

Ganti indikator service eksternal menjadi indikator lokal:

- Artifact model: Valid/Tidak Valid;
- model name dan version;
- checksum pendek;
- waktu training;
- threshold;
- feature count;
- accuracy, balanced accuracy, precision, recall, F1, PR-AUC, ROC-AUC;
- periode train/test dari artifact;
- tanggal recommendation run terakhir;
- jumlah data tidak cukup;
- top priority recommendation.

Jika artifact tidak valid, tombol menjalankan rekomendasi harus dinonaktifkan atau menghasilkan error domain yang jelas. CRUD master dan histori tetap dapat digunakan.

## 14. Deployment satu aplikasi

Docker Compose atau deployment lokal hanya membutuhkan:

```text
- nginx/web server
- Laravel PHP application
- Laravel queue worker, menggunakan image/source yang sama
- MySQL
```

Tidak ada service Python/FastAPI. Build image tidak menginstal Python packages. Node hanya dibutuhkan pada tahap build asset, bukan inference runtime.

Dokumentasikan setup minimal:

```text
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan ml:model-info
php artisan ml:verify-parity
php artisan serve
php artisan queue:work
```

Untuk development dapat tetap memakai `composer run dev` jika script project mendukung.

## 15. Format output Mode Plan yang telah diperbarui

Pada hasil plan, bagian arsitektur harus menampilkan:

```text
Laravel → Local PHP ML Services → MySQL
```

Bukan Laravel → FastAPI.

Plan wajib menyebut:

1. file lama terkait FastAPI/HTTP yang tidak akan dibuat atau akan dihapus;
2. file Laravel/PHP yang dibuat/diubah;
3. mekanisme artifact loading/checksum;
4. pseudocode preprocessing dan GaussianNB;
5. mapping fixture parity ke Pest tests;
6. alur batch recommendation lokal;
7. perubahan dashboard;
8. deployment tanpa Python;
9. command setup dan verification;
10. acceptance criteria bahwa aplikasi dapat bekerja tanpa proses Python aktif.

## 16. Acceptance criteria final

Implementasi dinyatakan selesai hanya jika:

- Laravel dapat boot tanpa Python terinstal;
- tidak ada request HTTP untuk inference;
- tidak ada pemanggilan executable Python;
- `.pkl` tidak dibaca oleh Laravel;
- checksum dan schema artifact tervalidasi;
- 100 parity vector model lulus;
- 78 parity recommendation lulus;
- probability dan label sesuai fixture;
- fitur dibuat dari histori database secara identik;
- batch run tersimpan dan dapat dilihat di Filament;
- import dataset berjalan;
- seluruh test dan formatter lulus;
- README menjelaskan training di Colab dan inference native di Laravel;
- aplikasi dapat dideploy sebagai satu codebase Laravel + MySQL.

## 17. Instruksi saat Mode Execute

Setelah plan disetujui, gunakan instruksi berikut:

```text
Execute the approved plan completely with the native Laravel/PHP inference override.
Remove every FastAPI/Python-runtime assumption from the implementation.
Copy and validate the JSON artifact and parity fixtures from project_naive_bayes.
Implement median imputation, Yeo-Johnson transformation, standardization,
Gaussian Naive Bayes, and the hybrid inventory rule directly in PHP.
Run all Pest parity tests, migrations, seed/import checks, Pint, and frontend build.
Do not stop at scaffolding, TODOs, placeholders, or partially passing tests.
Fix errors until every acceptance criterion and quality gate passes.
```
