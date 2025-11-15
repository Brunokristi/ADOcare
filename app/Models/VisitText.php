<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitText extends Model
{
    use HasFactory;

    protected $table = 'visit_texts';

    public $timestamps = false;

    protected $fillable = ['visit_id', 'text_id'];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function text()
    {
        return $this->belongsTo(TextBlock::class, 'text_id');
    }
}
