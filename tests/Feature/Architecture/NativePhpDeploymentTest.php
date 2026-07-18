<?php

it('documents MySQL and Indonesian application defaults in the example environment', function () {
    $environment = file_get_contents(base_path('.env.example'));

    expect($environment)
        ->toContain('APP_NAME="Sistem Rekomendasi Toko Barokah"')
        ->toContain('APP_TIMEZONE=Asia/Jakarta')
        ->toContain('APP_LOCALE=id')
        ->toContain('DB_CONNECTION=mysql')
        ->toContain('ML_MODEL_ARTIFACT_PATH=')
        ->toContain('ML_MODEL_CHECKSUM_PATH=')
        ->not->toContain('ML_SERVICE_URL');

    expect(config('app.timezone'))->toBe(env('APP_TIMEZONE', 'Asia/Jakarta'));
});

it('defines one Laravel deployment with nginx queue and MySQL but no Python service', function () {
    $compose = file_get_contents(base_path('docker-compose.yml'));
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($compose)
        ->toContain('nginx:', 'app:', 'queue:', 'mysql:')
        ->not->toContain('python', 'fastapi', 'ml-service');
    expect($dockerfile)
        ->toContain('php:8.4-fpm')
        ->not->toContain('python', 'pip install', 'scikit-learn');
});

it('keeps production inference free from external processes and HTTP model clients', function () {
    $productionFiles = [
        ...glob(base_path('app/**/*.php')) ?: [],
        ...glob(base_path('app/**/**/*.php')) ?: [],
        ...glob(base_path('app/**/**/**/*.php')) ?: [],
        ...glob(base_path('config/*.php')) ?: [],
        ...glob(base_path('routes/*.php')) ?: [],
    ];
    $source = collect(array_unique($productionFiles))
        ->map(fn (string $path): string => file_get_contents($path) ?: '')
        ->implode("\n");

    expect(strtolower($source))
        ->not->toContain('fastapi')
        ->not->toContain('ml_service_url')
        ->not->toContain('shell_exec(')
        ->not->toContain('proc_open(')
        ->not->toContain('python ')
        ->not->toContain('.pkl');
});

it('provides project-specific setup inference verification and research-data documentation', function () {
    $readme = file_get_contents(base_path('README.md'));

    expect($readme)
        ->toContain('Sistem Rekomendasi Pemesanan Stok Toko Barokah')
        ->toContain('Inference native PHP')
        ->toContain('php artisan ml:model-info')
        ->toContain('php artisan ml:verify-parity')
        ->toContain('php artisan stock:import')
        ->toContain('27.507')
        ->toContain('1 Agustus 2024')
        ->not->toContain('About Laravel');
});
