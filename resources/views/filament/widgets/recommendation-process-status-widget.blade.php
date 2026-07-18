<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Status Proses Rekomendasi</x-slot>
        <x-slot name="description">Pantau proses perhitungan rekomendasi terakhir.</x-slot>

        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::badge :color="$overview->runStatusColor">
                        {{ $overview->runStatusLabel }}
                    </x-filament::badge>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $overview->runTimeLabel }}</span>
                </div>

                @if ($overview->latestRun)
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm sm:grid-cols-4">
                        <div><dt class="text-gray-500">Diproses</dt><dd class="font-semibold">{{ number_format($overview->runProcessedProducts, 0, ',', '.') }} produk</dd></div>
                        <div><dt class="text-gray-500">Perlu pesan</dt><dd class="font-semibold">{{ number_format($overview->runNeedsOrder, 0, ',', '.') }} produk</dd></div>
                        <div><dt class="text-gray-500">Durasi</dt><dd class="font-semibold">{{ $overview->runDurationLabel }}</dd></div>
                        <div><dt class="text-gray-500">Tanggal data</dt><dd class="font-semibold">{{ $overview->latestDataDateLabel }}</dd></div>
                    </dl>
                @else
                    <div>
                        <p class="font-medium text-gray-950 dark:text-white">{{ $overview->emptyStateHeading }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $overview->emptyStateDescription }}</p>
                    </div>
                @endif

                @if ($overview->runError)
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $overview->runError }}</p>
                @endif
            </div>

            @if ($showAction)
                <x-filament::button tag="a" :href="$actionUrl" icon="heroicon-o-arrow-right">
                    {{ $actionLabel }}
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
