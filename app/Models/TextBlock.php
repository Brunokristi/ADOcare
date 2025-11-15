<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TextBlock extends Model
{
    use HasFactory;

    protected $table = 'text_blocks';

    public $timestamps = false;

    protected $fillable = ['text', 'position'];

    public function visits()
    {
        return $this->belongsToMany(Visit::class, 'visit_texts', 'text_id', 'visit_id');
    }
}
