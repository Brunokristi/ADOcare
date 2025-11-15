<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    use HasFactory;

    protected $table = 'visits';

    protected $fillable = ['date', 'examination', 'statement', 'patient_id', 'month_id'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function month()
    {
        return $this->belongsTo(ReportMonth::class, 'month_id');
    }

    public function texts()
    {
        return $this->belongsToMany(TextBlock::class, 'visit_texts', 'visit_id', 'text_id');
    }
}
