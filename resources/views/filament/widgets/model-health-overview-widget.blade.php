<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Kesehatan Model</x-slot>
        <x-slot name="description">Snapshot evaluasi test-set; tidak dihitung dari data produksi.</x-slot>

        <div class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::badge :color="$overview->artifactColor">{{ $overview->artifactStatusLabel }}</x-filament::badge>
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $overview->modelName }}</span>
                        <span class="text-sm text-gray-500">{{ $overview->modelVersionLabel }}</span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Training {{ $overview->trainedAtLabel }} · Threshold
                        <span class="font-medium" title="Batas probabilitas minimum agar model mengklasifikasikan Perlu Pesan.">{{ $overview->thresholdLabel }}</span>
                    </p>
                    <button
                        type="button"
                        class="text-xs text-gray-500 underline decoration-dotted"
                        title="{{ $overview->checksumFull }}"
                        x-on:click="navigator.clipboard.writeText('{{ $overview->checksumFull }}')"
                    >Checksum {{ $overview->checksumShort }} · Salin</button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($overview->mainMetrics as $metric)
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5" title="{{ $metric['tooltip'] }}">
                        <p class="text-xs font-medium text-gray-500">{{ $metric['label'] }}</p>
                        <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $metric['value'] }}</p>
                    </div>
                @endforeach
            </div>

            <details class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <summary class="cursor-pointer text-sm font-medium text-gray-950 dark:text-white">Metrik Lanjutan</summary>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach ($overview->advancedMetrics as $metric)
                        <div title="{{ $metric['tooltip'] }}">
                            <p class="text-xs text-gray-500">{{ $metric['label'] }}</p>
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $metric['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </details>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
