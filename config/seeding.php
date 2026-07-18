<?php

return [
    'admin' => [
        'name' => env('SEED_ADMIN_NAME', 'Administrator Toko Barokah'),
        'email' => env('SEED_ADMIN_EMAIL', 'admin@tokobarokah.test'),
        'password' => env('SEED_ADMIN_PASSWORD'),
        'force_password' => (bool) env('SEED_FORCE_ADMIN_PASSWORD', false),
    ],

    'reference_data' => (bool) env('SEED_REFERENCE_DATA', false),
    'run_recommendations' => (bool) env('SEED_RUN_RECOMMENDATIONS', false),
    'reference_dataset_path' => env(
        'SEED_REFERENCE_DATASET_PATH',
        base_path('project_naive_bayes/data/raw/dataset_toko_barokah.csv'),
    ),

    'expected' => [
        'rows' => 27507,
        'products' => 78,
        'categories' => 21,
        'start_date' => '2024-08-01',
        'end_date' => '2025-08-31',
    ],
];
