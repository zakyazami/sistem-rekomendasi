<?php

namespace App\Console\Commands;

use App\Services\Import\StockCsvImportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportStockCommand extends Command
{
    protected $signature = 'stock:import
        {path : Path file dataset CSV}
        {--dry-run : Validasi tanpa menyimpan data}';

    protected $description = 'Validasi atau import dataset stok harian Toko Barokah';

    public function handle(StockCsvImportService $service): int
    {
        try {
            $path = (string) $this->argument('path');

            if ($this->option('dry-run')) {
                $summary = $service->preview($path);
                $this->info(sprintf(
                    'Pratinjau valid: %d baris, %d produk, %d kategori.',
                    $summary->validRows,
                    $summary->productCount,
                    $summary->categoryCount,
                ));

                return self::SUCCESS;
            }

            $summary = $service->commit($path);
            $this->info(sprintf(
                'Import selesai: %d ditambahkan, %d diperbarui, %d dilewati.',
                $summary->insertedRows,
                $summary->updatedRows,
                $summary->skippedRows,
            ));

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            foreach (collect($exception->errors())->flatten() as $message) {
                $this->error((string) $message);
            }

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Import gagal. Periksa file dan log aplikasi.');

            return self::FAILURE;
        }
    }
}
