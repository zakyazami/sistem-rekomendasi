# Hasil Perubahan Notebook untuk Inference Native Laravel

## Tujuan

Training dan evaluasi tetap dilakukan dengan Python/scikit-learn di notebook. Pada deployment, Laravel menjalankan seluruh preprocessing, Gaussian Naive Bayes, dan aturan rekomendasi secara langsung menggunakan parameter pada artifact JSON.

## File utama

- `notebook_naive_bayes_laravel_export.ipynb`: notebook yang sudah dijalankan dan tidak memiliki error.
- `naive_bayes_rekomendasi_stok_laravel.json`: artifact deployment Laravel.
- `naive_bayes_rekomendasi_stok_laravel.json.sha256`: checksum file artifact.
- `parity_test_model_laravel.csv`: 100 kasus uji model untuk memastikan implementasi PHP identik.
- `parity_test_rekomendasi_laravel.csv`: 78 kasus rekomendasi seluruh produk.
- `verifikasi_artifact_laravel.json`: hasil verifikasi implementasi portable terhadap scikit-learn.
- `PROMPT_TAMBAHAN_CODEX_INFERENCE_NATIVE_LARAVEL.md`: instruksi override untuk prompt Codex sebelumnya.

## Hasil verifikasi

- Galat maksimum transformasi: `8.27e-15`.
- Galat maksimum joint log likelihood: `7.11e-14`.
- Galat maksimum probabilitas: `1.10e-14`.
- Perbedaan label prediksi: `0`.

## Dependency produksi

Laravel tidak memerlukan Python, FastAPI, scikit-learn, joblib, atau file `.pkl`. File `.pkl` hanya disimpan sebagai arsip penelitian.
