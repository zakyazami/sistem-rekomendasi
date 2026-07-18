<?php

namespace App\Domain\Recommendation\Enums;

enum RecommendationLabel: string
{
    case NeedsOrder = 'Perlu Pesan';
    case NoOrder = 'Tidak';
    case InsufficientData = 'Data Tidak Cukup';
    case Failed = 'Gagal';
}
