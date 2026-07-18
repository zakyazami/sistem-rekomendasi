<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardOverviewService;
use Filament\Widgets\Widget;

class ModelHealthOverviewWidget extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.model-health-overview-widget';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return ['overview' => app(DashboardOverviewService::class)->get()];
    }
}
