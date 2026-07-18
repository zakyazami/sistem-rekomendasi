<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\RecommendationDashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryStatsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Ringkasan Persediaan';

    protected function getStats(): array
    {
        $metrics = app(RecommendationDashboardService::class)->metrics();

        return [
            Stat::make('Produk Aktif', number_format($metrics['active_products']))
                ->icon('heroicon-o-cube'),
            Stat::make('Data Terbaru', $metrics['latest_data_date'] ?? 'Belum ada data')
                ->description($metrics['data_is_stale'] ? 'Data perlu diperbarui' : 'Data masih mutakhir')
                ->color($metrics['data_is_stale'] ? 'warning' : 'success'),
            Stat::make('Perlu Pesan', number_format($metrics['needs_order']))
                ->color('danger'),
            Stat::make('Total Jumlah Disarankan', number_format($metrics['total_recommended_quantity']))
                ->icon('heroicon-o-shopping-cart'),
            Stat::make('Data Tidak Cukup', number_format($metrics['insufficient_data']))
                ->color($metrics['insufficient_data'] > 0 ? 'warning' : 'success'),
            Stat::make(
                'Proses Terakhir',
                $metrics['latest_run']?->finished_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? 'Belum pernah',
            ),
        ];
    }
}
