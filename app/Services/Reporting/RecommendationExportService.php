<?php

namespace App\Services\Reporting;

use App\Models\RecommendationRun;
use App\Models\StockRecommendation;
use RuntimeException;

final class RecommendationExportService
{
    public function toCsv(RecommendationRun $run): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Laporan CSV tidak dapat dibuat.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        $headers = [
            'SKU',
            'Nama Barang',
            'Kategori',
            'Tanggal Data',
            'Stok Saat Ini',
            'Barang Dalam Pemesanan',
            'Projected Inventory',
            'Safety Stock',
            'Reorder Point',
            'Target Stock',
            'Probabilitas Perlu Pesan',
            'Threshold',
            'Klasifikasi Model',
            'Pemicu Persediaan',
            'Rekomendasi Final',
            'Jumlah Disarankan',
            'Alasan',
            'Peringatan',
        ];
        fwrite($stream, implode(',', $headers)."\n");

        $run->recommendations()
            ->with('product.category')
            ->orderByDesc('recommended_quantity')
            ->each(function (StockRecommendation $recommendation) use ($stream): void {
                fputcsv($stream, [
                    $recommendation->product->sku,
                    $recommendation->product->name,
                    $recommendation->product->category->name,
                    $recommendation->data_date?->toDateString(),
                    $recommendation->current_stock,
                    $recommendation->on_order_quantity,
                    $recommendation->projected_inventory,
                    $recommendation->safety_stock,
                    $recommendation->reorder_point,
                    $recommendation->target_stock,
                    $recommendation->model_probability_positive,
                    $recommendation->model_threshold,
                    $recommendation->model_classification,
                    $recommendation->inventory_trigger ? 'Ya' : 'Tidak',
                    $recommendation->final_recommendation,
                    $recommendation->recommended_quantity,
                    implode('; ', $recommendation->reason_codes ?? []),
                    implode('; ', $recommendation->warnings ?? []),
                ], ',', '"', '');
            });

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new RuntimeException('Laporan CSV tidak dapat dibaca.');
        }

        return $contents;
    }
}
