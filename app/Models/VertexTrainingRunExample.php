<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VertexTrainingRunExample extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'training_run_id',
        'feedback_id',
        'dataset_role',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(VertexTrainingRun::class, 'training_run_id');
    }
}
