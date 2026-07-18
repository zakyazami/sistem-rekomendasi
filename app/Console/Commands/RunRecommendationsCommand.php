<?php

namespace App\Console\Commands;

use App\Services\Recommendation\RecommendationRunService;
use Illuminate\Console\Command;
use Throwable;

class RunRecommendationsCommand extends Command
{
    protected $signature = 'recommendations:run
        {--sync : Proses langsung tanpa antrean}
        {--idempotency-key= : Kunci idempotency opsional}';

    protected $description = 'Jalankan rekomendasi seluruh produk aktif';

    public function handle(RecommendationRunService $service): int
    {
        try {
            $run = $service->start(
                triggeredBy: null,
                queue: ! $this->option('sync'),
                idempotencyKey: $this->option('idempotency-key'),
            );
        } catch (Throwable $exception) {
            $this->error('Gagal menjalankan rekomendasi: '.$exception->getMessage());

            return self::FAILURE;
        }

        $state = $this->option('sync') ? 'selesai' : 'masuk antrean';
        $this->info("Run {$run->public_id} {$state}.");

        return self::SUCCESS;
    }
}
