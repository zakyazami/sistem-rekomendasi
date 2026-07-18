<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockHistory;
use App\Services\Import\StockCsvImportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

class TokoBarokahReferenceDatasetSeeder extends Seeder
{
    public function __construct(private readonly StockCsvImportService $importService) {}

    public function run(): void
    {
        $startedAt = microtime(true);
        $path = (string) config('seeding.reference_dataset_path');
        $expected = config('seeding.expected');
        $preview = $this->importService->preview($path);

        if ($preview->validRows !== $expected['rows']
            || $preview->productCount !== $expected['products']
            || $preview->categoryCount !== $expected['categories']) {
            throw new RuntimeException(sprintf(
                'Dataset referensi tidak sesuai: ditemukan %d baris, %d produk, dan %d kategori.',
                $preview->validRows,
                $preview->productCount,
                $preview->categoryCount,
            ));
        }

        if ($preview->startDate !== $expected['start_date'] || $preview->endDate !== $expected['end_date']) {
            throw new RuntimeException(
                "Rentang dataset referensi tidak sesuai: {$preview->startDate} sampai {$preview->endDate}.",
            );
        }

        $summary = $this->importService->commit($path);
        $startDate = $this->dateBoundary(StockHistory::query()->min('date'));
        $endDate = $this->dateBoundary(StockHistory::query()->max('date'));

        if ($startDate !== $expected['start_date'] || $endDate !== $expected['end_date']) {
            throw new RuntimeException("Rentang dataset referensi tidak sesuai: {$startDate} sampai {$endDate}.");
        }

        $inactiveProducts = Product::query()->where('is_active', false)->count();
        $uncategorizedProducts = Product::query()->whereDoesntHave('category')->count();
        if ($inactiveProducts > 0 || $uncategorizedProducts > 0) {
            throw new RuntimeException('Dataset referensi menghasilkan produk tidak aktif atau tanpa kategori.');
        }

        $this->command?->info(sprintf(
            'Dataset referensi: %d kategori, %d produk, %d histori (%s sampai %s); %d baru, %d diperbarui, %d dilewati; %.2f detik.',
            Category::query()->count(),
            Product::query()->count(),
            StockHistory::query()->count(),
            $startDate,
            $endDate,
            $summary->insertedRows,
            $summary->updatedRows,
            $summary->skippedRows,
            microtime(true) - $startedAt,
        ));
    }

    private function dateBoundary(mixed $value): string
    {
        if ($value === null) {
            throw new RuntimeException('Dataset referensi tidak menghasilkan histori stok.');
        }

        return CarbonImmutable::parse($value)->toDateString();
    }
}
