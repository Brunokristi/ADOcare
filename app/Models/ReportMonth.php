<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportMonth extends Model
{
    use HasFactory;

    protected $table = 'report_months';

    protected $fillable = [
        'month',
        'year',
        'examination_start',
        'examination_end',
        'statement_start',
        'statement_end',
        'first_day',
        'last_day',
        'user_id',
        'branch_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class, 'month_id');
    }
}
