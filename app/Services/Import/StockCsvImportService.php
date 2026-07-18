<?php

namespace App\Services\Import;

use App\Domain\Import\StockImportSummary;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockImport;
use App\Models\User;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SplFileObject;

final class StockCsvImportService
{
    private const HEADERS = [
        'tanggal',
        'nama_barang',
        'kategori',
        'stok_awal',
        'barang_masuk',
        'barang_keluar',
        'stok_akhir',
        'label',
    ];

    public function preview(string $path): StockImportSummary
    {
        $analysis = $this->analyze($path);

        return $analysis['summary'];
    }

    public function commit(string $path, ?User $user = null): StockImportSummary
    {
        $analysis = $this->analyze($path);
        $preview = $analysis['summary'];

        if ($preview->failedRows > 0) {
            throw ValidationException::withMessages([
                'file' => $preview->errors,
            ]);
        }

        return DB::transaction(function () use ($analysis, $path, $user, $preview): StockImportSummary {
            if (! hash_equals($analysis['checksum'], hash_file('sha256', $path))) {
                throw new RuntimeException('File CSV berubah setelah pratinjau divalidasi.');
            }

            $categoryIds = [];
            foreach ($analysis['categories'] as $normalized => $name) {
                $category = Category::query()->firstOrCreate(['name' => $name]);
                $categoryIds[$normalized] = $category->id;
            }

            $products = [];
            foreach ($analysis['products'] as $normalized => $productData) {
                $sku = $this->sku($productData['category_normalized'], $normalized);
                $product = Product::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'category_id' => $categoryIds[$productData['category_normalized']],
                        'name' => $productData['name'],
                        'moving_type' => 'MEDIUM',
                        'minimum_stock' => 10,
                        'on_order_quantity' => 0,
                        'is_active' => true,
                    ],
                );
                $products[$normalized] = $product;
            }

            $inserted = 0;
            $updated = 0;
            $skipped = 0;
            $now = now();
            $rowChunk = [];

            foreach ($this->validRows($path) as $row) {
                $rowChunk[] = $row;
                if (count($rowChunk) < 1000) {
                    continue;
                }

                $this->upsertChunk($rowChunk, $products, $now, $inserted, $updated, $skipped);
                $rowChunk = [];
            }

            if ($rowChunk !== []) {
                $this->upsertChunk($rowChunk, $products, $now, $inserted, $updated, $skipped);
            }

            if (! hash_equals($analysis['checksum'], hash_file('sha256', $path))) {
                throw new RuntimeException('File CSV berubah selama proses import.');
            }

            $summary = new StockImportSummary(
                totalRows: $preview->totalRows,
                validRows: $preview->validRows,
                insertedRows: $inserted,
                updatedRows: $updated,
                skippedRows: $skipped,
                failedRows: 0,
                productCount: $preview->productCount,
                categoryCount: $preview->categoryCount,
                startDate: $preview->startDate,
                endDate: $preview->endDate,
            );

            StockImport::query()->create([
                'public_id' => (string) Str::uuid(),
                'user_id' => $user?->id,
                'original_name' => basename($path),
                'stored_path' => $path,
                'checksum' => $analysis['checksum'],
                'status' => 'completed',
                'mode' => 'commit',
                'total_rows' => $summary->totalRows,
                'valid_rows' => $summary->validRows,
                'inserted_rows' => $summary->insertedRows,
                'updated_rows' => $summary->updatedRows,
                'skipped_rows' => $summary->skippedRows,
                'failed_rows' => 0,
                'validation_summary' => [
                    'products' => $summary->productCount,
                    'categories' => $summary->categoryCount,
                ],
                'started_at' => $now,
                'finished_at' => now(),
            ]);

            return $summary;
        });
    }

    /**
     * @return array{
     *   summary: StockImportSummary,
     *   categories: array<string, string>,
     *   products: array<string, array{name: string, category_normalized: string}>,
     *   checksum: string
     * }
     */
    private function analyze(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages(['file' => 'File CSV tidak ditemukan.']);
        }

        $file = new SplFileObject($path);
        $file->setCsvControl(',', '"', '');
        $headers = $file->fgetcsv();
        if (isset($headers[0])) {
            $headers[0] = ltrim((string) $headers[0], "\xEF\xBB\xBF");
        }

        if ($headers !== self::HEADERS) {
            throw ValidationException::withMessages(['file' => 'Header CSV tidak sesuai.']);
        }

        $categories = [];
        $products = [];
        $errors = [];
        $stockByProduct = [];
        $productNames = [];
        $line = 1;
        $totalRows = 0;
        $validRows = 0;
        $startDate = null;
        $endDate = null;

        while (! $file->eof()) {
            $values = $file->fgetcsv();
            $line++;

            if (! is_array($values) || $values === [null]) {
                continue;
            }

            $totalRows++;

            if (count($values) !== count(self::HEADERS)) {
                $errors[] = "Baris {$line}: jumlah kolom tidak sesuai.";

                continue;
            }

            $source = array_combine(self::HEADERS, $values);
            $row = $this->validatedRow($source, $line, $errors);
            if ($row === null) {
                continue;
            }

            $productKey = (string) $row['product_normalized'];
            $date = (string) $row['date'];
            if (isset($stockByProduct[$productKey][$date])) {
                throw ValidationException::withMessages([
                    'file' => 'Duplikasi produk dan tanggal ditemukan.',
                ]);
            }
            $stockByProduct[$productKey][$date] = pack(
                'N3',
                (int) $row['initial_stock'],
                (int) $row['final_stock'],
                $line,
            );
            $productNames[$productKey] = (string) $row['product'];
            $categories[$row['category_normalized']] = $row['category'];
            $products[$productKey] = [
                'name' => $row['product'],
                'category_normalized' => $row['category_normalized'],
            ];
            $validRows++;
            $startDate = $startDate === null || $date < $startDate ? $date : $startDate;
            $endDate = $endDate === null || $date > $endDate ? $date : $endDate;
        }

        $this->validateContinuity($stockByProduct, $productNames, $errors);

        $summary = new StockImportSummary(
            totalRows: $totalRows,
            validRows: $validRows,
            insertedRows: 0,
            updatedRows: 0,
            skippedRows: 0,
            failedRows: count($errors),
            productCount: count($products),
            categoryCount: count($categories),
            errors: $errors,
            startDate: $startDate,
            endDate: $endDate,
        );

        return [
            'summary' => $summary,
            'categories' => $categories,
            'products' => $products,
            'checksum' => hash_file('sha256', $path),
        ];
    }

    /** @return Generator<int, array<string, int|string>> */
    private function validRows(string $path): Generator
    {
        $file = new SplFileObject($path);
        $file->setCsvControl(',', '"', '');
        $headers = $file->fgetcsv();
        if (isset($headers[0])) {
            $headers[0] = ltrim((string) $headers[0], "\xEF\xBB\xBF");
        }

        if ($headers !== self::HEADERS) {
            throw ValidationException::withMessages(['file' => 'Header CSV tidak sesuai.']);
        }

        $line = 1;
        while (! $file->eof()) {
            $values = $file->fgetcsv();
            $line++;

            if (! is_array($values) || $values === [null]) {
                continue;
            }

            if (count($values) !== count(self::HEADERS)) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$line}: jumlah kolom tidak sesuai.",
                ]);
            }

            $source = array_combine(self::HEADERS, $values);
            $errors = [];
            $row = $this->validatedRow($source, $line, $errors);
            if ($row === null) {
                throw ValidationException::withMessages(['file' => $errors]);
            }

            yield $row;
        }
    }

    /**
     * @param  array<string, string|null>  $source
     * @param  list<string>  $errors
     * @return array<string, int|string>|null
     */
    private function validatedRow(array $source, int $line, array &$errors): ?array
    {
        $product = trim((string) $source['nama_barang']);
        $category = trim((string) $source['kategori']);
        $date = trim((string) $source['tanggal']);
        $numbers = [];

        foreach (['stok_awal', 'barang_masuk', 'barang_keluar', 'stok_akhir'] as $field) {
            $value = trim((string) $source[$field]);
            if (! preg_match('/^\d+$/', $value)) {
                $errors[] = "Baris {$line}: {$field} harus bilangan nonnegatif.";

                return null;
            }
            $numbers[$field] = (int) $value;
        }

        $parsedDate = date_create_immutable_from_format('!Y-m-d', $date);
        if ($product === '' || $category === '' || $parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
            $errors[] = "Baris {$line}: identitas produk, kategori, atau tanggal tidak valid.";

            return null;
        }

        if ($numbers['stok_akhir'] !== $numbers['stok_awal'] + $numbers['barang_masuk'] - $numbers['barang_keluar']) {
            $errors[] = "Baris {$line}: rumus stok akhir tidak sesuai.";

            return null;
        }

        return [
            'date' => $date,
            'product' => $product,
            'product_normalized' => $this->normalize($product),
            'category' => $category,
            'category_normalized' => $this->normalize($category),
            'initial_stock' => $numbers['stok_awal'],
            'incoming_stock' => $numbers['barang_masuk'],
            'outgoing_stock' => $numbers['barang_keluar'],
            'final_stock' => $numbers['stok_akhir'],
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $stockByProduct
     * @param  array<string, string>  $productNames
     * @param  list<string>  $errors
     */
    private function validateContinuity(array $stockByProduct, array $productNames, array &$errors): void
    {
        foreach ($stockByProduct as $productKey => $stockByDate) {
            ksort($stockByDate);
            $previousFinalStock = null;

            foreach ($stockByDate as $date => $packedStock) {
                $stock = unpack('Ninitial/Nfinal/Nline', $packedStock);
                if ($previousFinalStock !== null && $stock['initial'] !== $previousFinalStock) {
                    $errors[] = sprintf(
                        'Kontinuitas stok %s pada %s tidak sesuai.',
                        $productNames[$productKey],
                        $date,
                    );
                }

                $previousFinalStock = $stock['final'];
            }
        }
    }

    /**
     * @param  list<array<string, int|string>>  $rowChunk
     * @param  array<string, Product>  $products
     */
    private function upsertChunk(
        array $rowChunk,
        array $products,
        mixed $now,
        int &$inserted,
        int &$updated,
        int &$skipped,
    ): void {
        $productIds = collect($rowChunk)
            ->map(fn (array $row): int => $products[$row['product_normalized']]->id)
            ->unique()
            ->values()
            ->all();
        $dates = collect($rowChunk)->pluck('date')->unique()->values()->all();
        $existing = DB::table('stock_histories')
            ->whereIn('product_id', $productIds)
            ->whereIn('date', $dates)
            ->get()
            ->keyBy(static fn (object $history): string => $history->product_id.'|'.substr((string) $history->date, 0, 10));
        $upserts = [];

        foreach ($rowChunk as $row) {
            /** @var Product $product */
            $product = $products[$row['product_normalized']];
            $key = $product->id.'|'.$row['date'];
            $current = $existing->get($key);
            $values = [
                'initial_stock' => $row['initial_stock'],
                'incoming_stock' => $row['incoming_stock'],
                'outgoing_stock' => $row['outgoing_stock'],
                'final_stock' => $row['final_stock'],
            ];

            if ($current !== null && $this->sameStockValues($current, $values)) {
                $skipped++;

                continue;
            }

            $current === null ? $inserted++ : $updated++;
            $upserts[] = [
                'product_id' => $product->id,
                'date' => $row['date'],
                ...$values,
                'created_at' => $current?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        if ($upserts === []) {
            return;
        }

        DB::table('stock_histories')->upsert(
            $upserts,
            ['product_id', 'date'],
            ['initial_stock', 'incoming_stock', 'outgoing_stock', 'final_stock', 'updated_at'],
        );
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::squish($value));
    }

    private function sku(string $category, string $product): string
    {
        return 'BRK-'.strtoupper(substr(hash('sha256', $category.'|'.$product), 0, 12));
    }

    /** @param array{initial_stock: int, incoming_stock: int, outgoing_stock: int, final_stock: int} $values */
    private function sameStockValues(object $history, array $values): bool
    {
        return $history->initial_stock === $values['initial_stock']
            && $history->incoming_stock === $values['incoming_stock']
            && $history->outgoing_stock === $values['outgoing_stock']
            && $history->final_stock === $values['final_stock'];
    }
}
