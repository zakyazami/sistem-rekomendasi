<?php

namespace App\Domain\Recommendation\Data;

use InvalidArgumentException;

final readonly class InventoryParameters
{
    public function __construct(
        public int $leadTimeDays,
        public int $reviewPeriodDays,
        public float $serviceLevel,
        public int $horizonDays,
        public int $onOrderQuantity,
    ) {
        if ($this->leadTimeDays < 0 || $this->reviewPeriodDays < 0 || $this->horizonDays < 1) {
            throw new InvalidArgumentException('Parameter hari persediaan tidak valid.');
        }

        if ($this->serviceLevel <= 0.0 || $this->serviceLevel >= 1.0) {
            throw new InvalidArgumentException('Service level harus berada di antara nol dan satu.');
        }

        if ($this->onOrderQuantity < 0) {
            throw new InvalidArgumentException('Barang dalam pemesanan tidak boleh negatif.');
        }
    }

    public static function defaults(): self
    {
        return new self(
            leadTimeDays: 3,
            reviewPeriodDays: 7,
            serviceLevel: 0.95,
            horizonDays: 1,
            onOrderQuantity: 0,
        );
    }
}
