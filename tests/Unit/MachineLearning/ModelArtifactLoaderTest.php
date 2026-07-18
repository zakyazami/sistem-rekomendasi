<?php

use App\Exceptions\InvalidModelArtifactException;
use App\Services\MachineLearning\ModelArtifactLoader;

function copyModelArtifactFixture(): array
{
    $directory = sys_get_temp_dir().'/toko-barokah-ml-'.bin2hex(random_bytes(6));
    mkdir($directory, 0777, true);

    $artifactPath = $directory.'/model.json';
    $checksumPath = $directory.'/model.json.sha256';

    copy(
        dirname(__DIR__, 3).'/resources/ml/naive_bayes_rekomendasi_stok_laravel.json',
        $artifactPath,
    );
    file_put_contents(
        $checksumPath,
        hash_file('sha256', $artifactPath).'  model.json'.PHP_EOL,
    );

    return [$artifactPath, $checksumPath];
}

function rewriteModelArtifact(array $artifact, string $artifactPath, string $checksumPath): void
{
    file_put_contents(
        $artifactPath,
        json_encode($artifact, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
    );
    file_put_contents(
        $checksumPath,
        hash_file('sha256', $artifactPath).'  model.json'.PHP_EOL,
    );
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/toko-barokah-ml-*', GLOB_ONLYDIR) ?: [] as $directory) {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($directory);
    }
});

it('loads a valid artifact when its checksum and schema match', function () {
    [$artifactPath, $checksumPath] = copyModelArtifactFixture();

    $artifact = (new ModelArtifactLoader($artifactPath, $checksumPath))->load();

    expect($artifact->schemaVersion)->toBe('1.0.0')
        ->and($artifact->modelName)->toBe('naive_bayes_rekomendasi_stok_toko_barokah')
        ->and($artifact->modelVersion)->toBe('1.0.0')
        ->and($artifact->featureOrder)->toHaveCount(9)
        ->and($artifact->threshold)->toBe(0.99)
        ->and($artifact->fileChecksum)->toBe(
            '520f43985da4949e148a2c919a628d4c101dadac1f6376f2502530e7469823cc',
        );
});

it('rejects a missing artifact file', function () {
    [$artifactPath, $checksumPath] = copyModelArtifactFixture();
    unlink($artifactPath);

    expect(fn () => (new ModelArtifactLoader($artifactPath, $checksumPath))->load())
        ->toThrow(InvalidModelArtifactException::class, 'Artifact model tidak ditemukan.');
});

it('rejects an artifact whose file checksum does not match', function () {
    [$artifactPath, $checksumPath] = copyModelArtifactFixture();
    file_put_contents($artifactPath, PHP_EOL, FILE_APPEND);

    expect(fn () => (new ModelArtifactLoader($artifactPath, $checksumPath))->load())
        ->toThrow(InvalidModelArtifactException::class, 'Checksum artifact model tidak cocok.');
});

it('rejects malformed json with a valid file checksum', function () {
    [$artifactPath, $checksumPath] = copyModelArtifactFixture();
    file_put_contents($artifactPath, '{not-json');
    file_put_contents($checksumPath, hash_file('sha256', $artifactPath).'  model.json');

    expect(fn () => (new ModelArtifactLoader($artifactPath, $checksumPath))->load())
        ->toThrow(InvalidModelArtifactException::class, 'JSON artifact model tidak valid.');
});

it('rejects unsupported schema versions', function () {
    [$artifactPath, $checksumPath] = copyModelArtifactFixture();
    $artifact = json_decode(file_get_contents($artifactPath), true, flags: JSON_THROW_ON_ERROR);
    $artifact['schema_version'] = '2.0.0';
    rewriteModelArtifact($artifact, $artifactPath, $checksumPath);

    expect(fn () => (new ModelArtifactLoader($artifactPath, $checksumPath))->load())
        ->toThrow(InvalidModelArtifactException::class, 'Versi schema artifact tidak didukung.');
});

it('rejects an invalid feature count or order', function () {
    [$artifactPath, $checksumPath] = copyModelArtifactFixture();
    $artifact = json_decode(file_get_contents($artifactPath), true, flags: JSON_THROW_ON_ERROR);
    array_pop($artifact['feature_order']);
    rewriteModelArtifact($artifact, $artifactPath, $checksumPath);

    expect(fn () => (new ModelArtifactLoader($artifactPath, $checksumPath))->load())
        ->toThrow(InvalidModelArtifactException::class, 'Urutan fitur artifact tidak valid.');
});

it('rejects non numeric or non finite model parameters', function () {
    [$artifactPath, $checksumPath] = copyModelArtifactFixture();
    $artifact = json_decode(file_get_contents($artifactPath), true, flags: JSON_THROW_ON_ERROR);
    $artifact['preprocessing']['imputer']['statistics'][0] = 'NaN';
    rewriteModelArtifact($artifact, $artifactPath, $checksumPath);

    expect(fn () => (new ModelArtifactLoader($artifactPath, $checksumPath))->load())
        ->toThrow(InvalidModelArtifactException::class, 'Parameter numerik artifact tidak valid.');
});

it('rejects zero or negative scaler values and variances', function (string $field) {
    [$artifactPath, $checksumPath] = copyModelArtifactFixture();
    $artifact = json_decode(file_get_contents($artifactPath), true, flags: JSON_THROW_ON_ERROR);

    if ($field === 'scale') {
        $artifact['preprocessing']['power_transformer']['scaler_scale'][0] = 0;
    } else {
        $artifact['classifier']['variance'][0][0] = -1;
    }

    rewriteModelArtifact($artifact, $artifactPath, $checksumPath);

    expect(fn () => (new ModelArtifactLoader($artifactPath, $checksumPath))->load())
        ->toThrow(InvalidModelArtifactException::class, 'Scale dan variance harus lebih besar dari nol.');
})->with(['scale', 'variance']);

it('rejects probability thresholds outside zero and one', function (float $threshold) {
    [$artifactPath, $checksumPath] = copyModelArtifactFixture();
    $artifact = json_decode(file_get_contents($artifactPath), true, flags: JSON_THROW_ON_ERROR);
    $artifact['decision']['probability_threshold'] = $threshold;
    rewriteModelArtifact($artifact, $artifactPath, $checksumPath);

    expect(fn () => (new ModelArtifactLoader($artifactPath, $checksumPath))->load())
        ->toThrow(InvalidModelArtifactException::class, 'Threshold probabilitas tidak valid.');
})->with([-0.01, 1.01]);
