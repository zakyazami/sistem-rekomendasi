# Sistem Rekomendasi Pemesanan Stok Toko Barokah

Aplikasi skripsi berbasis Laravel 13 dan Filament 5 untuk mengelola stok harian serta menghasilkan rekomendasi pemesanan yang dapat diaudit. Arsitektur produksi adalah satu codebase:

```text
Laravel / Filament → Feature Engineering PHP → Inference native PHP → Aturan Persediaan PHP → MySQL
```

Tidak ada FastAPI, HTTP inference, Python CLI, atau proses Python pada runtime aplikasi. Training dan evaluasi tetap dilakukan di notebook/Google Colab. Laravel hanya membaca artifact JSON yang tervalidasi checksum; file `.pkl` merupakan arsip penelitian dan tidak dibaca aplikasi.

## Kemampuan utama

- CRUD kategori, produk, pengguna, dan histori stok dengan policy berbasis peran.
- Validasi kontinuitas serta perhitungan stok akhir di service server-side.
- Preview dan import CSV privat, transaksional, idempotent, dan berbasis SKU deterministik.
- Median imputation, Yeo-Johnson, standardisasi, dan Gaussian Naive Bayes dalam PHP 64-bit tanpa pembulatan intermediate.
- Aturan hybrid yang memisahkan probabilitas model, klasifikasi model, trigger persediaan, rekomendasi final, dan jumlah pesan.
- Recommendation run sinkron atau queue, retry, audit snapshot, dashboard, dan ekspor CSV.
- Verifikasi 100 vector parity model serta 78 kasus parity rekomendasi.

## Prasyarat lokal

- PHP 8.4.1 atau lebih baru beserta ekstensi Laravel/MySQL.
- Composer 2.
- MySQL 8.
- Node.js 24 atau versi LTS/kompatibel dengan Vite 8.

## Setup lokal

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

Pada terminal terpisah jalankan queue worker:

```powershell
php artisan queue:work --queue=recommendations,default --tries=3 --timeout=300
```

Isi `SEED_ADMIN_PASSWORD` sebelum seeding. Pada environment lokal/testing yang tidak mengisinya, password awal fallback adalah `password`; segera ganti setelah login. Pada production, seeder tidak membuat admin bila variabel tersebut kosong.

Konfigurasi model menggunakan:

```dotenv
# Opsional; kosongkan agar Laravel memakai resources/ml secara otomatis.
# ML_MODEL_ARTIFACT_PATH=/path/absolut/model.json
# ML_MODEL_CHECKSUM_PATH=/path/absolut/model.json.sha256
ML_PARITY_TOLERANCE=1.0e-8
```

Artifact berada di `resources/ml/`, bukan `public/`, dan penggantiannya harus melalui release/commit terkontrol.

## Import dataset

Header CSV wajib:

```text
tanggal,nama_barang,kategori,stok_awal,barang_masuk,barang_keluar,stok_akhir,label
```

Kolom `label` diterima hanya untuk kompatibilitas dataset penelitian dan tidak digunakan sebagai fitur inference produksi. Lakukan pratinjau sebelum commit:

```powershell
php artisan stock:import project_naive_bayes/data/raw/dataset_toko_barokah.csv --dry-run
php artisan stock:import project_naive_bayes/data/raw/dataset_toko_barokah.csv
```

Dataset referensi yang diaudit memiliki 27.507 baris, 78 produk, 21 kategori, dan periode aktual 1 Agustus 2024 sampai 31 Agustus 2025. Periode ini berbeda dari narasi lama Oktober 2024–Oktober 2025 dan harus diselaraskan pada naskah skripsi, bukan diubah di aplikasi.

## Menjalankan rekomendasi

```powershell
# Masuk antrean
php artisan recommendations:run

# Proses langsung untuk verifikasi/operasional terkontrol
php artisan recommendations:run --sync
```

Di Filament, menu **Proses Rekomendasi** menyediakan aksi queue, retry run gagal, detail hasil, dan ekspor CSV. Tombol proses dinonaktifkan bila artifact tidak valid. Dashboard menampilkan status checksum/schema artifact, versi, threshold, waktu training, periode, seluruh metrik test-set, data terbaru, data tidak cukup, dan prioritas pemesanan.

## Verifikasi model dan quality gate

```powershell
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
php artisan ml:model-info
php artisan ml:verify-parity
npm install
npm run build
```

`ml:model-info` gagal dengan exit code non-zero saat checksum/schema/parameter artifact tidak valid. `ml:verify-parity` memeriksa fixture model dan rekomendasi secara lokal tanpa jaringan atau runtime Python.

## Docker Compose

Salin `.env.example` ke `.env`, isi `APP_KEY`, kredensial MySQL, dan password admin, lalu jalankan:

```powershell
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan ml:verify-parity
```

Aplikasi tersedia pada `http://localhost:8080`. Compose hanya berisi nginx, Laravel PHP-FPM, queue worker dari image/source Laravel yang sama, dan MySQL. Node hanya dipakai pada build stage asset; tidak ada container atau package Python.

## Struktur model penelitian

- `project_naive_bayes/notebook/`: training dan evaluasi penelitian.
- `resources/ml/`: artifact JSON deployment dan manifest SHA-256.
- `tests/Fixtures/ML/`: fixture parity yang tidak boleh diubah untuk menutupi selisih implementasi.
- `project_naive_bayes/model/*.pkl`: arsip lokal yang diabaikan Git dan bukan dependency Laravel.

Metrik pada dashboard adalah snapshot test set dari artifact, bukan metrik yang dihitung ulang menggunakan data produksi. Rekomendasi final juga tidak diklaim sebagai keluaran murni Naive Bayes karena keputusan menggabungkan klasifikasi model dan trigger persediaan.
