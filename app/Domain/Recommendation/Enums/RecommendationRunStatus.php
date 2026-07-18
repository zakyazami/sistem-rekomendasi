<?php

namespace App\Domain\Recommendation\Enums;

enum RecommendationRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
