<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardOverviewService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|array|null $columns = [
        'default' => 1,
        'md' => 2,
        'xl' => 4,
    ];

    protected function getStats(): array
    {
        $overview = app(DashboardOverviewService::class)->get();

        return [
            Stat::make('Produk Aktif', $overview->activeProductsLabel)
                ->description('Produk yang disertakan dalam evaluasi stok.')
                ->icon('heroicon-o-cube')
                ->color('primary'),
            Stat::make('Perlu Dipesan', $overview->needsOrderLabel)
                ->description('Produk pada proses rekomendasi berhasil terbaru.')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($overview->needsOrder === null ? 'gray' : 'danger'),
            Stat::make('Total Unit Disarankan', $overview->totalRecommendedQuantityLabel)
                ->description('Akumulasi jumlah pemesanan yang direkomendasikan.')
                ->icon('heroicon-o-shopping-cart')
                ->color('warning'),
            Stat::make('Kesegaran Data', $overview->latestDataDateLabel)
                ->description($overview->freshnessLabel)
                ->descriptionIcon(match ($overview->freshnessColor) {
                    'success' => 'heroicon-o-check-circle',
                    'warning' => 'heroicon-o-clock',
                    default => 'heroicon-o-x-circle',
                })
                ->icon('heroicon-o-calendar-days')
                ->color($overview->freshnessColor),
        ];
    }
}
