<?php

namespace App\Services\Dashboard;

use Carbon\CarbonImmutable;

final class IndonesianDashboardFormatter
{
    public function integer(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    public function percentage(?float $value): string
    {
        return $value === null ? '-' : number_format($value * 100, 2, ',', '.').'%';
    }

    public function date(?string $value): string
    {
        if ($value === null) {
            return 'Belum ada data';
        }

        return CarbonImmutable::parse($value)
            ->locale('id')
            ->translatedFormat('d F Y');
    }

    public function dateTime(?string $value): string
    {
        if ($value === null) {
            return 'Belum pernah';
        }

        $date = CarbonImmutable::parse($value)
            ->setTimezone(config('app.timezone'))
            ->locale('id');

        return $date->translatedFormat('d F Y, H.i').' '.$date->format('T');
    }

    public function duration(?string $startedAt, ?string $finishedAt): string
    {
        if ($startedAt === null || $finishedAt === null) {
            return '-';
        }

        $seconds = CarbonImmutable::parse($startedAt)->diffInSeconds(CarbonImmutable::parse($finishedAt));
        $minutes = intdiv((int) $seconds, 60);
        $remainingSeconds = (int) $seconds % 60;

        if ($minutes === 0) {
            return "{$remainingSeconds} detik";
        }

        return $remainingSeconds === 0
            ? "{$minutes} menit"
            : "{$minutes} menit {$remainingSeconds} detik";
    }
}
