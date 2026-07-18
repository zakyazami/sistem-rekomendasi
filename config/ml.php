<?php

return [
    'artifact_path' => env(
        'ML_MODEL_ARTIFACT_PATH',
        resource_path('ml/naive_bayes_rekomendasi_stok_laravel.json'),
    ),
    'checksum_path' => env(
        'ML_MODEL_CHECKSUM_PATH',
        resource_path('ml/naive_bayes_rekomendasi_stok_laravel.json.sha256'),
    ),
    'schema_version' => '1.0.0',
    'parity_tolerance' => (float) env('ML_PARITY_TOLERANCE', 1.0e-8),
];
