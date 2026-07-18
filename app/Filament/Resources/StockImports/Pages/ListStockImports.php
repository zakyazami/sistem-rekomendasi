<?php

namespace App\Filament\Resources\StockImports\Pages;

use App\Filament\Resources\StockImports\StockImportResource;
use App\Services\Import\StockCsvImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListStockImports extends ListRecords
{
    protected static string $resource = StockImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_csv')
                ->label('Import CSV')
                ->schema([
                    FileUpload::make('file')
                        ->label('Dataset CSV')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain'])
                        ->maxSize(20 * 1024)
                        ->required(),
                    Toggle::make('preview_only')
                        ->label('Pratinjau saja')
                        ->helperText('Aktifkan untuk memvalidasi tanpa menyimpan data.')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path($data['file']);
                    $service = app(StockCsvImportService::class);
                    $summary = $data['preview_only']
                        ? $service->preview($path)
                        : $service->commit($path, auth()->user());

                    Notification::make()
                        ->success()
                        ->title($data['preview_only'] ? 'Pratinjau valid.' : 'Import selesai.')
                        ->body(sprintf(
                            '%d valid, %d ditambahkan, %d diperbarui, %d dilewati.',
                            $summary->validRows,
                            $summary->insertedRows,
                            $summary->updatedRows,
                            $summary->skippedRows,
                        ))
                        ->send();
                }),
        ];
    }
}
