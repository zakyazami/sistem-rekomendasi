<?php

namespace App\Domain\Import;

final readonly class StockImportSummary
{
    /** @param list<string> $errors */
    public function __construct(
        public int $totalRows,
        public int $validRows,
        public int $insertedRows,
        public int $updatedRows,
        public int $skippedRows,
        public int $failedRows,
        public int $productCount,
        public int $categoryCount,
        public array $errors = [],
        public ?string $startDate = null,
        public ?string $endDate = null,
    ) {}
}
