<?php

namespace App\Domain\Recommendation\Enums;

enum RecommendationItemStatus: string
{
    case Success = 'success';
    case InsufficientData = 'insufficient_data';
    case Failed = 'failed';
}
