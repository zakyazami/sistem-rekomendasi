<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\StockHistory;
use App\Services\Import\StockCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function writeStockCsv(array $lines): string
{
    $path = sys_get_temp_dir().'/toko-barokah-import-'.bin2hex(random_bytes(5)).'.csv';
    file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);

    return $path;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/toko-barokah-import-*.csv') ?: [] as $file) {
        unlink($file);
    }
});

it('previews and commits a valid csv while ignoring its research label', function () {
    $path = writeStockCsv([
        'tanggal,nama_barang,kategori,stok_awal,barang_masuk,barang_keluar,stok_akhir,label',
        '2025-08-01,Aqua 600ml,Minuman,20,0,3,17,Tidak',
        '2025-08-02,Aqua 600ml,Minuman,17,5,4,18,Perlu Pesan',
    ]);
    $service = app(StockCsvImportService::class);

    $preview = $service->preview($path);

    expect($preview->totalRows)->toBe(2)
        ->and($preview->validRows)->toBe(2)
        ->and($preview->failedRows)->toBe(0)
        ->and(Category::query()->count())->toBe(0);

    $summary = $service->commit($path);

    expect($summary->insertedRows)->toBe(2)
        ->and(Category::query()->sole()->name)->toBe('Minuman')
        ->and(Product::query()->sole()->sku)->toStartWith('BRK-')
        ->and(StockHistory::query()->orderBy('date')->pluck('final_stock')->all())->toBe([17, 18]);
});

it('rejects invalid headers without writing domain data', function () {
    $path = writeStockCsv([
        'tanggal,nama_barang,kategori,stok_awal,barang_masuk,barang_keluar,stok_akhir',
        '2025-08-01,Aqua 600ml,Minuman,20,0,3,17',
    ]);

    expect(fn () => app(StockCsvImportService::class)->commit($path))
        ->toThrow(ValidationException::class, 'Header CSV tidak sesuai.');

    expect(Category::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBe(0)
        ->and(StockHistory::query()->count())->toBe(0);
});

it('rolls back all rows when a formula or continuity check fails', function () {
    $path = writeStockCsv([
        'tanggal,nama_barang,kategori,stok_awal,barang_masuk,barang_keluar,stok_akhir,label',
        '2025-08-01,Aqua 600ml,Minuman,20,0,3,17,Tidak',
        '2025-08-02,Aqua 600ml,Minuman,99,0,4,95,Tidak',
    ]);

    expect(fn () => app(StockCsvImportService::class)->commit($path))
        ->toThrow(ValidationException::class);

    expect(StockHistory::query()->count())->toBe(0);
});

it('rejects duplicate product dates inside a csv', function () {
    $path = writeStockCsv([
        'tanggal,nama_barang,kategori,stok_awal,barang_masuk,barang_keluar,stok_akhir,label',
        '2025-08-01,Aqua 600ml,Minuman,20,0,3,17,Tidak',
        '2025-08-01,Aqua 600ml,Minuman,20,0,3,17,Tidak',
    ]);

    expect(fn () => app(StockCsvImportService::class)->commit($path))
        ->toThrow(ValidationException::class, 'Duplikasi produk dan tanggal ditemukan.');
});

it('is idempotent and skips exact rows on a repeated import', function () {
    $path = writeStockCsv([
        'tanggal,nama_barang,kategori,stok_awal,barang_masuk,barang_keluar,stok_akhir,label',
        '2025-08-01,Aqua 600ml,Minuman,20,0,3,17,Tidak',
    ]);
    $service = app(StockCsvImportService::class);
    $service->commit($path);

    $second = $service->commit($path);

    expect($second->insertedRows)->toBe(0)
        ->and($second->updatedRows)->toBe(0)
        ->and($second->skippedRows)->toBe(1)
        ->and(StockHistory::query()->count())->toBe(1);
});

it('validates the complete reference dataset without database writes', function () {
    $path = base_path('project_naive_bayes/data/raw/dataset_toko_barokah.csv');

    $summary = app(StockCsvImportService::class)->preview($path);

    expect($summary->totalRows)->toBe(27507)
        ->and($summary->validRows)->toBe(27507)
        ->and($summary->failedRows)->toBe(0)
        ->and($summary->productCount)->toBe(78)
        ->and($summary->categoryCount)->toBe(21)
        ->and(StockHistory::query()->count())->toBe(0);
});

it('exposes preview and commit through a deterministic artisan import command', function () {
    $path = writeStockCsv([
        'tanggal,nama_barang,kategori,stok_awal,barang_masuk,barang_keluar,stok_akhir,label',
        '2025-08-01,Aqua 600ml,Minuman,20,0,3,17,Tidak',
    ]);

    $this->artisan('stock:import', ['path' => $path, '--dry-run' => true])
        ->expectsOutputToContain('Pratinjau valid: 1 baris')
        ->assertSuccessful();
    expect(StockHistory::query()->count())->toBe(0);

    $this->artisan('stock:import', ['path' => $path])
        ->expectsOutputToContain('Import selesai: 1 ditambahkan')
        ->assertSuccessful();
    expect(StockHistory::query()->count())->toBe(1);
});
