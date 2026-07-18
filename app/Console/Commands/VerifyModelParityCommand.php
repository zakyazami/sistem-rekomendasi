<?php

namespace App\Console\Commands;

use App\Services\MachineLearning\ModelParityVerifier;
use Illuminate\Console\Command;
use Throwable;

class VerifyModelParityCommand extends Command
{
    protected $signature = 'ml:verify-parity';

    protected $description = 'Verifikasi parity model PHP dan rekomendasi terhadap notebook';

    public function handle(ModelParityVerifier $verifier): int
    {
        try {
            $result = $verifier->verify();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$result['model']} vector model valid.");
        $this->info("{$result['recommendations']} rekomendasi valid.");

        return self::SUCCESS;
    }
}
