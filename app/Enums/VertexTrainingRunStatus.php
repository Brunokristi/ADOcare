<?php

namespace App\Enums;

enum VertexTrainingRunStatus: string
{
    case Pending = 'pending';
    case BuildingDataset = 'building_dataset';
    case DatasetReady = 'dataset_ready';
    case TrainingRequested = 'training_requested';
    case Training = 'training';
    case TrainingSucceeded = 'training_succeeded';
    case Evaluating = 'evaluating';
    case EvaluationFailed = 'evaluation_failed';
    case ReadyForPromotion = 'ready_for_promotion';
    case Promoted = 'promoted';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';
    case Skipped = 'skipped';

    /**
     * @return array<int, string>
     */
    public static function activeStatuses(): array
    {
        return [
            self::BuildingDataset->value,
            self::DatasetReady->value,
            self::TrainingRequested->value,
            self::Training->value,
            self::TrainingSucceeded->value,
            self::Evaluating->value,
            self::ReadyForPromotion->value,
        ];
    }
}
